<?php

namespace Tests\Feature;

use App\Http\Controllers\CurriculumController;
use App\Models\SchoolYear;
use App\Models\Teacher;
use Database\Seeders\CurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

// New modules only before the active school year has started (school_years.started_at), whatever its quarters say now.
class ModuleCreationLockTest extends TestCase
{
    use RefreshDatabase;

    private Teacher $tina;

    private SchoolYear $year;

    private int $joy;

    private int $enrollment;

    private int $math;

    protected function setUp(): void
    {
        parent::setUp();
        // Inside Q1, so a Q1 put back by a year switch goes to upcoming, not completed.
        Carbon::setTestNow(Carbon::parse('2026-07-15 09:00', 'Asia/Manila'));
        $this->seed(CurriculumSeeder::class);
        $this->year = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        foreach ([1 => ['2026-06-01', '2026-08-31'], 2 => ['2026-09-01', '2026-10-31'], 3 => ['2026-11-01', '2026-12-31'], 4 => ['2027-01-01', '2027-03-31']] as $n => [$start, $end]) {
            DB::table('quarters')->insert(['school_year_id' => $this->year->id, 'quarter_number' => $n, 'name' => "Q{$n}", 'start_date' => $start, 'end_date' => $end, 'status' => 'upcoming']);
        }
        $this->tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $this->joy = $this->setUpSections($this->tina, ['Joy'])['Joy'];
        $this->enrollment = DB::table('learner_school_years')->insertGetId([
            'learner_id' => DB::table('learners')->insertGetId(['first_name' => 'Ana', 'last_name' => 'Cruz', 'birth_date' => '2021-01-01', 'gender' => 'Female']),
            'school_year_id' => $this->year->id, 'section_id' => $this->joy, 'enrolled_on' => '2026-06-01', 'status' => 'active',
        ]);
        $this->math = DB::table('developmental_domains')->where('code', 'math_readiness')->value('id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // Sends a new domain too, so a rejection that wrote anything before failing would show in the snapshot.
    private function addModule(string $name = 'Science'): TestResponse
    {
        return $this->actingAs($this->tina)->postJson('/api/modules', ['moduleName' => $name, 'domainIds' => [$this->math], 'newDomain' => "{$name} Discovery"]);
    }

    private function assertRejected(): void
    {
        $before = $this->snapshot();
        $this->addModule()->assertStatus(422)->assertJsonValidationErrors(['schoolYear' => CurriculumController::MODULE_LOCKED]);
        $this->assertSame($before, $this->snapshot());
        $this->assertFalse($this->canAddModules());
    }

    private function canAddModules(): bool
    {
        return $this->actingAs($this->tina)->getJson('/api/school-years/active')->assertOk()->json('canAddModules');
    }

    // Every row a new module writes or could touch.
    private function snapshot(): array
    {
        return collect(['modules', 'developmental_domains', 'module_domains', 'section_modules', 'class_schedules', 'lessons', 'activities', 'school_years', 'quarters'])
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all()])->all();
    }

    private function startedAt(?SchoolYear $year = null): ?string
    {
        return DB::table('school_years')->where('id', ($year ?? $this->year)->id)->value('started_at');
    }

    private function activateQuarter(int $number): void
    {
        $this->actingAs($this->tina)->patchJson("/api/school-years/{$this->year->id}/quarters/{$number}/activate")->assertOk();
    }

    public function test_modules_can_be_added_before_the_school_year_starts(): void
    {
        $this->assertNull($this->startedAt());
        $this->assertTrue($this->canAddModules());

        $created = $this->addModule()->assertCreated()->json();
        $this->assertTrue(DB::table('section_modules')->where(['section_id' => $this->joy, 'module_id' => $created['id']])->exists());
        // Adding a module doesn't start the year.
        $this->assertNull($this->startedAt());
        $this->assertTrue($this->canAddModules());
    }

    public function test_activating_any_quarter_starts_the_year(): void
    {
        $this->activateQuarter(1);
        $this->assertNotNull($this->startedAt());
        $this->assertRejected();

        // A later quarter, and Q1 now completed (past its end date).
        Carbon::setTestNow(Carbon::parse('2026-11-15 09:00', 'Asia/Manila'));
        $this->activateQuarter(3);
        $this->assertSame('completed', DB::table('quarters')->where(['school_year_id' => $this->year->id, 'quarter_number' => 1])->value('status'));
        $this->assertRejected();
    }

    public function test_activating_a_later_quarter_first_starts_the_year(): void
    {
        $this->activateQuarter(2);
        $this->assertRejected();
    }

    public function test_the_year_stays_started_after_a_year_switch_puts_its_quarter_back_to_upcoming(): void
    {
        $this->activateQuarter(1);
        $stamp = $this->startedAt();

        $next = SchoolYear::create(['name' => '2027-2028', 'start_date' => '2027-06-01', 'end_date' => '2028-03-31']);
        $this->actingAs($this->tina)->patchJson("/api/school-years/{$next->id}/activate", ['sections' => ['Joy']])->assertOk();
        $this->actingAs($this->tina)->patchJson("/api/school-years/{$this->year->id}/activate")->assertOk();

        // Back to every quarter upcoming, the state a module could be added in, but the year already started.
        $this->assertSame(['upcoming'], DB::table('quarters')->where('school_year_id', $this->year->id)->distinct()->pluck('status')->all());
        $this->assertSame($stamp, $this->startedAt());
        $this->assertNull($this->startedAt($next));
        $this->assertRejected();
    }

    public function test_a_first_attendance_mark_starts_the_year(): void
    {
        $learner = DB::table('learner_school_years')->where('id', $this->enrollment)->value('learner_id');
        $this->actingAs($this->tina)->putJson("/api/sections/{$this->joy}/attendance", ['entries' => [['learnerId' => $learner, 'status' => 'PRESENT']]])->assertOk();

        $this->assertNotNull($this->startedAt());
        $this->assertRejected();
    }

    public function test_a_first_checklist_rating_starts_the_year_even_once_cleared(): void
    {
        $item = DB::table('developmental_checklist_items')->insertGetId(['domain_id' => $this->math, 'item' => 'Counts to 10', 'grade_level' => 'Kindergarten', 'quarter_number' => 1, 'display_order' => 99]);
        $rate = fn (?string $rating) => $this->actingAs($this->tina)->putJson("/api/sections/{$this->joy}/checklist/ratings", ['enrollmentId' => $this->enrollment, 'quarter' => 1, 'itemId' => $item, 'rating' => $rating]);

        // Clearing a rating that was never set writes nothing, so it starts nothing.
        $rate(null)->assertOk();
        $this->assertNull($this->startedAt());

        $rate('achieved')->assertOk();
        $this->assertNotNull($this->startedAt());
        $this->assertRejected();

        // The rating row is gone, but the year stays started.
        $rate(null)->assertOk();
        $this->assertFalse(DB::table('learner_developmental_checklists')->exists());
        $this->assertRejected();
    }

    public function test_a_first_progress_report_starts_the_year(): void
    {
        $this->actingAs($this->tina)->postJson("/api/sections/{$this->joy}/progress-reports", ['enrollmentId' => $this->enrollment, 'quarter' => 1])->assertCreated();

        $this->assertNotNull($this->startedAt());
        $this->assertRejected();
    }

    public function test_a_failed_write_does_not_start_the_year(): void
    {
        // Rejected attendance (a learner not in the section) and a rejected quarter activation write nothing.
        $this->actingAs($this->tina)->putJson("/api/sections/{$this->joy}/attendance", ['entries' => [['learnerId' => 99999, 'status' => 'PRESENT']]])->assertStatus(422);
        $other = SchoolYear::create(['name' => '2027-2028', 'start_date' => '2027-06-01', 'end_date' => '2028-03-31']);
        DB::table('quarters')->insert(['school_year_id' => $other->id, 'quarter_number' => 1, 'name' => 'Q1', 'start_date' => '2027-06-01', 'end_date' => '2027-08-31', 'status' => 'upcoming']);
        $this->actingAs($this->tina)->patchJson("/api/school-years/{$other->id}/quarters/1/activate")->assertStatus(422);

        $this->assertNull($this->startedAt());
        $this->assertNull($this->startedAt($other));
        $this->addModule()->assertCreated();
    }

    public function test_an_earlier_started_year_does_not_block_the_active_one(): void
    {
        $old = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-06-01', 'end_date' => '2026-03-31', 'status' => 'completed']);
        DB::table('school_years')->where('id', $old->id)->update(['started_at' => '2025-06-02 08:00:00+08']);
        DB::table('quarters')->insert(['school_year_id' => $old->id, 'quarter_number' => 1, 'name' => 'Q1', 'start_date' => '2025-06-01', 'end_date' => '2025-08-31', 'status' => 'completed']);

        $this->assertTrue($this->canAddModules());
        $this->addModule()->assertCreated();
        // Each year's marker is its own.
        $this->assertNull($this->startedAt());
    }

    public function test_module_creation_is_allowed_with_no_active_year(): void
    {
        $this->year->update(['status' => 'completed']);
        DB::table('school_years')->where('id', $this->year->id)->update(['started_at' => now()]);

        $created = $this->addModule()->assertCreated()->json();
        // No current year to link it to; the next activated year picks it up (Section::setUp).
        $this->assertFalse(DB::table('section_modules')->where('module_id', $created['id'])->exists());
    }

    public function test_module_creation_takes_the_quarter_lock(): void
    {
        $this->assertTrue($this->quarterLockFree());
        $this->addModule()->assertCreated();
        $this->assertFalse($this->quarterLockFree());
    }

    public function test_a_first_video_still_attaches_in_the_active_quarter_after_a_rejected_module(): void
    {
        Storage::fake('public');
        $domain = DB::table('module_domains')->join('modules', 'modules.id', '=', 'module_domains.module_id')->where('modules.code', 'reading')->value('module_domains.id');
        $lesson = DB::table('lessons')->insertGetId(['module_domain_id' => $domain, 'title' => 'Letter A', 'quarter_number' => 1, 'week_number' => 1, 'day_of_week' => 1, 'sequence_order' => 1]);
        $this->activateQuarter(1);

        $this->assertRejected();
        $this->actingAs($this->tina)->post("/api/lessons/{$lesson}/video", ['video' => UploadedFile::fake()->create('a.mp4', 512, 'video/mp4')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('lessonId', $lesson);
        $this->assertNotNull(DB::table('lessons')->where('id', $lesson)->value('lesson_video_url'));
    }

    // The migration's backfill, run again on rows as a database deployed before started_at existed has them.
    public function test_backfill_stamps_years_that_already_started_and_only_those(): void
    {
        $migration = require database_path('migrations/2026_10_19_000100_add_started_at_to_school_years.php');
        $year = fn (string $name, string $status = 'completed') => SchoolYear::create(['name' => $name, 'start_date' => substr($name, 0, 4).'-06-01', 'end_date' => substr($name, 5).'-03-31', 'status' => $status]);
        $quarter = fn (SchoolYear $y, string $status) => DB::table('quarters')->insertGetId(['school_year_id' => $y->id, 'quarter_number' => 1, 'name' => 'Q1', 'start_date' => $y->start_date, 'end_date' => $y->start_date->copy()->addMonths(2), 'status' => $status]);

        // The active year: Q1 open for classes.
        DB::table('quarters')->where(['school_year_id' => $this->year->id, 'quarter_number' => 1])->update(['status' => 'active']);
        // A year whose only sign is attendance, with every quarter upcoming.
        $attended = $year('2024-2025');
        $quarter($attended, 'upcoming');
        $section = DB::table('sections')->insertGetId(['school_year_id' => $attended->id, 'teacher_id' => $this->tina->id, 'name' => 'Hope', 'grade_level' => 'Kindergarten']);
        $enrollment = DB::table('learner_school_years')->insertGetId([
            'learner_id' => DB::table('learners')->insertGetId(['first_name' => 'Old', 'last_name' => 'Timer', 'birth_date' => '2019-01-01']),
            'school_year_id' => $attended->id, 'section_id' => $section, 'enrolled_on' => '2024-06-01', 'status' => 'completed',
        ]);
        DB::table('attendance')->insert(['enrollment_id' => $enrollment, 'attendance_date' => '2024-06-03', 'status' => 'present', 'recorded_by' => $this->tina->id, 'recorded_at' => '2024-06-03 08:00:00+08']);
        // A year switched away from: its quarter was put back to upcoming, but a class was held.
        $switched = $year('2023-2024');
        $sq = $quarter($switched, 'upcoming');
        $ss = DB::table('sections')->insertGetId(['school_year_id' => $switched->id, 'teacher_id' => $this->tina->id, 'name' => 'Hope', 'grade_level' => 'Kindergarten']);
        DB::table('classroom_sessions')->insert([
            'section_module_id' => DB::table('section_modules')->insertGetId(['section_id' => $ss, 'module_id' => DB::table('modules')->value('id'), 'teacher_id' => $this->tina->id]),
            'quarter_id' => $sq, 'teacher_id' => $this->tina->id, 'session_date' => '2023-06-05', 'planned_start_time' => '08:00', 'planned_end_time' => '09:00',
            'started_at' => '2023-06-05 08:00:00+08', 'status' => 'completed', 'ended_at' => '2023-06-05 09:00:00+08',
        ]);
        // Never started: upcoming quarter, no records.
        $untouched = $year('2028-2029', 'upcoming');
        $quarter($untouched, 'upcoming');

        $before = collect(['quarters', 'attendance', 'classroom_sessions', 'learner_school_years', 'sections'])
            ->mapWithKeys(fn (string $t) => [$t => DB::table($t)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all()])->all();
        $yearsBefore = DB::table('school_years')->orderBy('id')->get(['id', 'name', 'status', 'start_date', 'end_date', 'updated_at'])->map(fn ($r) => (array) $r)->all();
        $migration->down();
        $migration->up();

        $stamps = DB::table('school_years')->pluck('started_at', 'name');
        $this->assertNotNull($stamps['2026-2027']);
        $this->assertSame('2024-06-03 08:00:00+08', $stamps['2024-2025']);
        $this->assertSame('2023-06-05 08:00:00+08', $stamps['2023-2024']);
        $this->assertNull($stamps['2028-2029']);

        // Only started_at was written.
        $this->assertSame($before, collect(array_keys($before))->mapWithKeys(fn (string $t) => [$t => DB::table($t)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all()])->all());
        $this->assertSame($yearsBefore, DB::table('school_years')->orderBy('id')->get(['id', 'name', 'status', 'start_date', 'end_date', 'updated_at'])->map(fn ($r) => (array) $r)->all());
        // Running it again keeps the stamps.
        $migration->backfill();
        $this->assertEquals($stamps, DB::table('school_years')->pluck('started_at', 'name'));
    }
}

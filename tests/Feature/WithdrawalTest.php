<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\LearnerSchoolYear;
use App\Models\SchoolYear;
use App\Models\Teacher;
use App\Support\ClassSession;
use Database\Seeders\CurriculumSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class WithdrawalTest extends TestCase
{
    use RefreshDatabase;

    private Teacher $tina;

    private SchoolYear $active;

    private SchoolYear $past;

    /** @var array<string, int> section name => id, active year */
    private array $sections;

    /** @var array<int, int> checklist item id per quarter number */
    private array $items = [];

    private Learner $ana;

    private LearnerSchoolYear $anaNow;

    private Learner $ben;

    private int $lesson;

    protected function setUp(): void
    {
        parent::setUp();
        // Every date rule reads the Manila calendar day, so the clock is fixed inside the active year.
        $this->travelTo(Carbon::parse('2026-10-30 10:00:00', 'Asia/Manila'));
        $this->seed(CurriculumSeeder::class);
        $this->tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);

        $this->past = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-06-01', 'end_date' => '2026-03-31', 'status' => 'completed']);
        $this->active = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        foreach ([$this->past, $this->active] as $year) {
            $start = (int) substr($year->name, 0, 4);
            foreach ([1 => ['06-01', '08-31'], 2 => ['09-01', '10-31'], 3 => ['11-01', '12-31']] as $n => [$from, $to]) {
                DB::table('quarters')->insert(['school_year_id' => $year->id, 'quarter_number' => $n, 'name' => "Q{$n}", 'start_date' => "{$start}-{$from}", 'end_date' => "{$start}-{$to}", 'status' => $year->is($this->active) && $n === 1 ? 'active' : 'upcoming']);
            }
            DB::table('quarters')->insert(['school_year_id' => $year->id, 'quarter_number' => 4, 'name' => 'Q4', 'start_date' => ($start + 1).'-01-01', 'end_date' => ($start + 1).'-03-31', 'status' => 'upcoming']);
        }
        $this->setUpSections($this->tina, ['Joy'], $this->past);
        $this->sections = $this->setUpSections($this->tina);

        $domain = DB::table('developmental_domains')->value('id');
        foreach ([1, 2, 3, 4] as $q) {
            $this->items[$q] = DB::table('developmental_checklist_items')->insertGetId(['domain_id' => $domain, 'item' => "Skill Q{$q}", 'grade_level' => 'Kindergarten', 'quarter_number' => $q, 'display_order' => 1]);
        }
        $readingDomain = DB::table('module_domains')->where('module_id', $this->reading())->value('id');
        $this->lesson = DB::table('lessons')->insertGetId(['module_domain_id' => $readingDomain, 'title' => 'Letter B', 'quarter_number' => 1, 'week_number' => 1, 'day_of_week' => 1, 'sequence_order' => 1]);

        [$this->ana, $this->anaNow] = $this->enrolled('Ana');
        [$this->ben] = $this->enrolled('Ben');
    }

    /** @return array{Learner, LearnerSchoolYear} a learner enrolled in this year's Joy since June 1 */
    private function enrolled(string $first): array
    {
        // Five years old on the fixed date, inside the Add Learner age window.
        $learner = Learner::create(['first_name' => $first, 'last_name' => 'Cruz', 'birth_date' => '2021-09-30', 'gender' => 'Female']);
        $enrollment = $learner->enrollments()->create(['school_year_id' => $this->active->id, 'section_id' => $this->sections['Joy'], 'enrolled_on' => '2026-06-01', 'status' => 'active']);

        return [$learner, $enrollment];
    }

    private function reading(): int
    {
        return DB::table('modules')->where('code', 'reading')->value('id');
    }

    private function quarterId(SchoolYear $year, int $n): int
    {
        return DB::table('quarters')->where(['school_year_id' => $year->id, 'quarter_number' => $n])->value('id');
    }

    private function sectionModule(int $sectionId): int
    {
        return DB::table('section_modules')->where(['section_id' => $sectionId, 'module_id' => $this->reading()])->value('id');
    }

    private function withdraw(Learner $learner, array $body = [])
    {
        return $this->actingAs($this->tina)->postJson("/api/learners/{$learner->id}/withdraw", [...['withdrawnOn' => '2026-10-20', 'reason' => 'Moved away'], ...$body]);
    }

    private function activity(): int
    {
        $id = DB::table('activities')->insertGetId(['lesson_id' => $this->lesson, 'activity_code' => 'RD-Q1W1-01', 'name' => 'Game', 'instructions' => '-', 'activity_format' => 'digital', 'sequence_order' => 1]);
        DB::table('digital_activity_details')->insert(['activity_id' => $id, 'game_type' => 'tap_choice', 'max_score' => 2]);

        return $id;
    }

    // A finished class on $date with the enrollment on its roster.
    private function rosterOn(int $enrollmentId, string $date): void
    {
        $session = DB::table('classroom_sessions')->insertGetId([
            'section_module_id' => $this->sectionModule($this->sections['Joy']), 'quarter_id' => $this->quarterId($this->active, 1), 'teacher_id' => $this->tina->id,
            'session_date' => $date, 'planned_start_time' => '08:00', 'planned_end_time' => '09:00', 'status' => 'completed',
        ]);
        DB::table('classroom_session_learners')->insert(['classroom_session_id' => $session, 'enrollment_id' => $enrollmentId, 'attendance_status' => 'unmarked', 'participation_status' => 'not_joined']);
    }

    // An assignment of a game to the enrollment, with an attempt when $attempt times are given.
    private function assignment(int $enrollmentId, array $assignment, ?array $attempt = null): void
    {
        $activity = DB::table('activities')->where('lesson_id', $this->lesson)->value('id') ?? $this->activity();
        $id = DB::table('learner_activity_assignments')->insertGetId([
            'enrollment_id' => $enrollmentId, 'activity_id' => $activity, 'quarter_id' => $this->quarterId($this->active, 1), 'teacher_id' => $this->tina->id,
            'status' => isset($assignment['completed_at']) ? 'completed' : 'assigned', ...$assignment,
        ]);
        if ($attempt) {
            DB::table('learner_activity_attempts')->insert(['assignment_id' => $id, 'attempt_number' => 1, 'status' => 'evaluated', 'practical_rating' => 4, 'evaluated_by' => $this->tina->id, ...$attempt]);
        }
    }

    private function rate(int $enrollmentId, int $quarter, string $assessedAt, string $rating = 'achieved', ?SchoolYear $year = null): void
    {
        DB::table('learner_developmental_checklists')->insert([
            'enrollment_id' => $enrollmentId, 'checklist_item_id' => $this->items[$quarter], 'quarter_id' => $this->quarterId($year ?? $this->active, $quarter),
            'teacher_id' => $this->tina->id, 'rating' => $rating, 'assessed_at' => $assessedAt,
        ]);
    }

    /** Every row tied to these enrollments, as stored. */
    private function records(array $enrollmentIds): array
    {
        $assignments = DB::table('learner_activity_assignments')->whereIn('enrollment_id', $enrollmentIds)->pluck('id');

        return collect(['attendance', 'learner_developmental_checklists', 'learner_activity_assignments', 'classroom_session_learners', 'quarterly_progress_reports'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->whereIn('enrollment_id', $enrollmentIds)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all()])
            ->put('learner_activity_attempts', DB::table('learner_activity_attempts')->whereIn('assignment_id', $assignments)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all())
            ->all();
    }

    private function row(string $table, int $id): array
    {
        return (array) DB::table($table)->where('id', $id)->first();
    }

    public function test_withdraws_the_active_year_enrollment_and_keeps_every_record(): void
    {
        // Last year's enrollment stays exactly as it was, status and all. It is made first, as in real use, so this
        // year's is the latest one the profile reads.
        $pastJoy = DB::table('sections')->where('school_year_id', $this->past->id)->value('id');
        $this->anaNow->delete();
        $last = $this->ana->enrollments()->create(['school_year_id' => $this->past->id, 'section_id' => $pastJoy, 'enrolled_on' => '2025-06-01', 'status' => 'active']);
        $this->anaNow = $this->ana->enrollments()->create(['school_year_id' => $this->active->id, 'section_id' => $this->sections['Joy'], 'enrolled_on' => '2026-06-01', 'status' => 'active']);
        $this->rate($last->id, 1, '2025-08-01 08:00:00+08', 'developing', $this->past);
        DB::table('attendance')->insert(['enrollment_id' => $last->id, 'attendance_date' => '2025-06-02', 'status' => 'present', 'recorded_by' => $this->tina->id, 'recorded_at' => '2025-06-02 08:00:00+08']);
        // And this year's own records.
        DB::table('attendance')->insert(['enrollment_id' => $this->anaNow->id, 'attendance_date' => '2026-10-01', 'status' => 'present', 'recorded_by' => $this->tina->id, 'recorded_at' => '2026-10-01 08:00:00+08']);
        $this->rate($this->anaNow->id, 1, '2026-08-01 08:00:00+08');
        $this->rosterOn($this->anaNow->id, '2026-09-01');
        $this->assignment($this->anaNow->id, ['assigned_at' => '2026-09-01 08:00:00+08', 'completed_at' => '2026-09-01 08:30:00+08'], ['started_at' => '2026-09-01 08:05:00+08', 'submitted_at' => '2026-09-01 08:30:00+08', 'evaluated_at' => '2026-09-02 08:00:00+08']);
        DB::table('quarterly_progress_reports')->insert(['enrollment_id' => $this->anaNow->id, 'quarter_id' => $this->quarterId($this->active, 1), 'teacher_id' => $this->tina->id, 'status' => 'draft', 'generated_at' => '2026-09-30 08:00:00+08']);

        $learnerBefore = $this->row('learners', $this->ana->id);
        $recordsBefore = $this->records([$last->id, $this->anaNow->id]);
        $othersBefore = DB::table('learner_school_years')->where('id', '!=', $this->anaNow->id)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

        $this->withdraw($this->ana, ['reason' => '  Moved away  '])->assertOk()->assertJson(['learnerId' => $this->ana->id, 'enrollmentId' => $this->anaNow->id]);

        $now = $this->anaNow->fresh();
        $this->assertSame(['withdrawn', '2026-10-20', 'Moved away', $this->sections['Joy'], $this->active->id], [$now->status, $now->withdrawn_on->toDateString(), $now->withdrawal_reason, $now->section_id, $now->school_year_id]);
        $this->assertSame($learnerBefore, $this->row('learners', $this->ana->id));
        $this->assertTrue($this->ana->fresh()->is_active);
        $this->assertSame(2, Learner::count());
        $this->assertSame($recordsBefore, $this->records([$last->id, $this->anaNow->id]));
        $this->assertSame($othersBefore, DB::table('learner_school_years')->where('id', '!=', $this->anaNow->id)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all());

        // The profile still shows this year's history, now marked withdrawn.
        $profile = $this->actingAs($this->tina)->getJson("/api/learners/{$this->ana->id}")->assertOk()
            ->assertJsonPath('enrollmentStatus', 'withdrawn')->assertJsonPath('withdrawnOn', '2026-10-20')
            ->assertJsonPath('withdrawalReason', 'Moved away')->assertJsonPath('canWithdraw', false);
        $this->assertSame(['2026-10-01'], array_column($profile->json('attendances'), 'date'));
        $this->assertSame('achieved', collect($profile->json('progress'))->firstWhere('itemId', $this->items[1])['rating']);
        $this->actingAs($this->tina)->getJson("/api/learners/{$this->ben->id}")->assertJsonPath('canWithdraw', true)->assertJsonPath('withdrawnOn', null);
    }

    public function test_only_the_learners_teacher_may_withdraw_an_active_enrollment(): void
    {
        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        $this->postJson("/api/learners/{$this->ana->id}/withdraw", ['withdrawnOn' => '2026-10-20'])->assertUnauthorized();
        $this->actingAs($omar)->postJson("/api/learners/{$this->ana->id}/withdraw", ['withdrawnOn' => '2026-10-20'])->assertNotFound();

        $this->withdraw($this->ana, ['withdrawnOn' => null])->assertStatus(422)->assertJsonValidationErrors('withdrawnOn');
        $this->withdraw($this->ana, ['withdrawnOn' => '20/10/2026'])->assertStatus(422)->assertJsonValidationErrors('withdrawnOn');
        $this->withdraw($this->ana, ['reason' => str_repeat('a', 256)])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->assertSame('active', $this->anaNow->fresh()->status);

        // No reason is fine; a second withdrawal is refused and changes nothing.
        $this->withdraw($this->ana, ['reason' => null])->assertOk();
        $this->assertNull($this->anaNow->fresh()->withdrawal_reason);
        $row = $this->row('learner_school_years', $this->anaNow->id);
        $this->withdraw($this->ana, ['withdrawnOn' => '2026-10-25'])->assertStatus(422)->assertJsonValidationErrors(['learner' => 'Only a learner currently enrolled']);
        $this->assertSame($row, $this->row('learner_school_years', $this->anaNow->id));

        // A learner of last year only isn't on this year's list, so there is nothing to withdraw.
        $cara = Learner::create(['first_name' => 'Cara', 'last_name' => 'Cruz', 'birth_date' => '2021-09-30', 'gender' => 'Female']);
        $cara->enrollments()->create(['school_year_id' => $this->past->id, 'section_id' => DB::table('sections')->where('school_year_id', $this->past->id)->value('id'), 'enrolled_on' => '2025-06-01', 'status' => 'active']);
        $this->withdraw($cara)->assertNotFound();
        $this->assertSame('active', $cara->enrollments()->value('status'));
    }

    public function test_the_date_stays_within_the_enrollment_and_the_school_year(): void
    {
        $this->withdraw($this->ana, ['withdrawnOn' => '2026-10-31'])->assertStatus(422)->assertJsonValidationErrors(['withdrawnOn' => "can't be in the future"]);
        $this->anaNow->update(['enrolled_on' => '2026-07-01']);
        $this->withdraw($this->ana, ['withdrawnOn' => '2026-06-30'])->assertStatus(422)->assertJsonValidationErrors(['withdrawnOn' => 'before the learner was enrolled (Jul 1, 2026)']);
        $this->anaNow->update(['enrolled_on' => '2026-05-20']);
        $this->withdraw($this->ana, ['withdrawnOn' => '2026-05-25'])->assertStatus(422)->assertJsonValidationErrors(['withdrawnOn' => 'before SY 2026-2027 starts']);
        $this->active->update(['end_date' => '2026-10-15']);
        $this->withdraw($this->ana, ['withdrawnOn' => '2026-10-20'])->assertStatus(422)->assertJsonValidationErrors(['withdrawnOn' => 'after SY 2026-2027 ends']);
        $this->assertSame('active', $this->anaNow->fresh()->status);

        $this->active->update(['end_date' => '2027-03-31']);
        $this->withdraw($this->ana, ['withdrawnOn' => '2026-10-30'])->assertOk();
    }

    public function test_a_record_dated_after_the_withdrawal_day_refuses_it(): void
    {
        $teacher = $this->tina->id;
        // Each source of records, dated Oct 21 in Manila; the withdrawal asks for Oct 20.
        $sources = [
            'attendance day' => fn (int $e) => DB::table('attendance')->insert(['enrollment_id' => $e, 'attendance_date' => '2026-10-21', 'status' => 'absent', 'recorded_by' => $teacher, 'recorded_at' => '2026-10-19 08:00:00+08']),
            'class roster day' => fn (int $e) => $this->rosterOn($e, '2026-10-21'),
            'checklist rating' => fn (int $e) => $this->rate($e, 2, '2026-10-21 09:00:00+08'),
            // 23:30 UTC on Oct 20 is already 07:30 on Oct 21 in Manila.
            'checklist rating late UTC' => fn (int $e) => $this->rate($e, 2, '2026-10-20 23:30:00+00'),
            'domain assessment' => fn (int $e) => DB::table('learner_developmental_assessments')->insert(['enrollment_id' => $e, 'quarter_id' => $this->quarterId($this->active, 2), 'domain_id' => DB::table('developmental_domains')->value('id'), 'teacher_id' => $teacher, 'assessed_at' => '2026-10-21 09:00:00+08']),
            'assignment given' => fn (int $e) => $this->assignment($e, ['assigned_at' => '2026-10-21 09:00:00+08']),
            'assignment finished' => fn (int $e) => $this->assignment($e, ['assigned_at' => '2026-10-01 09:00:00+08', 'completed_at' => '2026-10-21 09:00:00+08']),
            'attempt rated' => fn (int $e) => $this->assignment($e, ['assigned_at' => '2026-10-01 09:00:00+08'], ['started_at' => '2026-10-01 09:00:00+08', 'evaluated_at' => '2026-10-21 09:00:00+08']),
        ];
        foreach ($sources as $source => $record) {
            [$learner, $enrollment] = $this->enrolled(str_replace(' ', '', ucwords($source)));
            $record($enrollment->id);

            $this->withdraw($learner)->assertStatus(422)->assertJsonValidationErrors(['withdrawnOn' => 'records up to Oct 21, 2026']);
            $this->assertSame('active', $enrollment->fresh()->status, $source);
            // The day of the last record itself is allowed.
            $this->withdraw($learner, ['withdrawnOn' => '2026-10-21'])->assertOk();
        }

        // 15:30 UTC on Oct 20 is 23:30 the same day in Manila, so Oct 20 is fine.
        [$learner, $enrollment] = $this->enrolled('LateEvening');
        $this->rate($enrollment->id, 2, '2026-10-20 15:30:00+00');
        $this->withdraw($learner)->assertOk();
    }

    public function test_a_running_class_refuses_withdrawal_and_a_later_start_leaves_the_learner_out(): void
    {
        $game = $this->activity();
        $joy = $this->sections['Joy'];
        $start = fn () => $this->actingAs($this->tina)->postJson("/api/sections/{$joy}/classroom/start", ['moduleId' => $this->reading(), 'lessonId' => $this->lesson, 'activityId' => $game]);
        // Withdrawn before any class: never marked today, yet no class waits on her.
        [$dina, $dinaNow] = $this->enrolled('Dina');
        $this->withdraw($dina)->assertOk();
        $this->actingAs($this->tina)->putJson("/api/sections/{$joy}/attendance", ['entries' => [
            ['learnerId' => $this->ana->id, 'status' => 'PRESENT'], ['learnerId' => $this->ben->id, 'status' => 'PRESENT'],
        ]])->assertOk();

        // The class started first, so the withdrawal waits for it to end.
        $first = $start()->assertOk()->json('classroomSessionId');
        $this->assertSame(0, DB::table('classroom_session_learners')->where(['classroom_session_id' => $first, 'enrollment_id' => $dinaNow->id])->count());
        $this->withdraw($this->ana, ['withdrawnOn' => '2026-10-30'])->assertStatus(422)->assertJsonValidationErrors(['learner' => 'A class is running in this section']);
        $this->assertSame('active', $this->anaNow->fresh()->status);
        $this->actingAs($this->tina)->postJson("/api/sections/{$joy}/classroom/end")->assertOk();

        // Withdrawn first: the next class neither waits on her attendance nor puts her on its roster.
        $this->withdraw($this->ana, ['withdrawnOn' => '2026-10-30'])->assertOk();
        $second = $start()->assertOk()->json('classroomSessionId');
        $this->assertNotSame($first, $second);
        $this->assertSame([$this->ben->enrollments()->value('id')], DB::table('classroom_session_learners')->where('classroom_session_id', $second)->pluck('enrollment_id')->all());
        $this->assertSame([$this->ben->id], array_column($this->actingAs($this->tina)->getJson("/api/sections/{$joy}/classroom")->assertOk()->json('learners'), 'learnerId'));
    }

    public function test_a_withdrawn_learner_leaves_the_active_lists_and_counts(): void
    {
        $this->withdraw($this->ana)->assertOk();
        $joy = $this->sections['Joy'];

        $this->assertSame(1, collect($this->actingAs($this->tina)->getJson('/api/sections')->assertOk()->json())->firstWhere('sectionId', $joy)['learnerCount']);
        $this->assertSame([$this->ben->id], array_column($this->actingAs($this->tina)->getJson('/api/learners')->assertOk()->json(), 'learnerId'));
        $this->assertSame([$this->ben->id], array_column($this->actingAs($this->tina)->getJson("/api/learners?section={$joy}")->assertOk()->json(), 'learnerId'));

        // The class list asks for them too, each with its status.
        $all = collect($this->actingAs($this->tina)->getJson('/api/learners?withWithdrawn=1')->assertOk()->json())->pluck('enrollmentStatus', 'learnerId')->all();
        $this->assertSame([$this->ana->id => 'withdrawn', $this->ben->id => 'active'], $all);
    }

    public function test_a_withdrawn_learner_gets_no_new_attendance_ratings_or_reports(): void
    {
        $joy = $this->sections['Joy'];
        $game = $this->activity();
        DB::table('attendance')->insert(['enrollment_id' => $this->anaNow->id, 'attendance_date' => '2026-10-19', 'status' => 'absent', 'recorded_by' => $this->tina->id, 'recorded_at' => '2026-10-19 08:00:00+08']);
        $report = $this->actingAs($this->tina)->postJson("/api/sections/{$joy}/progress-reports", ['enrollmentId' => $this->anaNow->id, 'quarter' => 1])->assertCreated()->json();
        $this->withdraw($this->ana)->assertOk();
        $before = $this->records([$this->anaNow->id]);

        $this->actingAs($this->tina)->putJson("/api/sections/{$joy}/attendance", ['entries' => [['learnerId' => $this->ana->id, 'status' => 'PRESENT']]])
            ->assertStatus(422)->assertJsonValidationErrors('entries.0.learnerId');
        $this->actingAs($this->tina)->putJson("/api/sections/{$joy}/checklist/ratings", ['enrollmentId' => $this->anaNow->id, 'quarter' => 1, 'itemId' => $this->items[1], 'rating' => 'achieved'])
            ->assertStatus(422)->assertJsonValidationErrors('enrollmentId');
        $this->actingAs($this->tina)->putJson("/api/sections/{$joy}/classroom/practical-rating", ['learnerId' => $this->ana->id, 'activityId' => $game, 'result' => 4])
            ->assertStatus(422)->assertJsonValidationErrors('learnerId');
        $this->actingAs($this->tina)->putJson("/api/learners/{$this->ana->id}/excuse-slip", ['date' => '2026-10-19', 'reason' => 'Fever'])
            ->assertStatus(422)->assertJsonValidationErrors(['date' => 'withdrawn']);
        $this->actingAs($this->tina)->postJson("/api/sections/{$joy}/progress-reports", ['enrollmentId' => $this->anaNow->id, 'quarter' => 2])
            ->assertStatus(422)->assertJsonValidationErrors('enrollmentId');
        $this->assertSame($before, $this->records([$this->anaNow->id]));

        // The draft made before the withdrawal can still be finished.
        $this->actingAs($this->tina)->putJson("/api/progress-reports/{$report['reportId']}", [
            'updatedAt' => $report['updatedAt'],
            'rows' => collect($report['rows'])->map(fn ($r) => ['moduleDomainId' => $r['moduleDomainId'], 'developmentObserved' => 'Observed.', 'commentsRemarks' => $r['commentsRemarks']])->all(),
        ])->assertOk();

        // The re-check those writes make under the enrollment lock, for a withdrawal that commits after their first check.
        DB::transaction(function () {
            $ben = $this->ben->enrollments()->value('id');
            $this->assertFalse(ClassSession::lockActive([$this->anaNow->id]));
            $this->assertTrue(ClassSession::lockActive([$ben]));
            $this->assertFalse(ClassSession::lockActive([$ben, $this->anaNow->id]));
            $this->assertTrue(ClassSession::lockActive([$ben, $ben]));
        });
    }

    public function test_withdrawal_never_counts_as_ready_for_grade_1_or_promoted(): void
    {
        foreach ([1, 2, 3, 4] as $q) {
            $this->rate($this->anaNow->id, $q, '2026-10-01 08:00:00+08');
        }
        $this->assertSame([$this->ana->id], array_column($this->actingAs($this->tina)->getJson('/api/learners?ready=1')->assertOk()->json(), 'learnerId'));

        $this->withdraw($this->ana)->assertOk();

        $this->actingAs($this->tina)->getJson('/api/learners?ready=1')->assertOk()->assertJsonCount(0);
        // Ending the year changes no status, and the promotion report (completed years only) leaves her out.
        $this->active->update(['status' => 'completed']);
        $this->actingAs($this->tina)->getJson("/api/promotions/{$this->active->id}")->assertOk()->assertJsonCount(0, 'learners');
        $this->assertSame(['withdrawn'], $this->ana->enrollments()->pluck('status')->all());
        $this->assertSame(['active'], $this->ben->enrollments()->pluck('status')->all());
    }

    public function test_a_withdrawn_learner_may_re_enroll_in_a_later_year(): void
    {
        $this->withdraw($this->ana)->assertOk();
        $withdrawn = $this->row('learner_school_years', $this->anaNow->id);

        $this->active->update(['status' => 'completed']);
        $next = SchoolYear::create(['name' => '2027-2028', 'start_date' => '2027-06-01', 'end_date' => '2028-03-31', 'status' => 'active']);
        $this->setUpSections($this->tina, ['Joy', 'Faith'], $next);

        $res = $this->actingAs($this->tina)->postJson("/api/learners/{$this->ana->id}/enrollments", ['gradeLevel' => 'Kindergarten', 'section' => 'Faith', 'schoolYear' => '2027-2028'])->assertCreated();
        $this->assertSame($this->ana->id, $res->json('learnerId'));
        $this->assertSame(['withdrawn', 'active'], $this->ana->enrollments()->orderBy('id')->pluck('status')->all());
        $this->assertSame($withdrawn, $this->row('learner_school_years', $this->anaNow->id));
    }

    public function test_absences_never_withdraw_a_learner(): void
    {
        foreach (range(1, 30) as $day) {
            DB::table('attendance')->insert(['enrollment_id' => $this->anaNow->id, 'attendance_date' => sprintf('2026-09-%02d', $day), 'status' => 'absent', 'recorded_by' => $this->tina->id, 'recorded_at' => now()]);
        }
        $this->actingAs($this->tina)->putJson("/api/sections/{$this->sections['Joy']}/attendance", ['entries' => [['learnerId' => $this->ana->id, 'status' => 'ABSENT']]])->assertOk();
        $this->actingAs($this->tina)->getJson("/api/learners/{$this->ana->id}")->assertOk()->assertJsonPath('enrollmentStatus', 'active');

        $this->assertSame(['active'], $this->ana->enrollments()->pluck('status')->all());
        $this->assertNull($this->anaNow->fresh()->withdrawn_on);
    }

    public function test_the_migration_stops_on_withdrawn_rows_without_a_date_and_then_enforces_one(): void
    {
        $migration = require database_path('migrations/2026_10_30_000100_add_withdrawal_to_learner_school_years.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('learner_school_years', 'withdrawn_on'));
        DB::table('learner_school_years')->where('id', $this->anaNow->id)->update(['status' => 'withdrawn']);

        try {
            $migration->up();
            $this->fail('The migration ran over a withdrawn row with no date.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString("ids {$this->anaNow->id}.", $e->getMessage());
        }
        // Stopped before any change: no column, and the row is untouched.
        $this->assertFalse(Schema::hasColumn('learner_school_years', 'withdrawn_on'));
        $this->assertSame('withdrawn', DB::table('learner_school_years')->where('id', $this->anaNow->id)->value('status'));

        DB::table('learner_school_years')->where('id', $this->anaNow->id)->update(['status' => 'active']);
        $migration->up();
        $rejects = function (array $change) {
            try {
                DB::transaction(fn () => DB::table('learner_school_years')->where('id', $this->anaNow->id)->update($change));
                $this->fail('Accepted '.json_encode($change));
            } catch (QueryException $e) {
                $this->assertStringContainsString('check', $e->getMessage());
            }
        };
        $rejects(['status' => 'withdrawn']);
        $rejects(['withdrawn_on' => '2026-10-20']);
        $rejects(['status' => 'withdrawn', 'withdrawn_on' => '2026-05-31']);
        $rejects(['withdrawal_reason' => 'Moved away']);
        DB::table('learner_school_years')->where('id', $this->anaNow->id)->update(['status' => 'withdrawn', 'withdrawn_on' => '2026-10-20', 'withdrawal_reason' => 'Moved away']);
        $this->assertSame('withdrawn', $this->anaNow->fresh()->status);
    }
}

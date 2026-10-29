<?php

namespace Tests\Feature;

use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Teacher;
use Database\Seeders\CurriculumSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SectionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function year(string $name, string $start, string $end, string $status = 'upcoming'): SchoolYear
    {
        return SchoolYear::create(['name' => $name, 'start_date' => $start, 'end_date' => $end, 'status' => $status]);
    }

    /** @return array{0: string, 1: string} the start/end time of a section's Language class */
    private function languageTimes(int $sectionId): array
    {
        return array_values((array) DB::table('class_schedules')
            ->join('section_modules', 'section_modules.id', '=', 'class_schedules.section_module_id')
            ->join('modules', 'modules.id', '=', 'section_modules.module_id')
            ->where(['section_modules.section_id' => $sectionId, 'modules.code' => 'language'])
            ->first(['start_time', 'end_time']));
    }

    public function test_sections_cannot_be_created_through_the_api_or_by_listing(): void
    {
        $this->seed(CurriculumSeeder::class);
        $this->year('2026-2027', '2026-06-01', '2027-03-31', 'active');
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);

        $this->getJson('/api/sections')->assertUnauthorized();
        $this->actingAs($tina)->postJson('/api/sections', ['sectionName' => 'Joy'])->assertMethodNotAllowed();
        // Listing never sets anything up, even for an active year with no sections.
        $this->actingAs($tina)->getJson('/api/sections')->assertOk()->assertJsonCount(0);
        $this->assertSame(0, DB::table('sections')->count());
    }

    public function test_activation_sets_up_only_the_picked_sections(): void
    {
        $this->seed(CurriculumSeeder::class);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        $joyOnly = $this->year('2026-2027', '2026-06-01', '2027-03-31');
        $faithOnly = $this->year('2027-2028', '2027-06-01', '2028-03-31');
        $both = $this->year('2028-2029', '2028-06-01', '2029-03-31');

        $this->actingAs($tina)->patchJson("/api/school-years/{$joyOnly->id}/activate", ['sections' => ['joy']])
            ->assertOk()->assertJson(['status' => 'active', 'sections' => ['Joy']]);
        $sections = $this->actingAs($tina)->getJson('/api/sections')->assertOk()
            ->assertExactJson([['sectionId' => DB::table('sections')->value('id'), 'gradeLevel' => 'Kindergarten', 'sectionName' => 'Joy', 'session' => 'morning', 'canRename' => true, 'renameReason' => null, 'learnerCount' => 0, 'capacity' => Section::CAPACITY]])
            ->json();
        // All five modules, each with its morning timetable: Reading, Language, Writing on M/W/F plus MAPEH, Math on T/Th.
        $this->assertSame(5, DB::table('section_modules')->count());
        $this->assertSame(5, DB::table('class_schedules')->count());
        $this->assertSame(13, DB::table('schedule_days')->count());
        $this->assertSame(['09:15:00', '10:00:00'], $this->languageTimes($sections[0]['sectionId']));
        $this->assertSame(['2026-06-01'], DB::table('class_schedules')->distinct()->pluck('effective_from')->all());
        // Owned by the teacher who activated the year, module links included.
        $this->assertSame([$tina->id], DB::table('section_modules')->distinct()->pluck('teacher_id')->all());
        $this->actingAs($omar)->getJson('/api/sections')->assertOk()->assertJsonCount(0);

        $this->actingAs($tina)->patchJson("/api/school-years/{$faithOnly->id}/activate", ['sections' => ['Faith']])
            ->assertOk()->assertJson(['sections' => ['Faith']]);
        $faith = $this->actingAs($tina)->getJson('/api/sections')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.sectionName', 'Faith')->assertJsonPath('0.session', 'afternoon')->json('0.sectionId');
        $this->assertSame(['14:15:00', '15:00:00'], $this->languageTimes($faith));

        $this->actingAs($tina)->patchJson("/api/school-years/{$both->id}/activate", ['sections' => ['Faith', 'Joy']])
            ->assertOk()->assertJson(['sections' => ['Joy', 'Faith']]);
        $this->actingAs($tina)->getJson('/api/sections')->assertOk()->assertJsonCount(2)
            ->assertJsonPath('0.sectionName', 'Joy')->assertJsonPath('1.sectionName', 'Faith');

        // Each year kept exactly what it picked, and nothing came with learners.
        $names = fn (SchoolYear $y) => DB::table('sections')->where('school_year_id', $y->id)->orderBy('name')->pluck('name')->all();
        $this->assertSame([['Joy'], ['Faith'], ['Faith', 'Joy']], [$names($joyOnly), $names($faithOnly), $names($both)]);
        $this->assertSame(0, DB::table('learner_school_years')->count());
    }

    public function test_a_years_sections_are_set_once_and_kept_with_their_records(): void
    {
        $this->seed(CurriculumSeeder::class);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $current = $this->year('2026-2027', '2026-06-01', '2027-03-31');
        $next = $this->year('2027-2028', '2027-06-01', '2028-03-31');
        $this->actingAs($tina)->patchJson("/api/school-years/{$current->id}/activate", ['sections' => ['Joy']])->assertOk();
        $joy = DB::table('sections')->value('id');
        $learner = DB::table('learners')->insertGetId(['first_name' => 'Ana', 'last_name' => 'Cruz', 'birth_date' => '2021-01-01']);
        DB::table('learner_school_years')->insert(['learner_id' => $learner, 'school_year_id' => $current->id, 'section_id' => $joy, 'enrolled_on' => '2026-06-01', 'status' => 'active']);
        $snapshot = fn () => [
            DB::table('sections')->orderBy('id')->get()->all(),
            DB::table('section_modules')->orderBy('id')->get()->all(),
            DB::table('class_schedules')->orderBy('id')->get()->all(),
            DB::table('schedule_days')->orderBy('id')->get()->all(),
            DB::table('learner_school_years')->get()->all(),
        ];
        $before = $snapshot();

        // Faith can't be added to a year that's already set up, even by re-activating it.
        $this->actingAs($tina)->patchJson("/api/school-years/{$current->id}/activate", ['sections' => ['Joy', 'Faith']])
            ->assertStatus(422)->assertJsonValidationErrors(['sections' => "This school year's sections are already set up and can't be changed."]);
        $this->actingAs($tina)->patchJson("/api/school-years/{$current->id}/activate")->assertOk()->assertJson(['sections' => ['Joy']]);
        $this->assertEquals($before, $snapshot());

        // Moving to the next year and back leaves the first year's Joy, its learner and timetable alone.
        $this->actingAs($tina)->patchJson("/api/school-years/{$next->id}/activate", ['sections' => ['Joy', 'Faith']])->assertOk();
        $this->actingAs($tina)->patchJson("/api/school-years/{$current->id}/activate")->assertOk();
        $this->assertEquals($before[4], DB::table('learner_school_years')->get()->all());
        $this->assertEquals($before[0], DB::table('sections')->where('school_year_id', $current->id)->orderBy('id')->get()->all());
        $this->actingAs($tina)->getJson('/api/sections')->assertOk()->assertJsonCount(1)->assertJsonPath('0.sectionId', $joy);
    }

    public function test_activation_rejects_anything_but_joy_and_faith(): void
    {
        $this->seed(CurriculumSeeder::class);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $year = $this->year('2026-2027', '2026-06-01', '2027-03-31');

        $payloads = [[], ['sections' => []], ['sections' => 'Joy'], ['sections' => ['Hope']], ['sections' => ['Joy', 'joy']],
            ['sections' => [1]], ['sections' => [['Joy']]], ['sections' => [null]], ['sections' => ['Joy', 'Hope']]];
        foreach ($payloads as $payload) {
            $this->actingAs($tina)->patchJson("/api/school-years/{$year->id}/activate", $payload)->assertStatus(422);
        }
        $this->actingAs($tina)->patchJson("/api/school-years/{$year->id}/activate", ['sections' => ['Joy', 'joy']])
            ->assertJsonValidationErrors('sections.1');
        $this->actingAs($tina)->patchJson("/api/school-years/{$year->id}/activate", ['sections' => [['Joy']]])
            ->assertJsonValidationErrors('sections.0');
        $this->assertSame('upcoming', $year->refresh()->status);
        $this->assertSame(0, DB::table('sections')->count());
    }

    private function enrollIn(int $sectionId, string $status = 'active'): int
    {
        return DB::table('learner_school_years')->insertGetId([
            'learner_id' => DB::table('learners')->insertGetId(['first_name' => 'Ana', 'last_name' => 'Cruz', 'birth_date' => '2021-01-01', 'gender' => 'Female']),
            'school_year_id' => DB::table('sections')->where('id', $sectionId)->value('school_year_id'),
            'section_id' => $sectionId, 'enrolled_on' => '2026-06-01', 'status' => $status,
            'withdrawn_on' => $status === 'withdrawn' ? '2026-06-01' : null,
        ]);
    }

    public function test_old_sections_get_a_joy_or_faith_identity_only_when_their_name_is_unambiguous(): void
    {
        $year = $this->year('2026-2027', '2026-06-01', '2027-03-31', 'active');
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $row = fn (string $name) => Section::create(['school_year_id' => $year->id, 'teacher_id' => $tina->id, 'grade_level' => 'Kindergarten', 'name' => $name]);
        $hope = $row('Hope');
        $joy = $row(' joy ');
        // Two names that both read as Faith: neither is guessed.
        $faith = $row('Faith');
        $faithToo = $row('faith');

        (require database_path('migrations/2026_10_16_000100_add_choice_to_sections_table.php'))->backfill();

        $this->assertSame([null, 'Joy', null, null], [$hope->refresh()->choice, $joy->refresh()->choice, $faith->refresh()->choice, $faithToo->refresh()->choice]);
        // Joy lists first as the morning section; the others are kept, without a session.
        $this->actingAs($tina)->getJson('/api/sections')->assertOk()
            ->assertJsonPath('0.sectionId', $joy->id)->assertJsonPath('0.session', 'morning')
            ->assertJsonPath('1.session', null)->assertJsonPath('2.session', null)->assertJsonPath('3.session', null);
        $this->actingAs($tina)->getJson('/api/school-years/active')->assertOk()->assertJson(['sections' => ['Joy']]);
        $this->actingAs($tina)->patchJson("/api/school-years/{$year->id}/activate", ['sections' => ['Faith']])
            ->assertStatus(422)->assertJsonValidationErrors('sections');
        $this->assertSame(4, DB::table('sections')->count());
    }

    public function test_an_empty_section_is_renamed_keeping_its_id_identity_session_order_and_timetable(): void
    {
        $this->seed(CurriculumSeeder::class);
        $year = $this->year('2026-2027', '2026-06-01', '2027-03-31', 'active');
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        ['Joy' => $joy, 'Faith' => $faith] = $this->setUpSections($tina, ['Joy', 'Faith'], $year);
        $times = $this->languageTimes($joy);
        $links = DB::table('section_modules')->where('section_id', $joy)->orderBy('id')->pluck('id')->all();

        $this->actingAs($tina)->patchJson("/api/sections/{$joy}", ['sectionName' => '  Sampaguita   Class '])->assertOk()
            ->assertExactJson(['sectionId' => $joy, 'gradeLevel' => 'Kindergarten', 'sectionName' => 'Sampaguita Class', 'session' => 'morning', 'canRename' => true, 'renameReason' => null, 'learnerCount' => 0, 'capacity' => Section::CAPACITY]);

        // Same row, still Joy (morning), listed first, owned by Tina, with the same module links and timetable.
        $this->assertSame(['Joy', $tina->id], [Section::find($joy)->choice, Section::find($joy)->teacher_id]);
        $this->assertSame($links, DB::table('section_modules')->where('section_id', $joy)->orderBy('id')->pluck('id')->all());
        $this->assertSame($times, $this->languageTimes($joy));
        $this->actingAs($tina)->getJson('/api/sections')->assertOk()
            ->assertJsonPath('0.sectionId', $joy)->assertJsonPath('0.sectionName', 'Sampaguita Class')->assertJsonPath('0.session', 'morning')
            ->assertJsonPath('1.sectionId', $faith)->assertJsonPath('1.session', 'afternoon');

        // Activation still sees Joy and Faith as taken: no second Joy, even under the old name.
        $this->actingAs($tina)->getJson('/api/school-years/active')->assertOk()->assertJson(['sections' => ['Joy', 'Faith']]);
        $this->actingAs($tina)->patchJson("/api/school-years/{$year->id}/activate", ['sections' => ['Joy']])
            ->assertStatus(422)->assertJsonValidationErrors('sections');
        // In a savepoint, so the failed insert doesn't abort the rest of the test.
        $this->assertThrows(fn () => DB::transaction(fn () => Section::create(['school_year_id' => $year->id, 'teacher_id' => $tina->id, 'grade_level' => 'Kindergarten', 'name' => 'Joy', 'choice' => 'Joy'])), UniqueConstraintViolationException::class);
        $this->assertSame(2, DB::table('sections')->where('school_year_id', $year->id)->count());

        // Its own name in another case is not a clash.
        $this->actingAs($tina)->patchJson("/api/sections/{$joy}", ['sectionName' => 'SAMPAGUITA CLASS'])->assertOk();
    }

    public function test_any_enrollment_of_any_status_fixes_the_name(): void
    {
        $old = $this->year('2025-2026', '2025-06-01', '2026-03-31', 'completed');
        $year = $this->year('2026-2027', '2026-06-01', '2027-03-31', 'active');
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        ['Joy' => $lastYear] = $this->setUpSections($tina, ['Joy'], $old);
        ['Joy' => $joy] = $this->setUpSections($tina, ['Joy'], $year);
        // A learner in last year's Joy does not lock this year's.
        $this->enrollIn($lastYear, 'completed');
        $this->actingAs($tina)->getJson('/api/sections')->assertOk()->assertJsonPath('0.canRename', true);

        foreach (['active', 'completed', 'transferred', 'withdrawn'] as $status) {
            $enrollment = $this->enrollIn($joy, $status);
            $this->actingAs($tina)->getJson('/api/sections')->assertOk()
                ->assertJsonPath('0.canRename', false)->assertJsonPath('0.renameReason', Section::NAME_LOCKED_MESSAGE);
            $this->actingAs($tina)->patchJson("/api/sections/{$joy}", ['sectionName' => 'Hope'])
                ->assertStatus(422)->assertJsonValidationErrors(['section' => Section::NAME_LOCKED_MESSAGE]);
            DB::table('learner_school_years')->where('id', $enrollment)->delete();
        }
        $this->assertSame('Joy', Section::find($joy)->name);
    }

    public function test_classroom_history_without_learners_fixes_the_name(): void
    {
        $this->seed(CurriculumSeeder::class);
        $year = $this->year('2026-2027', '2026-06-01', '2027-03-31', 'active');
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        ['Joy' => $joy] = $this->setUpSections($tina, ['Joy'], $year);
        $quarter = DB::table('quarters')->insertGetId(['school_year_id' => $year->id, 'quarter_number' => 1, 'name' => 'Q1', 'start_date' => '2026-06-01', 'end_date' => '2026-08-31', 'status' => 'active']);
        DB::table('classroom_sessions')->insert([
            'section_module_id' => DB::table('section_modules')->where('section_id', $joy)->value('id'), 'quarter_id' => $quarter, 'teacher_id' => $tina->id,
            'session_date' => '2026-06-02', 'planned_start_time' => '08:00', 'planned_end_time' => '09:00', 'status' => 'cancelled',
        ]);

        $this->actingAs($tina)->getJson('/api/sections')->assertOk()->assertJsonPath('0.canRename', false);
        $this->actingAs($tina)->patchJson("/api/sections/{$joy}", ['sectionName' => 'Hope'])
            ->assertStatus(422)->assertJsonValidationErrors(['section' => Section::NAME_LOCKED_MESSAGE]);
        $this->assertSame('Joy', Section::find($joy)->name);
    }

    public function test_a_rename_form_opened_before_a_learner_was_enrolled_is_rejected(): void
    {
        $year = $this->year('2026-2027', '2026-06-01', '2027-03-31', 'active');
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        ['Joy' => $joy] = $this->setUpSections($tina, ['Joy'], $year);

        // The form opened while the section was still empty...
        $this->actingAs($tina)->getJson('/api/sections')->assertOk()->assertJsonPath('0.canRename', true);
        // ...then a learner was enrolled before it was sent.
        $this->enrollIn($joy);

        $this->actingAs($tina)->patchJson("/api/sections/{$joy}", ['sectionName' => 'Hope'])
            ->assertStatus(422)->assertJsonValidationErrors(['section' => Section::NAME_LOCKED_MESSAGE]);
        $this->assertSame('Joy', Section::find($joy)->name);
    }

    public function test_a_section_name_must_be_filled_short_and_unused_this_year(): void
    {
        $year = $this->year('2026-2027', '2026-06-01', '2027-03-31', 'active');
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        ['Joy' => $joy] = $this->setUpSections($tina, ['Joy', 'Faith'], $year);

        foreach (['', '   ', str_repeat('a', 51), 'faith', '  FAITH  '] as $name) {
            $this->actingAs($tina)->patchJson("/api/sections/{$joy}", ['sectionName' => $name])
                ->assertStatus(422)->assertJsonValidationErrors('sectionName');
        }
        $this->actingAs($tina)->patchJson("/api/sections/{$joy}", [])->assertStatus(422)->assertJsonValidationErrors('sectionName');
        $this->assertSame(['Faith', 'Joy'], DB::table('sections')->orderBy('name')->pluck('name')->all());
    }

    public function test_only_the_owners_sections_in_the_active_year_can_be_renamed(): void
    {
        $old = $this->year('2025-2026', '2025-06-01', '2026-03-31', 'completed');
        $year = $this->year('2026-2027', '2026-06-01', '2027-03-31', 'active');
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        ['Joy' => $lastYear] = $this->setUpSections($tina, ['Joy'], $old);
        ['Joy' => $joy] = $this->setUpSections($tina, ['Joy'], $year);

        $this->patchJson("/api/sections/{$joy}", ['sectionName' => 'Hope'])->assertUnauthorized();
        $this->actingAs($omar)->patchJson("/api/sections/{$joy}", ['sectionName' => 'Hope'])->assertNotFound();
        // A past year's section keeps the name its records were made under.
        $this->actingAs($tina)->patchJson("/api/sections/{$lastYear}", ['sectionName' => 'Hope'])->assertNotFound();
        $this->assertSame(['Joy', 'Joy'], DB::table('sections')->pluck('name')->all());
    }

    public function test_active_session_shows_current_module_and_activity_scoped_to_teacher(): void
    {
        $this->seed(CurriculumSeeder::class);
        $year = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $quarterId = DB::table('quarters')->insertGetId([
            'school_year_id' => $year->id, 'quarter_number' => 1, 'name' => '1st Quarter',
            'start_date' => '2026-06-01', 'end_date' => '2026-08-31', 'status' => 'active',
        ]);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $sectionId = $this->setUpSections($tina, ['Joy'])['Joy'];

        // No class in session yet: both names read null, not an error.
        $this->actingAs($tina)->getJson("/api/sections/{$sectionId}/active-session")->assertOk()
            ->assertJson(['activeModuleName' => null, 'activeActivityName' => null]);

        $sectionModuleId = DB::table('section_modules')
            ->join('modules', 'modules.id', '=', 'section_modules.module_id')
            ->where(['section_modules.section_id' => $sectionId, 'modules.code' => 'reading'])
            ->value('section_modules.id');
        $moduleDomainId = DB::table('module_domains')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where('modules.code', 'reading')->value('module_domains.id');
        $lessonId = DB::table('lessons')->insertGetId([
            'module_domain_id' => $moduleDomainId, 'title' => 'Letter A', 'sequence_order' => 1,
        ]);
        $activityId = DB::table('activities')->insertGetId([
            'lesson_id' => $lessonId, 'activity_code' => 'READ-A-1', 'name' => 'Trace the letter A',
            'instructions' => 'Trace it.', 'activity_format' => 'digital', 'sequence_order' => 1,
        ]);
        $sessionId = DB::table('classroom_sessions')->insertGetId([
            'section_module_id' => $sectionModuleId, 'quarter_id' => $quarterId, 'teacher_id' => $tina->id,
            'session_date' => now()->toDateString(), 'planned_start_time' => '08:00', 'planned_end_time' => '09:00',
            'started_at' => now(), 'status' => 'ongoing',
        ]);
        $sessionLessonId = DB::table('classroom_session_lessons')->insertGetId([
            'classroom_session_id' => $sessionId, 'lesson_id' => $lessonId, 'sequence_order' => 1, 'status' => 'active',
        ]);
        DB::table('classroom_session_activities')->insert([
            'session_lesson_id' => $sessionLessonId, 'activity_id' => $activityId, 'sequence_order' => 1,
            'assigned_by' => $tina->id, 'assigned_at' => now(), 'status' => 'active',
        ]);

        $this->actingAs($tina)->getJson("/api/sections/{$sectionId}/active-session")->assertOk()
            ->assertJson(['activeModuleName' => 'Reading', 'activeActivityName' => 'Trace the letter A']);

        // Another teacher's section is a 404, not someone else's live class.
        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        $this->actingAs($omar)->getJson("/api/sections/{$sectionId}/active-session")->assertNotFound();
    }

    public function test_curriculum_resources_counts_only_this_sections_modules(): void
    {
        $this->seed(CurriculumSeeder::class);
        SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $sectionId = $this->setUpSections($tina, ['Joy'])['Joy'];

        // No lessons yet — the section has modules, but nothing under them.
        $this->actingAs($tina)->getJson("/api/sections/{$sectionId}/curriculum-resources")->assertOk()
            ->assertJson(['lessonCount' => 0, 'activityCount' => 0]);

        $readingDomainId = DB::table('module_domains')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where('modules.code', 'reading')->value('module_domains.id');
        $lesson1 = DB::table('lessons')->insertGetId(['module_domain_id' => $readingDomainId, 'title' => 'Letter A', 'sequence_order' => 1]);
        $lesson2 = DB::table('lessons')->insertGetId(['module_domain_id' => $readingDomainId, 'title' => 'Letter B', 'sequence_order' => 2]);
        DB::table('activities')->insert([
            ['lesson_id' => $lesson1, 'activity_code' => 'A-1', 'name' => 'Trace A', 'instructions' => '-', 'activity_format' => 'digital', 'sequence_order' => 1],
            ['lesson_id' => $lesson1, 'activity_code' => 'A-2', 'name' => 'Color A', 'instructions' => '-', 'activity_format' => 'practical', 'sequence_order' => 2],
            ['lesson_id' => $lesson2, 'activity_code' => 'B-1', 'name' => 'Trace B', 'instructions' => '-', 'activity_format' => 'digital', 'sequence_order' => 1],
        ]);

        $this->actingAs($tina)->getJson("/api/sections/{$sectionId}/curriculum-resources")->assertOk()
            ->assertJson(['lessonCount' => 2, 'activityCount' => 3]);

        // Another teacher's section is a 404, never someone else's counts.
        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        $this->actingAs($omar)->getJson("/api/sections/{$sectionId}/curriculum-resources")->assertNotFound();
    }

    public function test_overall_progress_percent_and_no_active_quarter_state(): void
    {
        $this->seed(CurriculumSeeder::class);
        $year = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $sectionId = $this->setUpSections($tina, ['Joy'])['Joy'];

        // No quarter row at all yet.
        $this->actingAs($tina)->getJson("/api/sections/{$sectionId}/overall-progress")->assertOk()->assertContent('null');

        $quarterId = DB::table('quarters')->insertGetId([
            'school_year_id' => $year->id, 'quarter_number' => 1, 'name' => '1st Quarter',
            'start_date' => '2026-06-01', 'end_date' => '2026-08-31', 'status' => 'active',
        ]);

        $readingDomainId = DB::table('module_domains')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where('modules.code', 'reading')->value('module_domains.id');
        $lesson1 = DB::table('lessons')->insertGetId(['module_domain_id' => $readingDomainId, 'title' => 'Letter A', 'sequence_order' => 1, 'quarter_number' => 1]);
        $lesson2 = DB::table('lessons')->insertGetId(['module_domain_id' => $readingDomainId, 'title' => 'Letter B', 'sequence_order' => 2, 'quarter_number' => 1]);
        // A lesson in a different quarter must not count toward this quarter's total.
        DB::table('lessons')->insert(['module_domain_id' => $readingDomainId, 'title' => 'Letter C', 'sequence_order' => 3, 'quarter_number' => 2]);

        // 0 of 2 done yet.
        $this->actingAs($tina)->getJson("/api/sections/{$sectionId}/overall-progress")->assertOk()
            ->assertExactJson(['quarterId' => $quarterId, 'quarterName' => '1st Quarter', 'startDate' => '2026-06-01', 'percent' => 0]);

        $sectionModuleId = DB::table('section_modules')
            ->join('modules', 'modules.id', '=', 'section_modules.module_id')
            ->where(['section_modules.section_id' => $sectionId, 'modules.code' => 'reading'])
            ->value('section_modules.id');
        $sessionId = DB::table('classroom_sessions')->insertGetId([
            'section_module_id' => $sectionModuleId, 'quarter_id' => $quarterId, 'teacher_id' => $tina->id,
            'session_date' => now()->toDateString(), 'planned_start_time' => '08:00', 'planned_end_time' => '09:00', 'status' => 'completed',
        ]);
        DB::table('classroom_session_lessons')->insert([
            'classroom_session_id' => $sessionId, 'lesson_id' => $lesson1, 'sequence_order' => 1, 'status' => 'completed',
        ]);

        // 1 of 2 lessons for this quarter done => 50%.
        $this->actingAs($tina)->getJson("/api/sections/{$sectionId}/overall-progress")->assertOk()
            ->assertJson(['percent' => 50]);

        // Another teacher's section is a 404, never someone else's progress.
        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        $this->actingAs($omar)->getJson("/api/sections/{$sectionId}/overall-progress")->assertNotFound();
    }

    public function test_todays_schedule_shows_only_todays_module_times_and_current_quarter_lessons(): void
    {
        // A known Monday, so the default timetable's M/W/F modules are "today" and T/Th modules aren't.
        Carbon::setTestNow(Carbon::parse('2026-06-08 09:00:00', 'Asia/Manila'));

        $this->seed(CurriculumSeeder::class);
        $year = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $sectionId = $this->setUpSections($tina, ['Joy'])['Joy'];

        // No active quarter yet: today's module times still show (the timetable doesn't need a quarter), but no lessons.
        $response = $this->actingAs($tina)->getJson("/api/sections/{$sectionId}/todays-schedule")->assertOk()
            ->assertJsonCount(0, 'lessons')
            ->assertJsonFragment(['moduleId' => DB::table('modules')->where('code', 'reading')->value('id'), 'startTime' => '8:00 AM', 'endTime' => '9:00 AM']);
        // Monday has 3 module times (reading, language, writing) — MAPEH/Math's Tue/Thu slots never appear.
        $this->assertCount(3, $response->json('moduleTimes'));

        $quarterId = DB::table('quarters')->insertGetId([
            'school_year_id' => $year->id, 'quarter_number' => 1, 'name' => '1st Quarter',
            'start_date' => '2026-06-01', 'end_date' => '2026-08-31', 'status' => 'active',
        ]);
        $readingDomainId = DB::table('module_domains')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where('modules.code', 'reading')->value('module_domains.id');
        // Today's lesson: Monday (day_of_week 1), this quarter.
        $todayLessonId = DB::table('lessons')->insertGetId([
            'module_domain_id' => $readingDomainId, 'title' => 'Letter A', 'sequence_order' => 1,
            'day_of_week' => 1, 'quarter_number' => 1, 'week_number' => 2,
        ]);
        DB::table('activities')->insert([
            'lesson_id' => $todayLessonId, 'activity_code' => 'A-1', 'name' => 'Trace A',
            'instructions' => '-', 'activity_format' => 'digital', 'sequence_order' => 1,
        ]);
        // A Tuesday lesson and a next-quarter lesson must both stay out of today's list.
        DB::table('lessons')->insert([
            ['module_domain_id' => $readingDomainId, 'title' => 'Tuesday lesson', 'sequence_order' => 2, 'day_of_week' => 2, 'quarter_number' => 1, 'week_number' => 2],
            ['module_domain_id' => $readingDomainId, 'title' => 'Next quarter lesson', 'sequence_order' => 3, 'day_of_week' => 1, 'quarter_number' => 2, 'week_number' => 1],
        ]);
        unset($quarterId); // only its existence/activeness matters here

        $this->actingAs($tina)->getJson("/api/sections/{$sectionId}/todays-schedule")->assertOk()
            ->assertJsonCount(1, 'lessons')
            ->assertJsonPath('lessons.0.lessonName', 'Letter A')
            ->assertJsonPath('lessons.0.moduleName', 'Reading')
            ->assertJsonPath('lessons.0.moduleCode', 'reading')
            ->assertJsonPath('lessons.0.domainName', 'Reading and Language Development')
            ->assertJsonPath('lessons.0.week', 2)
            ->assertJsonPath('lessons.0.activities.0.activityName', 'Trace A');

        // Another teacher's section is a 404, never someone else's schedule.
        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        $this->actingAs($omar)->getJson("/api/sections/{$sectionId}/todays-schedule")->assertNotFound();
    }

    public function test_todays_schedule_names_each_mapeh_lessons_domain(): void
    {
        // A known Tuesday, when the default timetable runs MAPEH.
        Carbon::setTestNow(Carbon::parse('2026-06-09 09:00:00', 'Asia/Manila'));

        $this->seed(CurriculumSeeder::class);
        $year = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        DB::table('quarters')->insert([
            'school_year_id' => $year->id, 'quarter_number' => 1, 'name' => '1st Quarter',
            'start_date' => '2026-06-01', 'end_date' => '2026-08-31', 'status' => 'active',
        ]);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $sectionId = $this->setUpSections($tina, ['Joy'])['Joy'];

        $domainIds = DB::table('module_domains')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->where('modules.code', 'mapeh')
            ->pluck('module_domains.id', 'developmental_domains.code');
        // Inserted out of sequence so the response order can only come from sequence_order.
        foreach (['health_habits' => 3, 'gross_motor' => 1, 'arts_crafts' => 2] as $code => $order) {
            DB::table('lessons')->insert([
                'module_domain_id' => $domainIds[$code], 'title' => "Lesson {$order}", 'sequence_order' => $order,
                'day_of_week' => 2, 'quarter_number' => 1, 'week_number' => 2,
            ]);
        }

        $lessons = $this->actingAs($tina)->getJson("/api/sections/{$sectionId}/todays-schedule")->assertOk()->json('lessons');

        $this->assertSame(
            [
                ['Lesson 1', 'MAPEH', 'mapeh', 'Gross Motor Development'],
                ['Lesson 2', 'MAPEH', 'mapeh', 'Arts and Crafts'],
                ['Lesson 3', 'MAPEH', 'mapeh', 'Health, Works, Habits and Attitude'],
            ],
            array_map(fn ($l) => [$l['lessonName'], $l['moduleName'], $l['moduleCode'], $l['domainName']], $lessons),
        );
    }

    public function test_teacher_edits_a_sections_schedule_keeping_history(): void
    {
        $this->seed(CurriculumSeeder::class);
        SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        $sectionId = $this->setUpSections($tina, ['Joy'])['Joy'];

        $rows = collect($this->actingAs($tina)->getJson("/api/sections/$sectionId/schedule")->assertOk()->json())->keyBy('moduleName');
        $this->assertCount(5, $rows);
        $reading = $rows->first(fn ($r) => $r['startTime'] === '08:00' && $r['days'] === [1, 3, 5]);
        $this->assertNotNull($reading);

        $this->actingAs($omar)->getJson("/api/sections/$sectionId/schedule")->assertNotFound();

        $payload = fn (array $changes) => ['modules' => $rows->values()->map(fn ($r) => ($r['sectionModuleId'] === $reading['sectionModuleId'] ? $changes : []) + [
            'sectionModuleId' => $r['sectionModuleId'], 'startTime' => $r['startTime'], 'endTime' => $r['endTime'], 'days' => $r['days'],
        ])->all()];

        // End before start, and a clash with Language (9:15-10:00 MWF), are rejected.
        $this->actingAs($tina)->putJson("/api/sections/$sectionId/schedule", $payload(['startTime' => '09:00', 'endTime' => '08:30']))
            ->assertStatus(422);
        $this->actingAs($tina)->putJson("/api/sections/$sectionId/schedule", $payload(['startTime' => '09:00', 'endTime' => '09:30']))
            ->assertStatus(422);
        $this->assertSame(5, DB::table('class_schedules')->count());

        $saved = $this->actingAs($tina)->putJson("/api/sections/$sectionId/schedule", $payload(['startTime' => '07:00', 'endTime' => '08:00', 'days' => [1, 2]]))
            ->assertOk()->json();
        $updated = collect($saved)->firstWhere('sectionModuleId', $reading['sectionModuleId']);
        $this->assertSame(['07:00', '08:00', [1, 2]], [$updated['startTime'], $updated['endTime'], $updated['days']]);

        // Only the changed module got a new row; its old one stays, inactive.
        $this->assertSame(6, DB::table('class_schedules')->count());
        $this->assertFalse((bool) DB::table('class_schedules')->where('id', $reading['scheduleId'])->value('is_active'));
        $this->assertSame(5, DB::table('class_schedules')->where('is_active', true)->count());
    }

    public function test_schedule_save_leaves_a_never_scheduled_module_blank(): void
    {
        $this->seed(CurriculumSeeder::class);
        SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $sectionId = $this->setUpSections($tina, ['Joy'])['Joy'];
        $domainId = DB::table('developmental_domains')->value('id');
        $this->actingAs($tina)->postJson('/api/modules', ['moduleName' => 'Science', 'domainIds' => [$domainId]])->assertCreated();

        $rows = collect($this->actingAs($tina)->getJson("/api/sections/$sectionId/schedule")->assertOk()->json());
        $science = $rows->firstWhere('moduleName', 'Science');
        $this->assertSame([null, null, []], [$science['startTime'], $science['endTime'], $science['days']]);

        $payload = fn (callable $change) => ['modules' => $rows->map(fn ($r) => $change([
            'sectionModuleId' => $r['sectionModuleId'], 'startTime' => $r['startTime'], 'endTime' => $r['endTime'], 'days' => $r['days'],
        ], $r))->all()];

        // The blank Science row is skipped, so another module's change still saves.
        $this->actingAs($tina)->putJson("/api/sections/$sectionId/schedule", $payload(
            fn ($m, $r) => $r['moduleName'] === 'Reading' ? ['startTime' => '07:00', 'endTime' => '08:00'] + $m : $m,
        ))->assertOk();
        $this->assertSame(6, DB::table('class_schedules')->count());

        // A half-filled new row, or blanking a module that has a schedule, is rejected.
        $this->actingAs($tina)->putJson("/api/sections/$sectionId/schedule", $payload(
            fn ($m, $r) => $r['moduleName'] === 'Science' ? ['startTime' => '13:00'] + $m : $m,
        ))->assertStatus(422)->assertJsonValidationErrors([
            'modules.'.$rows->search($science).'.endTime',
            'modules.'.$rows->search($science).'.days',
        ]);
        $this->actingAs($tina)->putJson("/api/sections/$sectionId/schedule", $payload(
            fn ($m, $r) => $r['moduleName'] === 'Math' ? ['startTime' => null, 'endTime' => null, 'days' => []] + $m : $m,
        ))->assertStatus(422);
        $this->assertSame(6, DB::table('class_schedules')->count());
    }
}

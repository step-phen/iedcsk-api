<?php

namespace Tests\Feature;

use App\Models\Quarter;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Teacher;
use Database\Seeders\CurriculumSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchoolYearTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // Activation payload for a year that has no sections yet.
    private const BOTH = ['sections' => ['Joy', 'Faith']];

    private function teacher(): Teacher
    {
        return Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
    }

    /** @return array{SchoolYear, SchoolYear} the active 2026-2027 and an upcoming 2027-2028 */
    private function twoYears(): array
    {
        return [
            SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-15', 'end_date' => '2027-04-15', 'status' => 'active']),
            SchoolYear::create(['name' => '2027-2028', 'start_date' => '2027-06-14', 'end_date' => '2028-04-14']),
        ];
    }

    public function test_activates_a_school_year_and_keeps_one_active(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 10:00', 'Asia/Manila'));
        $tina = $this->teacher();
        [$current, $next] = $this->twoYears();

        $this->patchJson("/api/school-years/{$next->id}/activate")->assertUnauthorized();
        $this->actingAs($tina)->patchJson('/api/school-years/999/activate')->assertNotFound();

        $this->actingAs($tina)->patchJson("/api/school-years/{$next->id}/activate", self::BOTH)
            ->assertOk()->assertJson(['schoolYearId' => $next->id, 'status' => 'active']);

        // The old year hasn't ended yet, so it goes back to upcoming.
        $this->assertSame('upcoming', $current->refresh()->status);
        $this->assertSame(1, SchoolYear::where('status', 'active')->count());
        $this->actingAs($tina)->getJson('/api/school-years/active')->assertOk()->assertJson(['name' => '2027-2028']);
    }

    public function test_previous_year_is_completed_once_its_end_date_has_passed_in_manila(): void
    {
        $tina = $this->teacher();
        [$current, $next] = $this->twoYears();

        // Ends today (Apr 15) in Manila: not past yet.
        Carbon::setTestNow(Carbon::parse('2027-04-15 23:30', 'Asia/Manila'));
        $this->actingAs($tina)->patchJson("/api/school-years/{$next->id}/activate", self::BOTH)->assertOk();
        $this->assertSame('upcoming', $current->refresh()->status);

        // 00:30 Apr 16 in Manila is still Apr 15 in UTC, so this only passes when Manila's date is used.
        Carbon::setTestNow(Carbon::parse('2027-04-16 00:30', 'Asia/Manila'));
        $this->actingAs($tina)->patchJson("/api/school-years/{$current->id}/activate", self::BOTH)->assertOk();
        $this->actingAs($tina)->patchJson("/api/school-years/{$next->id}/activate")->assertOk();
        $this->assertSame('completed', $current->refresh()->status);
        $this->assertSame('active', $next->refresh()->status);
    }

    private function quarter(SchoolYear $year, int $number, string $start, string $end, string $status = 'upcoming'): Quarter
    {
        return Quarter::create(['school_year_id' => $year->id, 'quarter_number' => $number, 'name' => Quarter::NAMES[$number], 'start_date' => $start, 'end_date' => $end, 'status' => $status]);
    }

    /** @return array<int, string> quarter number => stored status */
    private function quarterStatuses(SchoolYear $year): array
    {
        return Quarter::where('school_year_id', $year->id)->orderBy('quarter_number')->pluck('status', 'quarter_number')->all();
    }

    public function test_switching_years_completes_the_old_years_ended_quarter(): void
    {
        $tina = $this->teacher();
        [$current, $next] = $this->twoYears();
        $this->quarter($current, 4, '2027-02-01', '2027-04-15', 'active');
        $this->quarter($next, 1, '2027-06-14', '2027-08-31');

        Carbon::setTestNow(Carbon::parse('2027-04-20 10:00', 'Asia/Manila'));
        $this->actingAs($tina)->patchJson("/api/school-years/{$next->id}/activate", self::BOTH)->assertOk();

        $this->assertSame('completed', $current->refresh()->status);
        $this->assertSame([4 => 'completed'], $this->quarterStatuses($current));
        $this->assertSame('active', $next->refresh()->status);
        // Nothing is activated for the new year; the teacher picks its quarter.
        $this->assertSame([1 => 'upcoming'], $this->quarterStatuses($next));
        $this->assertSame(1, SchoolYear::where('status', 'active')->count());
        $this->assertSame(0, Quarter::where('status', 'active')->count());
    }

    public function test_active_year_includes_its_active_quarter(): void
    {
        $tina = $this->teacher();
        [$current] = $this->twoYears();
        $this->actingAs($tina)->getJson('/api/school-years/active')->assertOk()->assertJson(['activeQuarter' => null]);

        $this->quarter($current, 1, '2026-06-15', '2026-09-25', 'active');
        $this->actingAs($tina)->getJson('/api/school-years/active')->assertOk()->assertJson(['activeQuarter' => '1st Quarter', 'activeQuarterNumber' => 1]);
    }

    public function test_switching_years_returns_an_unfinished_quarter_to_upcoming(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 10:00', 'Asia/Manila'));
        $tina = $this->teacher();
        [$current, $next] = $this->twoYears();
        $this->quarter($current, 1, '2026-06-15', '2026-09-25', 'completed');
        $this->quarter($current, 2, '2026-09-28', '2026-11-13', 'active');

        $this->actingAs($tina)->patchJson("/api/school-years/{$next->id}/activate", self::BOTH)->assertOk();

        $this->assertSame('upcoming', $current->refresh()->status);
        $this->assertSame([1 => 'completed', 2 => 'upcoming'], $this->quarterStatuses($current));
        $this->assertSame('active', $next->refresh()->status);
    }

    public function test_a_completed_year_cannot_be_set_as_active_again(): void
    {
        $tina = $this->teacher();
        [$current, $next] = $this->twoYears();
        $this->actingAs($tina)->patchJson("/api/school-years/{$current->id}/activate", self::BOTH)->assertOk();
        $current->update(['status' => 'completed']);
        $next->update(['status' => 'active']);

        $this->actingAs($tina)->patchJson("/api/school-years/{$current->id}/activate")
            ->assertStatus(422)->assertJsonValidationErrors(['schoolYear' => 'is completed']);
        $this->assertSame(['completed', 'active'], [$current->refresh()->status, $next->refresh()->status]);

        // Neither it nor the active year can be edited; only an upcoming one can.
        foreach ([$current, $next] as $year) {
            $before = $year->refresh()->only(['name', 'start_date', 'end_date']);
            $this->actingAs($tina)->patchJson("/api/school-years/{$year->id}", ['name' => $year->name, 'startDate' => '2026-06-20', 'endDate' => '2027-04-20'])
                ->assertStatus(422)->assertJsonValidationErrors(['name' => "is {$year->status}, so it can't be edited."]);
            $this->assertEquals($before, $year->refresh()->only(['name', 'start_date', 'end_date']));
        }
    }

    public function test_old_quarter_ending_today_in_manila_is_not_completed_yet(): void
    {
        $tina = $this->teacher();
        [$current, $next] = $this->twoYears();
        $this->quarter($current, 1, '2026-06-15', '2026-09-25', 'active');

        // Still Sep 25 in Manila: the quarter's last day.
        Carbon::setTestNow(Carbon::parse('2026-09-25 23:30', 'Asia/Manila'));
        $this->actingAs($tina)->patchJson("/api/school-years/{$next->id}/activate", self::BOTH)->assertOk();
        $this->assertSame([1 => 'upcoming'], $this->quarterStatuses($current));

        // Sep 26 in Manila, though still Sep 25 in UTC.
        Carbon::setTestNow(Carbon::parse('2026-09-26 00:30', 'Asia/Manila'));
        $this->actingAs($tina)->patchJson("/api/school-years/{$current->id}/activate", self::BOTH)->assertOk();
        $this->actingAs($tina)->patchJson("/api/school-years/{$current->id}/quarters/1/activate")->assertOk();
        $this->actingAs($tina)->patchJson("/api/school-years/{$next->id}/activate")->assertOk();
        $this->assertSame([1 => 'completed'], $this->quarterStatuses($current));
    }

    public function test_switching_years_without_an_active_quarter_still_works(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 10:00', 'Asia/Manila'));
        $tina = $this->teacher();
        [$current, $next] = $this->twoYears();
        $this->quarter($current, 1, '2026-06-15', '2026-09-25');

        $this->actingAs($tina)->patchJson("/api/school-years/{$next->id}/activate", self::BOTH)->assertOk()->assertJson(['status' => 'active']);

        $this->assertSame('upcoming', $current->refresh()->status);
        $this->assertSame([1 => 'upcoming'], $this->quarterStatuses($current));
    }

    public function test_target_years_stored_active_quarter_is_kept(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 10:00', 'Asia/Manila'));
        $tina = $this->teacher();
        [$current, $next] = $this->twoYears();
        $this->quarter($current, 2, '2026-09-28', '2026-11-13', 'active');
        // Left active by data from before this rule existed.
        $this->quarter($next, 1, '2027-06-14', '2027-08-31', 'active');

        $this->actingAs($tina)->patchJson("/api/school-years/{$next->id}/activate", self::BOTH)->assertOk();

        $this->assertSame([2 => 'upcoming'], $this->quarterStatuses($current));
        $this->assertSame([1 => 'active'], $this->quarterStatuses($next));
        $this->assertSame(1, Quarter::where('status', 'active')->count());
    }

    public function test_activating_the_active_year_leaves_its_quarter_alone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 10:00', 'Asia/Manila'));
        $tina = $this->teacher();
        [$current] = $this->twoYears();
        $quarter = $this->quarter($current, 1, '2026-06-15', '2026-09-25', 'active');
        $stamp = $quarter->refresh()->updated_at;

        Carbon::setTestNow(Carbon::parse('2026-09-29 10:00', 'Asia/Manila'));
        $this->actingAs($tina)->patchJson("/api/school-years/{$current->id}/activate", self::BOTH)->assertOk();

        $this->assertSame('active', $quarter->refresh()->status);
        $this->assertEquals($stamp, $quarter->updated_at);
    }

    public function test_year_and_quarter_change_together_or_not_at_all(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 10:00', 'Asia/Manila'));
        $tina = $this->teacher();
        [$current, $next] = $this->twoYears();
        $this->quarter($current, 2, '2026-09-28', '2026-11-13', 'active');

        // Fail on the quarter update, after the old year has already been demoted.
        Quarter::updating(fn () => throw new \RuntimeException('quarter update failed'));
        $this->actingAs($tina)->patchJson("/api/school-years/{$next->id}/activate", self::BOTH)->assertServerError();
        Quarter::flushEventListeners();

        // The new year's sections roll back with it.
        $this->assertSame(0, DB::table('sections')->count());
        $this->assertSame('active', $current->refresh()->status);
        $this->assertSame('upcoming', $next->refresh()->status);
        $this->assertSame([2 => 'active'], $this->quarterStatuses($current));
    }

    public function test_quarter_activation_follows_the_new_active_year(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 10:00', 'Asia/Manila'));
        $tina = $this->teacher();
        [$current, $next] = $this->twoYears();
        $this->quarter($current, 2, '2026-09-28', '2026-11-13', 'active');
        $this->quarter($next, 1, '2027-06-14', '2027-08-31');

        $this->actingAs($tina)->patchJson("/api/school-years/{$next->id}/activate", self::BOTH)->assertOk();

        $this->actingAs($tina)->patchJson("/api/school-years/{$next->id}/quarters/1/activate")->assertOk()->assertJson(['status' => 'active']);
        $this->actingAs($tina)->patchJson("/api/school-years/{$current->id}/quarters/2/activate")->assertStatus(422);
        $this->assertSame([2 => 'upcoming'], $this->quarterStatuses($current));
        $this->assertSame(1, Quarter::where('status', 'active')->count());
    }

    public function test_activating_the_active_year_changes_nothing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 10:00', 'Asia/Manila'));
        $tina = $this->teacher();
        [$current, $next] = $this->twoYears();
        $stamp = $current->refresh()->updated_at;

        Carbon::setTestNow(Carbon::parse('2026-09-29 10:00', 'Asia/Manila'));
        $this->actingAs($tina)->patchJson("/api/school-years/{$current->id}/activate", self::BOTH)
            ->assertOk()->assertJson(['status' => 'active']);

        $this->assertEquals($stamp, $current->refresh()->updated_at);
        $this->assertSame('upcoming', $next->refresh()->status);
    }

    public function test_edit_endpoint_cannot_change_status(): void
    {
        $tina = $this->teacher();
        [$current, $next] = $this->twoYears();

        $this->actingAs($tina)->patchJson("/api/school-years/{$next->id}", ['name' => '2027-2028', 'startDate' => '2027-06-14', 'endDate' => '2028-04-14', 'status' => 'active'])
            ->assertOk()->assertJson(['status' => 'upcoming']);
        $this->assertSame('active', $current->refresh()->status);
    }

    public function test_active_endpoint_returns_null_without_an_active_year(): void
    {
        $tina = $this->teacher();
        $this->getJson('/api/school-years/active')->assertUnauthorized();

        $this->actingAs($tina)->getJson('/api/school-years/active')->assertOk()->assertContent('null');

        [$current] = $this->twoYears();
        $this->actingAs($tina)->getJson('/api/school-years/active')->assertOk()
            ->assertExactJson(['schoolYearId' => $current->id, 'name' => '2026-2027', 'startDate' => '2026-06-15', 'endDate' => '2027-04-15', 'status' => 'active', 'sections' => [], 'activeQuarter' => null, 'activeQuarterNumber' => null, 'quarterStatuses' => [], 'canAddModules' => true]);
    }

    public function test_list_returns_stored_status_even_when_dates_disagree(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 10:00', 'Asia/Manila'));
        $tina = $this->teacher();
        // Stored statuses that the dates alone would contradict; nothing may recompute them.
        SchoolYear::create(['name' => '2024-2025', 'start_date' => '2024-06-01', 'end_date' => '2025-04-01', 'status' => 'upcoming']);
        SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-06-01', 'end_date' => '2026-04-01', 'status' => 'completed']);
        SchoolYear::create(['name' => '2027-2028', 'start_date' => '2027-06-01', 'end_date' => '2028-04-01', 'status' => 'active']);

        $this->actingAs($tina)->getJson('/api/school-years')->assertOk()->assertJson([
            ['name' => '2027-2028', 'status' => 'active'],
            ['name' => '2025-2026', 'status' => 'completed'],
            ['name' => '2024-2025', 'status' => 'upcoming'],
        ]);
    }

    public function test_single_active_constraint_still_holds(): void
    {
        [, $next] = $this->twoYears();

        $this->expectException(QueryException::class);
        $next->update(['status' => 'active']);
    }

    public function test_switching_years_moves_sections_and_learners_without_touching_old_rows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 10:00', 'Asia/Manila'));
        $tina = $this->teacher();
        [$current, $next] = $this->twoYears();
        $joy = DB::table('sections')->insertGetId(['school_year_id' => $current->id, 'teacher_id' => $tina->id, 'name' => 'Joy', 'grade_level' => 'Kindergarten']);
        $learner = DB::table('learners')->insertGetId(['first_name' => 'Ana', 'last_name' => 'Cruz', 'birth_date' => '2021-01-01']);
        DB::table('learner_school_years')->insert(['learner_id' => $learner, 'school_year_id' => $current->id, 'section_id' => $joy, 'enrolled_on' => '2026-06-15', 'status' => 'active']);

        $this->actingAs($tina)->getJson('/api/sections')->assertJsonCount(1);
        $this->actingAs($tina)->getJson('/api/learners')->assertJsonCount(1);

        $oldRows = fn () => [
            DB::table('sections')->where('school_year_id', $current->id)->get()->all(),
            DB::table('learner_school_years')->get()->all(),
        ];
        $before = $oldRows();

        // The new year gets its own Joy; the old year's Joy, learner and enrollment stay where they were.
        $this->actingAs($tina)->patchJson("/api/school-years/{$next->id}/activate", ['sections' => ['Joy']])
            ->assertOk()->assertJson(['sections' => ['Joy']]);

        $sections = $this->actingAs($tina)->getJson('/api/sections')->assertOk()->assertJsonCount(1)->json();
        $this->assertNotSame($joy, $sections[0]['sectionId']);
        $this->assertSame($next->id, DB::table('sections')->where('id', $sections[0]['sectionId'])->value('school_year_id'));
        // No fallback to the previous year's learners.
        $this->actingAs($tina)->getJson('/api/learners')->assertOk()->assertJsonCount(0);
        $this->assertEquals($before, $oldRows());
    }

    public function test_first_school_year_is_set_up_in_settings_after_a_fresh_seed(): void
    {
        $server = $_SERVER;
        try {
            $_SERVER = ['SEED_TEACHER_USERNAME' => 'Tina', 'SEED_TEACHER_PASSWORD' => 'secret-pass', 'SEED_TEACHER_NAME' => 'Tina'] + $_SERVER;
            $this->seed();
        } finally {
            $_SERVER = $server;
        }

        // Seeding creates the teacher but no school year; the teacher adds and activates it in Settings.
        $tina = Teacher::where('username', 'tina')->firstOrFail();
        $this->assertSame(0, SchoolYear::count());
        $this->actingAs($tina)->getJson('/api/school-years/active')->assertOk()->assertContent('null');

        $id = $this->actingAs($tina)->postJson('/api/school-years', ['name' => '2026-2027', 'startDate' => '2026-06-15', 'endDate' => '2027-04-15'])
            ->assertCreated()->assertJson(['status' => 'upcoming'])->json('schoolYearId');
        $this->actingAs($tina)->patchJson("/api/school-years/{$id}/activate", self::BOTH)->assertOk()->assertJson(['status' => 'active']);
        $this->actingAs($tina)->getJson('/api/school-years/active')->assertOk()->assertJson(['schoolYearId' => $id, 'name' => '2026-2027', 'sections' => ['Joy', 'Faith']]);

        // Activation set up Joy (morning) and Faith (afternoon), with no learners, so both can still be renamed.
        $this->actingAs($tina)->getJson('/api/sections')->assertOk()->assertExactJson([
            ['sectionId' => DB::table('sections')->where('name', 'Joy')->value('id'), 'gradeLevel' => 'Kindergarten', 'sectionName' => 'Joy', 'session' => 'morning', 'canRename' => true, 'renameReason' => null, 'learnerCount' => 0, 'capacity' => Section::CAPACITY],
            ['sectionId' => DB::table('sections')->where('name', 'Faith')->value('id'), 'gradeLevel' => 'Kindergarten', 'sectionName' => 'Faith', 'session' => 'afternoon', 'canRename' => true, 'renameReason' => null, 'learnerCount' => 0, 'capacity' => Section::CAPACITY],
        ]);
        $this->assertSame(0, DB::table('learners')->count());
        $this->assertSame(0, DB::table('learner_school_years')->count());
    }

    public function test_creates_and_lists_school_years(): void
    {
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-15', 'end_date' => '2027-04-15', 'status' => 'active']);
        $valid = ['name' => '2027-2028', 'startDate' => '2027-06-14', 'endDate' => '2028-04-14'];

        $this->getJson('/api/school-years')->assertUnauthorized();
        $this->postJson('/api/school-years', $valid)->assertUnauthorized();

        // New years are never made active on creation.
        $this->actingAs($tina)->postJson('/api/school-years', $valid)
            ->assertCreated()
            ->assertJson(['name' => '2027-2028', 'startDate' => '2027-06-14', 'endDate' => '2028-04-14', 'status' => 'upcoming']);

        $this->actingAs($tina)->postJson('/api/school-years', [])
            ->assertStatus(422)->assertJsonValidationErrors('name')->assertJsonMissingValidationErrors(['startDate', 'endDate']);
        $this->actingAs($tina)->postJson('/api/school-years', ['name' => '2026-2027'] + $valid)
            ->assertStatus(422)->assertJsonValidationErrors('name');
        $this->actingAs($tina)->postJson('/api/school-years', ['name' => 'SY 2028-2029'] + $valid)
            ->assertStatus(422)->assertJsonValidationErrors('name');
        $this->actingAs($tina)->postJson('/api/school-years', ['name' => '2028-2029', 'startDate' => '2028-06-01', 'endDate' => '2028-06-01'])
            ->assertStatus(422)->assertJsonValidationErrors('endDate');

        $this->actingAs($tina)->getJson('/api/school-years')->assertOk()
            ->assertJsonPath('0.name', '2027-2028')
            ->assertJsonPath('1.status', 'active')
            ->assertJsonCount(2);
    }

    /** @return array<int, array> class_schedules id => its row plus days, for one school year's sections */
    private function schedules(SchoolYear $year): array
    {
        return DB::table('class_schedules')
            ->join('section_modules', 'section_modules.id', '=', 'class_schedules.section_module_id')
            ->join('sections', 'sections.id', '=', 'section_modules.section_id')
            ->where('sections.school_year_id', $year->id)
            ->orderBy('class_schedules.id')
            ->get(['class_schedules.*'])
            ->mapWithKeys(fn ($row) => [$row->id => [
                ...array_diff_key((array) $row, ['updated_at' => 0]),
                'days' => DB::table('schedule_days')->where('schedule_id', $row->id)->orderBy('day_of_week')->pluck('day_of_week')->all(),
            ]])
            ->all();
    }

    /** @return array{Teacher, SchoolYear, SchoolYear} a teacher with a Joy timetable in the active year and a Hope one in the next */
    private function yearsWithTimetables(): array
    {
        $this->seed(CurriculumSeeder::class);
        $tina = $this->teacher();
        [$current, $next] = $this->twoYears();
        $this->setUpSections($tina, ['Joy'], $current);
        // Switched away from before it ended, so it is upcoming again and can be edited; its sections stay.
        $current->update(['status' => 'upcoming']);

        // The next year's section, built by hand.
        $hope = DB::table('sections')->insertGetId(['school_year_id' => $next->id, 'teacher_id' => $tina->id, 'name' => 'Hope', 'grade_level' => 'Kindergarten']);
        $sectionModule = DB::table('section_modules')->insertGetId(['section_id' => $hope, 'module_id' => DB::table('modules')->value('id'), 'teacher_id' => $tina->id]);
        $schedule = DB::table('class_schedules')->insertGetId(['section_module_id' => $sectionModule, 'start_time' => '08:00', 'end_time' => '09:00', 'effective_from' => '2027-06-14', 'effective_until' => '2028-04-14']);
        DB::table('schedule_days')->insert(['schedule_id' => $schedule, 'day_of_week' => 1]);

        return [$tina, $current, $next];
    }

    public function test_editing_school_year_dates_moves_its_timetables_validity_only(): void
    {
        [$tina, $current, $next] = $this->yearsWithTimetables();
        $before = $this->schedules($current);
        $otherYear = $this->schedules($next);
        $this->assertCount(5, $before);
        $this->assertSame(['2026-06-15'], array_values(array_unique(array_column($before, 'effective_from'))));

        $this->actingAs($tina)->patchJson("/api/school-years/{$current->id}", ['name' => '2026-2027', 'startDate' => '2026-06-16', 'endDate' => '2027-04-30'])
            ->assertOk();

        // Same rows, times, days and is_active; only the validity range follows the year.
        $expected = array_map(fn ($row) => [...$row, 'effective_from' => '2026-06-16', 'effective_until' => '2027-04-30'], $before);
        $this->assertSame($expected, $this->schedules($current));
        $this->assertSame(5, DB::table('class_schedules')->where('is_active', true)->whereIn('id', array_keys($before))->count());
        // Another year's timetable is untouched.
        $this->assertSame($otherYear, $this->schedules($next));
    }

    public function test_renaming_a_school_year_leaves_timetables_alone(): void
    {
        [$tina, $current] = $this->yearsWithTimetables();
        $stamps = DB::table('class_schedules')->orderBy('id')->pluck('updated_at', 'id')->all();

        Carbon::setTestNow(now()->addDay());
        $current->update(['name' => '2026-2099']);
        $this->actingAs($tina)->patchJson("/api/school-years/{$current->id}", ['name' => '2026-2027', 'startDate' => '2026-06-15', 'endDate' => '2027-04-15'])
            ->assertOk()->assertJson(['name' => '2026-2027']);

        $this->assertSame($stamps, DB::table('class_schedules')->orderBy('id')->pluck('updated_at', 'id')->all());
    }

    public function test_school_year_edit_rolls_back_when_timetable_sync_fails(): void
    {
        [$tina, $current] = $this->yearsWithTimetables();
        $before = $this->schedules($current);
        // Make the schedule update fail after the school year row has been written.
        DB::unprepared("CREATE FUNCTION fail_schedule_sync() RETURNS trigger AS \$\$ BEGIN RAISE EXCEPTION 'sync failed'; END \$\$ LANGUAGE plpgsql;
            CREATE TRIGGER fail_schedule_sync BEFORE UPDATE ON class_schedules FOR EACH ROW EXECUTE FUNCTION fail_schedule_sync();");

        $this->actingAs($tina)->patchJson("/api/school-years/{$current->id}", ['name' => '2026-2027', 'startDate' => '2026-06-16', 'endDate' => '2027-04-30'])
            ->assertServerError();

        $current->refresh();
        $this->assertSame(['2026-06-15', '2027-04-15'], [$current->start_date->toDateString(), $current->end_date->toDateString()]);
        $this->assertSame($before, $this->schedules($current));
    }

    public function test_quarter_range_check_blocks_the_edit_before_timetables_change(): void
    {
        [$tina, $current] = $this->yearsWithTimetables();
        $before = $this->schedules($current);
        DB::table('quarters')->insert(['school_year_id' => $current->id, 'quarter_number' => 1, 'name' => '1st Quarter', 'start_date' => '2026-06-15', 'end_date' => '2026-08-31', 'status' => 'upcoming']);

        $this->actingAs($tina)->patchJson("/api/school-years/{$current->id}", ['name' => '2026-2027', 'startDate' => '2026-06-16', 'endDate' => '2027-04-30'])
            ->assertStatus(422)->assertJsonValidationErrors('startDate');

        $this->assertSame('2026-06-15', $current->refresh()->start_date->toDateString());
        $this->assertSame($before, $this->schedules($current));
    }

    public function test_updates_school_year_in_place(): void
    {
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $year = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-15', 'end_date' => '2027-04-15']);
        SchoolYear::create(['name' => '2027-2028', 'start_date' => '2027-06-14', 'end_date' => '2028-04-14']);
        $url = "/api/school-years/{$year->id}";

        $this->patchJson($url, ['name' => '2026-2027', 'startDate' => '2026-06-01', 'endDate' => '2027-04-15'])->assertUnauthorized();

        // Keeping its own name is fine; the row is updated, not duplicated, and stays upcoming.
        $this->actingAs($tina)->patchJson($url, ['name' => '2026-2027', 'startDate' => '2026-06-01', 'endDate' => '2027-04-30'])
            ->assertOk()
            ->assertJson(['schoolYearId' => $year->id, 'startDate' => '2026-06-01', 'endDate' => '2027-04-30', 'status' => 'upcoming']);
        $this->assertSame(2, SchoolYear::count());

        $this->actingAs($tina)->patchJson($url, ['name' => '2027-2028', 'startDate' => '2026-06-01', 'endDate' => '2027-04-30'])
            ->assertStatus(422)->assertJsonValidationErrors('name');
        $this->actingAs($tina)->patchJson($url, ['name' => '2026-2027', 'startDate' => '2027-05-01', 'endDate' => '2027-04-30'])
            ->assertStatus(422)->assertJsonValidationErrors('endDate');

        // The range can't shrink past the year's quarters.
        DB::table('quarters')->insert(['school_year_id' => $year->id, 'quarter_number' => 1, 'name' => '1st Quarter', 'start_date' => '2026-06-15', 'end_date' => '2026-08-31', 'status' => 'upcoming']);
        $this->actingAs($tina)->patchJson($url, ['name' => '2026-2027', 'startDate' => '2026-07-01', 'endDate' => '2027-04-30'])
            ->assertStatus(422)->assertJsonValidationErrors('startDate');
        $this->actingAs($tina)->patchJson($url, ['name' => '2026-2027', 'startDate' => '2026-06-01', 'endDate' => '2026-08-01'])
            ->assertStatus(422)->assertJsonValidationErrors('endDate');

        $this->actingAs($tina)->patchJson('/api/school-years/999', ['name' => '2030-2031', 'startDate' => '2030-06-01', 'endDate' => '2031-04-01'])
            ->assertNotFound();
    }

    public function test_creates_an_upcoming_year_without_dates_and_lists_it_first(): void
    {
        $tina = $this->teacher();
        $this->twoYears();

        $this->actingAs($tina)->postJson('/api/school-years', ['name' => '2028-2029'])
            ->assertCreated()
            ->assertJson(['name' => '2028-2029', 'startDate' => null, 'endDate' => null, 'status' => 'upcoming']);
        // Empty strings from the form read as no dates too.
        $this->actingAs($tina)->postJson('/api/school-years', ['name' => '2029-2030', 'startDate' => '', 'endDate' => ''])
            ->assertCreated()->assertJson(['startDate' => null, 'endDate' => null]);

        // Undated years first (newest name first), then by start date.
        $this->actingAs($tina)->getJson('/api/school-years')->assertOk()
            ->assertJsonPath('0.name', '2029-2030')
            ->assertJsonPath('1.name', '2028-2029')
            ->assertJsonPath('2.name', '2027-2028')
            ->assertJsonPath('3.name', '2026-2027');
    }

    public function test_one_date_without_the_other_is_rejected(): void
    {
        $tina = $this->teacher();
        [, $next] = $this->twoYears();
        $undated = SchoolYear::create(['name' => '2028-2029']);

        $this->actingAs($tina)->postJson('/api/school-years', ['name' => '2029-2030', 'startDate' => '2029-06-01'])
            ->assertStatus(422)->assertJsonValidationErrors('endDate');
        $this->actingAs($tina)->postJson('/api/school-years', ['name' => '2029-2030', 'endDate' => '2030-04-01'])
            ->assertStatus(422)->assertJsonValidationErrors('startDate');
        $this->actingAs($tina)->patchJson("/api/school-years/{$undated->id}", ['startDate' => '2028-06-01'])
            ->assertStatus(422)->assertJsonValidationErrors('endDate');
        $this->actingAs($tina)->patchJson("/api/school-years/{$next->id}", ['endDate' => null])
            ->assertStatus(422)->assertJsonValidationErrors('endDate');

        $this->assertNull($undated->refresh()->start_date);
        $this->assertSame('2028-04-14', $next->refresh()->end_date->toDateString());
        $this->assertSame(3, SchoolYear::count());
    }

    public function test_omitted_dates_are_kept_and_explicit_nulls_clear_them(): void
    {
        $tina = $this->teacher();
        [, $next] = $this->twoYears();
        $undated = SchoolYear::create(['name' => '2028-2029']);

        // Renaming alone keeps the stored dates.
        $this->actingAs($tina)->patchJson("/api/school-years/{$next->id}", ['name' => '2027-2028'])
            ->assertOk()->assertJson(['startDate' => '2027-06-14', 'endDate' => '2028-04-14']);

        // The official dates are added later.
        $this->actingAs($tina)->patchJson("/api/school-years/{$undated->id}", ['startDate' => '2028-06-12', 'endDate' => '2029-04-13'])
            ->assertOk()->assertJson(['name' => '2028-2029', 'startDate' => '2028-06-12', 'endDate' => '2029-04-13', 'status' => 'upcoming']);
        $this->actingAs($tina)->patchJson("/api/school-years/{$undated->id}", ['startDate' => '2029-06-12', 'endDate' => '2029-06-12'])
            ->assertStatus(422)->assertJsonValidationErrors('endDate');

        // An upcoming year without quarters may have its dates removed on purpose.
        $this->actingAs($tina)->patchJson("/api/school-years/{$next->id}", ['startDate' => null, 'endDate' => null])
            ->assertOk()->assertJson(['name' => '2027-2028', 'startDate' => null, 'endDate' => null]);
    }

    public function test_dates_cannot_be_removed_from_active_years_or_years_with_quarters(): void
    {
        $tina = $this->teacher();
        [$current, $next] = $this->twoYears();
        Quarter::create(['school_year_id' => $next->id, 'quarter_number' => 1, 'name' => '1st Quarter', 'start_date' => '2027-06-14', 'end_date' => '2027-08-31', 'status' => 'upcoming']);

        $this->actingAs($tina)->patchJson("/api/school-years/{$current->id}", ['startDate' => null, 'endDate' => null])
            ->assertStatus(422)->assertJsonValidationErrors(['startDate' => 'Active and completed school years need start and end dates.']);
        $this->actingAs($tina)->patchJson("/api/school-years/{$next->id}", ['startDate' => '', 'endDate' => ''])
            ->assertStatus(422)->assertJsonValidationErrors(['startDate' => "This school year has quarters, so its dates can't be removed."]);

        $this->assertNotNull($current->refresh()->start_date);
        $this->assertNotNull($next->refresh()->start_date);

        // The database backs both rules up.
        $this->expectException(QueryException::class);
        DB::table('school_years')->where('id', $current->id)->update(['start_date' => null, 'end_date' => null]);
    }

    public function test_an_undated_year_cannot_be_activated(): void
    {
        $tina = $this->teacher();
        [$current] = $this->twoYears();
        $undated = SchoolYear::create(['name' => '2028-2029']);

        $this->actingAs($tina)->patchJson("/api/school-years/{$undated->id}/activate")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['schoolYear' => 'Please set the school year start and end dates before activating it.']);

        $this->assertSame('active', $current->refresh()->status);
        $this->assertSame('upcoming', $undated->refresh()->status);
    }

    public function test_database_requires_both_dates_or_neither(): void
    {
        $this->expectException(QueryException::class);
        SchoolYear::create(['name' => '2028-2029', 'start_date' => '2028-06-01']);
    }

    public function test_activation_takes_the_quarter_lock(): void
    {
        $tina = $this->teacher();
        [, $next] = $this->twoYears();

        $this->assertTrue($this->quarterLockFree());
        $this->actingAs($tina)->patchJson("/api/school-years/{$next->id}/activate", self::BOTH)->assertOk();
        $this->assertFalse($this->quarterLockFree());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Quarter;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Teacher;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QuarterTest extends TestCase
{
    use RefreshDatabase;

    private Teacher $tina;

    private SchoolYear $year;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $this->year = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-15', 'end_date' => '2027-04-15', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function activate(int $number, ?SchoolYear $year = null)
    {
        $year ??= $this->year;

        return $this->actingAs($this->tina)->patchJson("/api/school-years/{$year->id}/quarters/{$number}/activate");
    }

    private function statuses(): array
    {
        return Quarter::where('school_year_id', $this->year->id)->orderBy('quarter_number')->pluck('status', 'quarter_number')->all();
    }

    public function test_activates_a_quarter_and_the_dashboard_reads_it(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 10:00', 'Asia/Manila'));
        $section = Section::create(['school_year_id' => $this->year->id, 'teacher_id' => $this->tina->id, 'grade_level' => 'Kindergarten', 'name' => 'Joy']);
        $this->saveQuarter(1, '2026-06-15', '2026-09-25')->assertCreated();
        $this->saveQuarter(2, '2026-09-28', '2026-11-13')->assertCreated();

        $this->actingAs($this->tina)->getJson("/api/sections/{$section->id}/overall-progress")->assertContent('null');

        $this->activate(1)->assertOk()->assertJson(['quarterNumber' => 1, 'status' => 'active']);
        // Q1 ended before today in Manila, so it completes when Q2 takes over.
        $this->activate(2)->assertOk()->assertJson(['quarterNumber' => 2, 'status' => 'active']);
        $this->assertSame([1 => 'completed', 2 => 'active'], $this->statuses());

        $this->actingAs($this->tina)->getJson("/api/sections/{$section->id}/overall-progress")->assertOk()
            ->assertJson(['quarterName' => '2nd Quarter']);
    }

    public function test_previous_quarter_returns_to_upcoming_until_its_end_date_passes_in_manila(): void
    {
        $this->saveQuarter(1, '2026-06-15', '2026-09-25')->assertCreated();
        $this->saveQuarter(2, '2026-09-28', '2026-11-13')->assertCreated();

        // Still Sep 25 in Manila (16:30 UTC): Q1's last day, so it isn't over yet.
        Carbon::setTestNow(Carbon::parse('2026-09-25 23:30', 'Asia/Manila'));
        $this->activate(1)->assertOk();
        $this->activate(2)->assertOk();
        $this->assertSame([1 => 'upcoming', 2 => 'active'], $this->statuses());

        // Sep 26 in Manila, though still Sep 25 in UTC.
        Carbon::setTestNow(Carbon::parse('2026-09-26 00:30', 'Asia/Manila'));
        $this->activate(1)->assertOk();
        $this->activate(2)->assertOk();
        $this->assertSame([1 => 'completed', 2 => 'active'], $this->statuses());
    }

    public function test_activating_the_active_quarter_changes_nothing(): void
    {
        $this->saveQuarter(1, '2026-06-15', '2026-09-25')->assertCreated();
        $this->activate(1)->assertOk();
        $before = Quarter::first()->updated_at;

        $this->travel(5)->minutes();
        $this->activate(1)->assertOk()->assertJson(['status' => 'active']);
        $this->assertEquals($before, Quarter::first()->updated_at);
    }

    public function test_only_configured_quarters_of_the_active_school_year_can_be_activated(): void
    {
        $next = SchoolYear::create(['name' => '2027-2028', 'start_date' => '2027-06-14', 'end_date' => '2028-04-14']);
        $this->saveQuarter(1, '2027-06-14', '2027-08-31', $next)->assertCreated();

        $this->activate(1, $next)->assertStatus(422)->assertJsonValidationErrors('quarter');
        $this->activate(3)->assertNotFound();
        $this->assertSame(0, Quarter::where('status', 'active')->count());
    }

    public function test_activating_leaves_other_school_years_alone(): void
    {
        $next = SchoolYear::create(['name' => '2027-2028', 'start_date' => '2027-06-14', 'end_date' => '2028-04-14']);
        $this->saveQuarter(1, '2027-06-14', '2027-08-31', $next)->assertCreated();
        Quarter::where('school_year_id', $next->id)->update(['status' => 'active']);
        $this->saveQuarter(1, '2026-06-15', '2026-09-25')->assertCreated();

        $this->activate(1)->assertOk();
        $this->assertSame(2, Quarter::where('status', 'active')->count());
    }

    public function test_single_active_quarter_constraint_still_holds(): void
    {
        $this->saveQuarter(1, '2026-06-15', '2026-09-25')->assertCreated();
        $this->saveQuarter(2, '2026-09-28', '2026-11-13')->assertCreated();

        $this->expectException(QueryException::class);
        Quarter::query()->update(['status' => 'active']);
    }

    private function saveQuarter(int $number, string $start, string $end, ?SchoolYear $year = null)
    {
        $year ??= $this->year;

        return $this->actingAs($this->tina)->putJson("/api/school-years/{$year->id}/quarters/{$number}", ['startDate' => $start, 'endDate' => $end]);
    }

    public function test_creates_then_edits_a_quarter_in_place(): void
    {
        $this->saveQuarter(2, '2026-09-28', '2026-11-13')->assertCreated()->assertExactJson([
            'quarterId' => Quarter::value('id'), 'schoolYearId' => $this->year->id, 'quarterNumber' => 2,
            'name' => '2nd Quarter', 'startDate' => '2026-09-28', 'endDate' => '2026-11-13', 'status' => 'upcoming',
        ]);

        // Editing keeps the row, its name and a status set elsewhere; a status in the body is ignored.
        Quarter::query()->update(['status' => 'active']);
        $this->actingAs($this->tina)->putJson("/api/school-years/{$this->year->id}/quarters/2", [
            'startDate' => '2026-09-29', 'endDate' => '2026-11-20', 'status' => 'completed', 'name' => 'Q2',
        ])->assertOk()->assertJson(['name' => '2nd Quarter', 'startDate' => '2026-09-29', 'endDate' => '2026-11-20', 'status' => 'active']);

        $this->assertSame(1, Quarter::count());
    }

    public function test_lists_only_the_school_years_own_quarters_in_order(): void
    {
        $next = SchoolYear::create(['name' => '2027-2028', 'start_date' => '2027-06-14', 'end_date' => '2028-04-14']);
        $this->saveQuarter(2, '2026-09-28', '2026-11-13')->assertCreated();
        $this->saveQuarter(1, '2026-06-15', '2026-09-25')->assertCreated();
        $this->saveQuarter(1, '2027-06-14', '2027-08-31', $next)->assertCreated();

        $this->actingAs($this->tina)->getJson("/api/school-years/{$this->year->id}/quarters")->assertOk()
            ->assertJsonCount(2)->assertJsonPath('0.name', '1st Quarter')->assertJsonPath('1.name', '2nd Quarter');
        $this->actingAs($this->tina)->getJson("/api/school-years/{$next->id}/quarters")->assertOk()->assertJsonCount(1);
    }

    public function test_dates_must_stay_inside_the_school_year(): void
    {
        $this->saveQuarter(1, '2026-06-14', '2026-08-31')->assertStatus(422)->assertJsonValidationErrors('startDate');
        $this->saveQuarter(4, '2027-02-01', '2027-04-16')->assertStatus(422)->assertJsonValidationErrors('endDate');
        $this->saveQuarter(1, '2026-08-31', '2026-08-01')->assertStatus(422)->assertJsonValidationErrors('endDate');
        $this->saveQuarter(1, '2026-06-15', 'soon')->assertStatus(422)->assertJsonValidationErrors('endDate');
        $this->assertSame(0, Quarter::count());
    }

    public function test_quarters_keep_their_order_without_overlapping(): void
    {
        $this->saveQuarter(2, '2026-09-01', '2026-10-31')->assertCreated();

        // Q1 can't reach into Q2, and Q3 can't start before Q2 ends.
        $this->saveQuarter(1, '2026-06-15', '2026-09-01')->assertStatus(422)->assertJsonValidationErrors('endDate');
        $this->saveQuarter(3, '2026-10-31', '2026-12-31')->assertStatus(422)->assertJsonValidationErrors('startDate');
        // Q3 placed before Q2 is out of order even without overlapping.
        $this->saveQuarter(3, '2026-06-15', '2026-08-31')->assertStatus(422)->assertJsonValidationErrors('startDate');

        $this->saveQuarter(1, '2026-06-15', '2026-08-31')->assertCreated();
        $this->saveQuarter(3, '2026-11-01', '2026-12-31')->assertCreated();
        $this->assertSame(3, Quarter::count());
    }

    public function test_only_quarters_one_to_four_of_a_real_year(): void
    {
        $this->putJson("/api/school-years/{$this->year->id}/quarters/1", ['startDate' => '2026-06-15', 'endDate' => '2026-08-31'])->assertUnauthorized();
        $this->saveQuarter(5, '2026-06-15', '2026-08-31')->assertNotFound();
        $this->actingAs($this->tina)->putJson('/api/school-years/999/quarters/1', ['startDate' => '2026-06-15', 'endDate' => '2026-08-31'])->assertNotFound();
    }

    public function test_quarters_need_the_school_years_dates_first(): void
    {
        $undated = SchoolYear::create(['name' => '2027-2028']);

        $this->actingAs($this->tina)->putJson("/api/school-years/{$undated->id}/quarters/1", ['startDate' => '2027-06-14', 'endDate' => '2027-08-31'])
            ->assertStatus(422)->assertJsonValidationErrors(['startDate' => "Set the school year's start and end dates first."]);
        $this->assertSame(0, Quarter::where('school_year_id', $undated->id)->count());
    }

    private function saveAll(array $quarters, ?SchoolYear $year = null)
    {
        $year ??= $this->year;

        return $this->actingAs($this->tina)->putJson("/api/school-years/{$year->id}/quarters", ['quarters' => $quarters]);
    }

    private function q(?string $start, ?string $end): array
    {
        return ['startDate' => $start, 'endDate' => $end];
    }

    public function test_saves_all_quarters_in_one_request_without_activating_any(): void
    {
        $this->saveQuarter(1, '2026-06-15', '2026-08-28')->assertCreated();
        Quarter::query()->update(['status' => 'active']);

        // Q1 edited, Q2 and Q3 created, Q4 left blank (stays unconfigured).
        $this->saveAll([
            $this->q('2026-06-15', '2026-08-31'),
            $this->q('2026-09-01', '2026-11-13'),
            $this->q('2026-11-16', '2027-01-29'),
            $this->q(null, null),
        ])->assertOk()
            ->assertJsonCount(3)
            ->assertJsonPath('0.endDate', '2026-08-31')
            ->assertJsonPath('0.status', 'active')
            ->assertJsonPath('1.name', '2nd Quarter')
            ->assertJsonPath('1.status', 'upcoming')
            ->assertJsonPath('2.status', 'upcoming');

        $this->assertSame([1 => 'active', 2 => 'upcoming', 3 => 'upcoming'], $this->statuses());
    }

    public function test_save_all_checks_bounds_order_and_overlap_and_saves_nothing_on_error(): void
    {
        $valid = [
            $this->q('2026-06-15', '2026-08-31'),
            $this->q('2026-09-01', '2026-11-13'),
            $this->q('2026-11-16', '2027-01-29'),
            $this->q('2027-02-01', '2027-04-15'),
        ];
        $with = fn (int $i, array $q) => array_replace($valid, [$i => $q]);

        // Outside the school year.
        $this->saveAll($with(0, $this->q('2026-06-14', '2026-08-31')))->assertStatus(422)->assertJsonValidationErrors('quarters.0.startDate');
        $this->saveAll($with(3, $this->q('2027-02-01', '2027-04-16')))->assertStatus(422)->assertJsonValidationErrors('quarters.3.endDate');
        // End not after start.
        $this->saveAll($with(1, $this->q('2026-09-01', '2026-09-01')))->assertStatus(422)->assertJsonValidationErrors('quarters.1.endDate');
        // Q2 overlapping Q3, and Q3 placed before Q2.
        $this->saveAll($with(1, $this->q('2026-09-01', '2026-11-20')))->assertStatus(422)->assertJsonValidationErrors('quarters.1.endDate');
        $this->saveAll($with(2, $this->q('2026-06-01', '2026-06-10')))->assertStatus(422)->assertJsonValidationErrors('quarters.2.startDate');
        // One date without the other, and the wrong shape.
        $this->saveAll($with(2, $this->q('2026-11-16', null)))->assertStatus(422)->assertJsonValidationErrors('quarters.2.endDate');
        $this->saveAll(array_slice($valid, 0, 3))->assertStatus(422)->assertJsonValidationErrors('quarters');
        $this->assertSame(0, Quarter::count());

        $this->saveAll($valid)->assertOk()->assertJsonCount(4);

        // A configured quarter can't be blanked.
        $this->saveAll($with(3, $this->q(null, null)))->assertStatus(422)
            ->assertJsonValidationErrors(['quarters.3.startDate' => "4th Quarter is already configured, so its dates can't be removed."]);
        $this->assertSame(4, Quarter::count());
    }

    public function test_save_all_needs_the_school_years_dates_first(): void
    {
        $undated = SchoolYear::create(['name' => '2027-2028']);

        $this->saveAll([$this->q('2027-06-14', '2027-08-31'), $this->q(null, null), $this->q(null, null), $this->q(null, null)], $undated)
            ->assertStatus(422)->assertJsonValidationErrors(['quarters' => "Set the school year's start and end dates first."]);
    }

    public function test_activation_takes_the_quarter_lock(): void
    {
        Quarter::create(['school_year_id' => $this->year->id, 'quarter_number' => 1, 'name' => Quarter::NAMES[1], 'start_date' => '2026-06-15', 'end_date' => '2026-09-25', 'status' => 'upcoming']);

        $this->assertTrue($this->quarterLockFree());
        $this->activate(1)->assertOk();
        $this->assertFalse($this->quarterLockFree());
    }
}

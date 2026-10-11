<?php

namespace Tests\Feature;

use App\Models\SchoolYear;
use App\Models\Teacher;
use Database\Seeders\CurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Today's Schedule and the Modules board only use a timetable that applies today: active module
// assignment, active schedule, and today (Asia/Manila) within effective_from..effective_until.
class ScheduleValidityTest extends TestCase
{
    use RefreshDatabase;

    private Teacher $tina;

    private int $joy;

    private int $faith;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CurriculumSeeder::class);
        // Section timetables take the year's dates: valid 2026-06-16 through 2027-04-30.
        SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-16', 'end_date' => '2027-04-30', 'status' => 'active']);
        $this->tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $this->joy = $this->actingAs($this->tina)->postJson('/api/sections', ['gradeLevel' => 'Kinder 1', 'sectionName' => 'Joy'])->json('sectionId');
        $this->faith = $this->actingAs($this->tina)->postJson('/api/sections', ['gradeLevel' => 'Kinder 1', 'sectionName' => 'Faith'])->json('sectionId');
        // Faith is the afternoon class.
        DB::table('class_schedules')->whereIn('section_module_id', DB::table('section_modules')->where('section_id', $this->faith)->select('id'))
            ->update(['start_time' => DB::raw("start_time + interval '5 hours'"), 'end_time' => DB::raw("end_time + interval '5 hours'")]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $manilaTime): void
    {
        Carbon::setTestNow(Carbon::parse($manilaTime, 'Asia/Manila'));
    }

    /** @return array<string, string> module code => "start-end" from Today's Schedule */
    private function today(int $section): array
    {
        $codes = DB::table('modules')->pluck('code', 'id');

        return collect($this->actingAs($this->tina)->getJson("/api/sections/{$section}/todays-schedule")->assertOk()->json('moduleTimes'))
            ->mapWithKeys(fn ($t) => [$codes[$t['moduleId']] => "{$t['startTime']}-{$t['endTime']}"])->sortKeys()->all();
    }

    /** @return array<string, array{days: list<string>, time: ?string}> module name => its board schedule */
    private function board(int $section): array
    {
        return collect($this->actingAs($this->tina)->getJson("/api/modules?section={$section}")->assertOk()->json())
            ->mapWithKeys(fn ($m) => [$m['moduleName'] => [
                'days' => array_column($m['scheduleDays'], 'day'),
                'time' => $m['scheduleDays'] ? "{$m['scheduleDays'][0]['startTime']}-{$m['scheduleDays'][0]['endTime']}" : null,
            ]])->all();
    }

    private function joyModule(string $code): int
    {
        return DB::table('section_modules')->join('modules', 'modules.id', '=', 'section_modules.module_id')
            ->where('section_modules.section_id', $this->joy)->where('modules.code', $code)->value('section_modules.id');
    }

    public function test_valid_schedule_shows_per_section_and_per_weekday(): void
    {
        $this->at('2026-09-28 09:00'); // Monday

        // Only Monday's modules, each section with its own times.
        $this->assertSame(['language' => '9:15 AM-10:00 AM', 'reading' => '8:00 AM-9:00 AM', 'writing' => '10:00 AM-11:15 AM'], $this->today($this->joy));
        $this->assertSame(['language' => '2:15 PM-3:00 PM', 'reading' => '1:00 PM-2:00 PM', 'writing' => '3:00 PM-4:15 PM'], $this->today($this->faith));

        $this->assertSame(['days' => ['MONDAY', 'WEDNESDAY', 'FRIDAY'], 'time' => '8:00 AM-9:00 AM'], $this->board($this->joy)['Reading']);
        $this->assertSame(['days' => ['TUESDAY', 'THURSDAY'], 'time' => '1:00 PM-2:30 PM'], $this->board($this->faith)['MAPEH']);

        $this->at('2026-09-29 09:00'); // Tuesday
        $this->assertSame(['mapeh' => '8:00 AM-9:30 AM', 'math' => '9:45 AM-11:15 AM'], $this->today($this->joy));
    }

    public function test_inactive_module_assignment_hides_only_that_sections_schedule(): void
    {
        $this->at('2026-09-28 09:00');
        DB::table('section_modules')->where('id', $this->joyModule('reading'))->update(['is_active' => false]);

        $this->assertArrayNotHasKey('reading', $this->today($this->joy));
        $this->assertSame('1:00 PM-2:00 PM', $this->today($this->faith)['reading']);

        // Reading stays on Joy's board as shared curriculum, just without a timetable.
        $joyBoard = $this->board($this->joy);
        $this->assertSame(['days' => [], 'time' => null], $joyBoard['Reading']);
        $this->assertSame('9:15 AM-10:00 AM', $joyBoard['Language']['time']);
        $this->assertSame('1:00 PM-2:00 PM', $this->board($this->faith)['Reading']['time']);
    }

    public function test_inactive_schedule_is_hidden(): void
    {
        $this->at('2026-09-28 09:00');
        DB::table('class_schedules')->where('section_module_id', $this->joyModule('reading'))->update(['is_active' => false]);

        $this->assertSame(['language', 'writing'], array_keys($this->today($this->joy)));
        $this->assertSame([], $this->board($this->joy)['Reading']['days']);
        $this->assertSame('1:00 PM-2:00 PM', $this->board($this->faith)['Reading']['time']);
    }

    public function test_schedule_applies_only_within_its_effective_dates(): void
    {
        // Monday before the timetable starts.
        $this->at('2026-06-15 09:00');
        $this->assertSame([], $this->today($this->joy));
        $this->assertSame([], $this->board($this->joy)['Reading']['days']);

        // First day (Tuesday), 00:30 in Manila while UTC is still on Jun 15.
        $this->at('2026-06-16 00:30');
        $this->assertSame(['mapeh' => '8:00 AM-9:30 AM', 'math' => '9:45 AM-11:15 AM'], $this->today($this->joy));
        $this->assertSame('8:00 AM-9:00 AM', $this->board($this->joy)['Reading']['time']);

        // Last day (Friday), late evening in Manila.
        $this->at('2027-04-30 23:30');
        $this->assertSame('8:00 AM-9:00 AM', $this->today($this->joy)['reading']);
        $this->assertSame('1:00 PM-2:30 PM', $this->board($this->faith)['MAPEH']['time']);

        // Monday after it ended.
        $this->at('2027-05-03 09:00');
        $this->assertSame([], $this->today($this->joy));
        $this->assertSame([], $this->today($this->faith));
        $this->assertSame([], $this->board($this->joy)['Reading']['days']);
    }
}

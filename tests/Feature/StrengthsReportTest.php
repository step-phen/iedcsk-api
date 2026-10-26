<?php

namespace Tests\Feature;

use App\Models\SchoolYear;
use App\Models\Teacher;
use Database\Seeders\CurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StrengthsReportTest extends TestCase
{
    use RefreshDatabase;

    private Teacher $tina;

    private SchoolYear $year;

    private array $sections;

    private array $quarters;

    private int $gabriel;

    private int $gabrielId;

    private int $order = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CurriculumSeeder::class);
        $this->year = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $this->tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $this->sections = $this->setUpSections($this->tina, ['Joy', 'Faith'], $this->year);
        foreach ([1 => 'active', 2 => 'upcoming'] as $n => $status) {
            $this->quarters[$n] = DB::table('quarters')->insertGetId(['school_year_id' => $this->year->id, 'quarter_number' => $n, 'name' => "Q{$n}", 'start_date' => '2026-0'.(5 + $n).'-01', 'end_date' => '2026-0'.(5 + $n).'-28', 'status' => $status]);
        }
        [$this->gabrielId, $this->gabriel] = $this->enroll('Gabriel', $this->sections['Joy']);
    }

    // Returns [learnerId, enrollmentId].
    private function enroll(string $name, int $section, ?int $learnerId = null, ?SchoolYear $year = null): array
    {
        $learnerId ??= DB::table('learners')->insertGetId(['first_name' => $name, 'last_name' => 'Reyes', 'birth_date' => '2021-01-01', 'gender' => 'Male']);

        return [$learnerId, DB::table('learner_school_years')->insertGetId([
            'learner_id' => $learnerId, 'school_year_id' => ($year ?? $this->year)->id, 'section_id' => $section, 'enrolled_on' => '2026-06-01', 'status' => 'active',
        ])];
    }

    private function activity(string $module, string $domain, string $format = 'digital', int $quarter = 1): array
    {
        $this->order++;
        $moduleDomain = DB::table('module_domains')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->where(['modules.code' => $module, 'developmental_domains.code' => $domain])
            ->first(['module_domains.id', 'module_domains.domain_id']);
        $activity = DB::table('activities')->insertGetId(['lesson_id' => DB::table('lessons')->insertGetId([
            'module_domain_id' => $moduleDomain->id, 'title' => 'Lesson', 'quarter_number' => $quarter, 'week_number' => 1, 'sequence_order' => $this->order,
        ]), 'activity_code' => "S-{$this->order}", 'name' => "Game {$this->order}", 'instructions' => '-', 'activity_format' => $format, 'sequence_order' => 1]);

        return [$activity, $moduleDomain];
    }

    // A checklist skill, by default linked to a new game in the same quarter; returns [activityId, itemId].
    private function skill(string $module, string $domain, string $format = 'digital', int $quarter = 1, bool $linked = true, ?int $activityQuarter = null): array
    {
        [$activity, $moduleDomain] = $this->activity($module, $domain, $format, $activityQuarter ?? $quarter);
        $itemId = DB::table('developmental_checklist_items')->insertGetId([
            'domain_id' => $moduleDomain->domain_id, 'item' => "Skill {$this->order}", 'grade_level' => 'Kindergarten', 'quarter_number' => $quarter,
            'activity_id' => $linked ? $activity : null, 'display_order' => $this->order,
        ]);

        return [$activity, $itemId];
    }

    // One assignment with its attempts, oldest first.
    private function played(int $activity, array $attempts, int $playedQuarter = 1, ?int $enrollment = null, ?int $quarterId = null): void
    {
        $assignment = DB::table('learner_activity_assignments')->insertGetId([
            'enrollment_id' => $enrollment ?? $this->gabriel, 'activity_id' => $activity, 'quarter_id' => $quarterId ?? $this->quarters[$playedQuarter], 'teacher_id' => $this->tina->id,
            'assigned_at' => now(), 'status' => 'completed', 'completed_at' => now(),
        ]);
        foreach (array_values($attempts) as $i => $attempt) {
            $at = now()->subMinutes(10 - $i);
            DB::table('learner_activity_attempts')->insert($attempt + ['assignment_id' => $assignment, 'attempt_number' => $i + 1, 'started_at' => $at, 'status' => 'evaluated', 'evaluated_at' => $at]);
        }
    }

    private function rate(int $item, string $rating, int $quarter = 1, ?int $enrollment = null, ?int $quarterId = null): void
    {
        DB::table('learner_developmental_checklists')->insert([
            'enrollment_id' => $enrollment ?? $this->gabriel, 'checklist_item_id' => $item, 'quarter_id' => $quarterId ?? $this->quarters[$quarter],
            'teacher_id' => $this->tina->id, 'rating' => $rating, 'assessed_at' => now(),
        ]);
    }

    private function report(int $quarter = 1, string $section = 'Joy'): array
    {
        return $this->actingAs($this->tina)->getJson("/api/sections/{$this->sections[$section]}/classroom-record/strengths?quarter={$quarter}")->assertOk()->json();
    }

    private function entries(array $report, ?int $learnerId = null): array
    {
        return collect($report['learners'])->firstWhere('learnerId', $learnerId ?? $this->gabrielId)['entries'];
    }

    private function skillEntry(array $report, int $itemId, ?int $learnerId = null): ?array
    {
        return collect($this->entries($report, $learnerId))->first(fn ($e) => $e['kind'] === 'skill' && $e['itemId'] === $itemId);
    }

    private function activityEntry(array $report, int $activityId): ?array
    {
        return collect($this->entries($report))->first(fn ($e) => $e['kind'] === 'activity' && $e['activityId'] === $activityId);
    }

    private function module(array $report, int $moduleDomainId, ?int $learnerId = null): array
    {
        return collect(collect($report['learners'])->firstWhere('learnerId', $learnerId ?? $this->gabrielId)['modules'])->firstWhere('moduleDomainId', $moduleDomainId);
    }

    private function moduleId(string $code): int
    {
        return DB::table('modules')->where('code', $code)->value('id');
    }

    public function test_module_performance_is_the_classroom_record_total_over_assessed_activities(): void
    {
        [$high, $math] = $this->activity('math', 'math_readiness');
        [$practical] = $this->activity('math', 'math_readiness', 'practical');
        [$zero] = $this->activity('math', 'math_readiness');
        $this->activity('math', 'math_readiness');
        [$unrated] = $this->activity('math', 'math_readiness', 'practical');
        [$unfinished] = $this->activity('math', 'math_readiness');

        $this->played($high, [['score' => 4, 'max_score' => 5]]);
        $this->played($practical, [['practical_rating' => 3, 'evaluated_by' => $this->tina->id]]);
        // A valid zero is a result and counts.
        $this->played($zero, [['score' => 0, 'max_score' => 5]]);
        $this->played($unrated, [['status' => 'submitted', 'evaluated_at' => null, 'submitted_at' => now()]]);
        $this->played($unfinished, [['status' => 'in_progress', 'evaluated_at' => null]]);

        // 4 + 3 + 0 out of 5 + 5 + 5; the unplayed, unrated and unfinished ones stay out of the percent but in the coverage.
        $this->assertEquals(
            ['moduleDomainId' => $math->id, 'total' => 6, 'assessed' => 3, 'earned' => 7, 'possible' => 15, 'percent' => 47],
            $this->module($this->report(), $math->id),
        );

        // The same numbers as the Classroom Record's total for that module and quarter.
        $record = $this->actingAs($this->tina)->getJson("/api/sections/{$this->sections['Joy']}/classroom-record?moduleId={$this->moduleId('math')}&quarter=1")->assertOk()->json();
        $this->assertEquals(['score' => 7, 'maxScore' => 15], collect($record['rows'])->firstWhere('learnerId', $this->gabrielId)['total']);
    }

    public function test_nothing_assessed_is_null_not_zero_percent(): void
    {
        [$game, $math] = $this->activity('math', 'math_readiness');
        [, $ana] = $this->enroll('Ana', $this->sections['Joy']);
        $anaId = DB::table('learner_school_years')->where('id', $ana)->value('learner_id');
        $this->played($game, [['score' => 5, 'max_score' => 5]]);
        $writing = DB::table('module_domains')->join('modules', 'modules.id', '=', 'module_domains.module_id')->where('modules.code', 'writing')->value('module_domains.id');

        $report = $this->report();
        // A learner with no results is listed, every module not assessed.
        $this->assertSame(['total' => 1, 'assessed' => 0, 'percent' => null], array_intersect_key($this->module($report, $math->id, $anaId), array_flip(['assessed', 'total', 'percent'])));
        $this->assertSame([null], array_values(array_unique(array_column(collect($report['learners'])->firstWhere('learnerId', $anaId)['modules'], 'percent'))));
        // A module with no activities this quarter has nothing to divide by.
        $this->assertSame(['total' => 0, 'assessed' => 0, 'percent' => null], array_intersect_key($this->module($report, $writing), array_flip(['total', 'assessed', 'percent'])));
    }

    public function test_a_retry_counts_once_as_the_latest_finished_attempt(): void
    {
        [$game, $item] = $this->skill('math', 'math_readiness');
        $math = DB::table('lessons')->join('activities', 'activities.lesson_id', '=', 'lessons.id')->where('activities.id', $game)->value('lessons.module_domain_id');
        $this->played($game, [
            ['score' => 2, 'max_score' => 5],
            ['score' => 5, 'max_score' => 5],
            // A later try left unfinished does not replace the finished one.
            ['status' => 'abandoned', 'evaluated_at' => null],
        ]);

        $report = $this->report();
        $this->assertEquals([1, 5, 5, 100], array_values(array_intersect_key($this->module($report, $math), array_flip(['assessed', 'earned', 'possible', 'percent']))));
        $this->assertEquals(5, $this->skillEntry($report, $item)['result']['score']);
    }

    public function test_rated_practicals_count_out_of_five(): void
    {
        [$veryGood, $arts] = $this->activity('mapeh', 'arts_crafts', 'practical');
        [$needsHelp] = $this->activity('mapeh', 'arts_crafts', 'practical');
        $this->played($veryGood, [['practical_rating' => 5, 'evaluated_by' => $this->tina->id]]);
        $this->played($needsHelp, [['practical_rating' => 2, 'evaluated_by' => $this->tina->id]]);

        $this->assertEquals([2, 7, 10, 70], array_values(array_intersect_key($this->module($this->report(), $arts->id), array_flip(['assessed', 'earned', 'possible', 'percent']))));
    }

    public function test_the_teachers_checklist_rating_is_the_only_skill_status(): void
    {
        [$pendingGame, $pending] = $this->skill('math', 'math_readiness');
        [$observedGame, $notObserved] = $this->skill('math', 'math_readiness');
        [, $needsSupport] = $this->skill('math', 'math_readiness', linked: false);
        [$achievedGame, $achieved] = $this->skill('math', 'math_readiness');
        [$unratedGame, $unrated] = $this->skill('math', 'math_readiness', 'practical');

        // A perfect score without a checklist rating is evidence only, not a teacher-assessed status.
        $this->played($pendingGame, [['score' => 5, 'max_score' => 5]]);
        // An explicit "not yet observed" stays as it is beside a perfect score.
        $this->rate($notObserved, 'not_yet_observed');
        $this->played($observedGame, [['score' => 5, 'max_score' => 5]]);
        $this->rate($needsSupport, 'needs_support');
        // A low score does not lower the teacher's rating either.
        $this->rate($achieved, 'achieved');
        $this->played($achievedGame, [['score' => 1, 'max_score' => 5]]);
        $this->played($unratedGame, [['status' => 'submitted', 'evaluated_at' => null, 'submitted_at' => now()]]);

        $report = $this->report();
        $status = fn (int $item) => $this->skillEntry($report, $item)['teacherStatus'];
        $this->assertSame([null, 'not_yet_observed', 'needs_support', 'achieved', null], [$status($pending), $status($notObserved), $status($needsSupport), $status($achieved), $status($unrated)]);
        $this->assertEquals(5, $this->skillEntry($report, $pending)['result']['score']);
        $this->assertEquals(5, $this->skillEntry($report, $notObserved)['result']['score']);
        $this->assertSame('not_yet_observed', $this->skillEntry($report, $notObserved)['checklist']['rating']);
        $this->assertSame([null, null], [$this->skillEntry($report, $needsSupport)['activityId'], $this->skillEntry($report, $needsSupport)['result']]);
        $this->assertSame('awaiting_rating', $this->skillEntry($report, $unrated)['status']);
        $this->assertArrayNotHasKey('group', $this->skillEntry($report, $pending));
    }

    public function test_an_unlinked_activity_is_activity_level_evidence_and_mapeh_domains_stay_separate(): void
    {
        [$game, $math] = $this->activity('math', 'math_readiness');
        [$linkedGame, $skill] = $this->skill('math', 'math_readiness');
        [, $arts] = $this->skill('mapeh', 'arts_crafts', 'practical');
        [, $motor] = $this->skill('mapeh', 'gross_motor', 'practical');
        $this->played($game, [['score' => 1, 'max_score' => 5]]);

        $report = $this->report();
        $entry = $this->activityEntry($report, $game);
        $this->assertSame([$math->id, null, null], [$entry['moduleDomainId'], $entry['itemId'], $entry['teacherStatus']]);
        $this->assertEquals(1, $entry['result']['score']);
        // A game a skill links to shows under that skill, not again as an activity.
        $this->assertNull($this->activityEntry($report, $linkedGame));
        $this->assertSame(2, $this->module($report, $math->id)['total']);

        $artsRow = $this->skillEntry($report, $arts)['moduleDomainId'];
        $motorRow = $this->skillEntry($report, $motor)['moduleDomainId'];
        $this->assertNotSame($artsRow, $motorRow);
        $rows = collect($report['rows'])->keyBy('moduleDomainId');
        $this->assertSame(['ARTS AND CRAFTS', 'GROSS MOTOR DEVELOPMENT'], [$rows[$artsRow]['subheading'], $rows[$motorRow]['subheading']]);
        $this->assertSame(
            ['LANGUAGE', 'READING', 'WRITING READINESS', 'MATH READINESS', 'MAPEH', 'MAPEH', 'MAPEH'],
            array_column($report['rows'], 'subject'),
        );
        $this->assertCount(7, collect($report['learners'])->firstWhere('learnerId', $this->gabrielId)['modules']);
    }

    public function test_each_quarter_uses_only_its_own_skills_ratings_and_activities(): void
    {
        // A Q1 skill linked to a Q2 game: the Q2 result says nothing about the Q1 skill or the Q1 module.
        [$q2game, $crossItem] = $this->skill('math', 'math_readiness', quarter: 1, activityQuarter: 2);
        $this->played($q2game, [['score' => 5, 'max_score' => 5]], 2);
        // A Q1 game replayed during a Q2 class still counts for Q1.
        [$replayed, $replayedItem] = $this->skill('math', 'math_readiness');
        $this->played($replayed, [['score' => 2, 'max_score' => 5]], 2);
        $math = DB::table('lessons')->join('activities', 'activities.lesson_id', '=', 'lessons.id')->where('activities.id', $replayed)->value('lessons.module_domain_id');
        // A Q2 rating of a Q1 skill does not count in Q1 (its unlinked game adds one unplayed Q1 activity).
        [, $ratedLater] = $this->skill('math', 'math_readiness', linked: false);
        $this->rate($ratedLater, 'achieved', 2);
        [, $q2item] = $this->skill('math', 'math_readiness', quarter: 2, linked: false);

        $q1 = $this->report(1);
        $cross = $this->skillEntry($q1, $crossItem);
        $this->assertSame([null, 2], [$cross['result'], $cross['linkedActivityQuarter']]);
        $this->assertEquals(2, $this->skillEntry($q1, $replayedItem)['result']['score']);
        $this->assertSame([2, 1, 40], array_values(array_intersect_key($this->module($q1, $math), array_flip(['total', 'assessed', 'percent']))));
        $this->assertNull($this->skillEntry($q1, $ratedLater)['teacherStatus']);
        $this->assertNull($this->skillEntry($q1, $q2item));
        $this->assertNull($this->activityEntry($q1, $q2game));

        $q2 = $this->report(2);
        $this->assertNotNull($this->skillEntry($q2, $q2item));
        $this->assertNull($this->skillEntry($q2, $crossItem));
        // In Q2 no Q2 skill links that game, so it is activity-level evidence there.
        $this->assertEquals(5, $this->activityEntry($q2, $q2game)['result']['score']);
        $this->assertSame([2, 1, 100], array_values(array_intersect_key($this->module($q2, $math), array_flip(['total', 'assessed', 'percent']))));
    }

    public function test_only_this_sections_enrollments_in_this_school_year_count_and_nothing_is_written(): void
    {
        [$game, $item] = $this->skill('math', 'math_readiness');
        $math = DB::table('lessons')->join('activities', 'activities.lesson_id', '=', 'lessons.id')->where('activities.id', $game)->value('lessons.module_domain_id');
        [$faithId, $faith] = $this->enroll('Faith', $this->sections['Faith']);
        $this->played($game, [['score' => 5, 'max_score' => 5]], enrollment: $faith);

        // Gabriel's results and ratings from last school year stay with that year's enrollment.
        $old = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-06-01', 'end_date' => '2026-03-31', 'status' => 'completed']);
        $oldQuarter = DB::table('quarters')->insertGetId(['school_year_id' => $old->id, 'quarter_number' => 1, 'name' => 'Q1', 'start_date' => '2025-06-01', 'end_date' => '2025-08-31', 'status' => 'completed']);
        [, $lastYear] = $this->enroll('Gabriel', $this->setUpSections($this->tina, ['Joy'], $old)['Joy'], $this->gabrielId, $old);
        $this->played($game, [['score' => 5, 'max_score' => 5]], enrollment: $lastYear, quarterId: $oldQuarter);
        $this->rate($item, 'achieved', enrollment: $lastYear, quarterId: $oldQuarter);

        $before = [DB::table('learner_activity_attempts')->count(), DB::table('learner_developmental_checklists')->count()];
        $report = $this->report();
        $this->assertSame([$this->gabrielId], array_column($report['learners'], 'learnerId'));
        $this->assertSame([null, null], [$this->skillEntry($report, $item)['teacherStatus'], $this->skillEntry($report, $item)['result']]);
        $this->assertNull($this->module($report, $math)['percent']);
        $this->assertSame(100, $this->module($this->report(1, 'Faith'), $math, $faithId)['percent']);
        $this->assertSame($before, [DB::table('learner_activity_attempts')->count(), DB::table('learner_developmental_checklists')->count()]);

        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        $this->actingAs($omar)->getJson("/api/sections/{$this->sections['Joy']}/classroom-record/strengths")->assertNotFound();
    }
}

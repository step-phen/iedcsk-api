<?php

namespace Tests\Feature;

use App\Models\SchoolYear;
use App\Models\Teacher;
use App\Support\ClassSession;
use Database\Seeders\CurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// The lesson cards' "Done" / "In Progress" badges on the Modules board, per selected section. The badge is
// classroom delivery (did the teacher finish teaching every activity); the count is learners who finished them all.
class LessonCompletionTest extends TestCase
{
    use RefreshDatabase;

    private Teacher $tina;

    private SchoolYear $year;

    /** @var array<string, int> section name => id */
    private array $sections;

    private int $q1;

    private int $reading;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CurriculumSeeder::class);
        $this->year = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        // Q2 is open for classes, so Q1's unused lessons can still be renamed (Step 1).
        foreach ([1 => 'upcoming', 2 => 'active'] as $number => $status) {
            $id = DB::table('quarters')->insertGetId(['school_year_id' => $this->year->id, 'quarter_number' => $number, 'name' => "Q{$number}", 'start_date' => '2026-06-01', 'end_date' => '2026-12-31', 'status' => $status]);
            $this->q1 ??= $id;
        }
        $this->tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $this->sections = $this->setUpSections($this->tina);
        $this->reading = DB::table('module_domains')->where('module_id', DB::table('modules')->where('code', 'reading')->value('id'))->value('id');
    }

    private function enroll(string $name, string|int $section, string $status = 'active', ?int $year = null): int
    {
        return DB::table('learner_school_years')->insertGetId([
            'learner_id' => DB::table('learners')->insertGetId(['first_name' => $name, 'last_name' => 'Cruz', 'birth_date' => '2021-01-01']),
            'school_year_id' => $year ?? $this->year->id, 'section_id' => $this->sections[$section] ?? $section, 'enrolled_on' => '2026-06-01', 'status' => $status,
            'withdrawn_on' => $status === 'withdrawn' ? '2026-06-01' : null,
        ]);
    }

    private function lesson(string $title, int $week = 1, int $day = 1, ?int $domain = null): int
    {
        return DB::table('lessons')->insertGetId(['module_domain_id' => $domain ?? $this->reading, 'title' => $title, 'quarter_number' => 1, 'week_number' => $week, 'day_of_week' => $day, 'sequence_order' => 1]);
    }

    private function activity(int $lesson, string $format = 'digital', bool $active = true): int
    {
        static $n = 0;

        return DB::table('activities')->insertGetId(['lesson_id' => $lesson, 'activity_code' => 'A-'.++$n, 'name' => "Activity {$n}", 'instructions' => '-', 'activity_format' => $format, 'sequence_order' => 1, 'is_active' => $active]);
    }

    /** One assignment with the given attempts, e.g. ['evaluated', 'score' => 0] or ['submitted'] or ['evaluated', 'rating' => 3]. */
    private function work(int $enrollment, int $activity, array ...$attempts): void
    {
        $quarter = DB::table('quarters')->where('school_year_id', DB::table('learner_school_years')->where('id', $enrollment)->value('school_year_id'))->value('id');
        $done = collect($attempts)->contains(fn ($a) => in_array($a[0], ['evaluated', 'submitted'], true));
        $assignment = DB::table('learner_activity_assignments')->insertGetId([
            'enrollment_id' => $enrollment, 'activity_id' => $activity, 'quarter_id' => $quarter, 'teacher_id' => $this->tina->id,
            'assigned_at' => '2026-07-01 08:00', 'status' => $done ? 'completed' : 'in_progress', 'completed_at' => $done ? '2026-07-01 09:00' : null,
        ]);
        foreach ($attempts as $i => $a) {
            $rated = isset($a['rating']);
            DB::table('learner_activity_attempts')->insert([
                'assignment_id' => $assignment, 'attempt_number' => $i + 1, 'status' => $a[0], 'started_at' => '2026-07-01 08:00',
                'score' => $a[0] === 'evaluated' && ! $rated ? ($a['score'] ?? 2) : null,
                'max_score' => $a[0] === 'evaluated' && ! $rated ? 2 : null,
                'practical_rating' => $a['rating'] ?? null, 'evaluated_by' => $rated ? $this->tina->id : null,
                'submitted_at' => $a[0] === 'in_progress' || $a[0] === 'abandoned' ? null : '2026-07-01 09:00',
            ]);
        }
    }

    /**
     * The teacher showed these activities in one class of the section: 'completed' once moved past or the class
     * ended, 'active' while one is still on screen (one activity only). Returns the class's id.
     */
    private function conduct(array $activities, string $status = 'completed', string|int $section = 'Joy'): int
    {
        $open = $status === 'active';
        $module = DB::table('activities')->join('lessons', 'lessons.id', '=', 'activities.lesson_id')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->where('activities.id', $activities[0])->value('module_domains.module_id');
        $times = ['started_at' => '2026-07-01 08:00', 'ended_at' => $open ? null : '2026-07-01 09:00'];
        $session = DB::table('classroom_sessions')->insertGetId([
            'section_module_id' => DB::table('section_modules')->where(['section_id' => $this->sections[$section] ?? $section, 'module_id' => $module])->value('id'),
            'quarter_id' => $this->q1, 'teacher_id' => $this->tina->id, 'session_date' => '2026-07-01',
            'planned_start_time' => '08:00', 'planned_end_time' => '09:00', 'status' => $open ? 'ongoing' : 'completed',
        ] + $times);
        foreach ($activities as $i => $activity) {
            $sessionLesson = DB::table('classroom_session_lessons')->insertGetId([
                'classroom_session_id' => $session, 'lesson_id' => DB::table('activities')->where('id', $activity)->value('lesson_id'), 'sequence_order' => $i + 1, 'status' => $status,
            ] + $times);
            DB::table('classroom_session_activities')->insert([
                'session_lesson_id' => $sessionLesson, 'activity_id' => $activity, 'sequence_order' => 1, 'assigned_by' => $this->tina->id, 'assigned_at' => '2026-07-01 08:00', 'status' => $status,
            ] + $times);
        }

        return $session;
    }

    /** lesson id => its completion on the board, for one section (or none). */
    private function board(?string $section = 'Joy'): array
    {
        $query = $section ? '?section='.$this->sections[$section] : '';

        return collect($this->actingAs($this->tina)->getJson("/api/modules{$query}")->assertOk()->json())
            ->flatMap(fn ($m) => $m['lessons'])->mapWithKeys(fn ($l) => [$l['id'] => $l['completion']])->all();
    }

    private function badge(int $lesson, string $section = 'Joy'): ?array
    {
        return $this->board($section)[$lesson];
    }

    public function test_learner_counts_for_digital_and_practical_activities_and_done_once_taught(): void
    {
        $ana = $this->enroll('Ana', 'Joy');
        $ben = $this->enroll('Ben', 'Joy');
        $lesson = $this->lesson('Letter A');
        $game = $this->activity($lesson);
        $hop = $this->activity($lesson, 'practical');

        // Nothing started: no badge, but the count still knows the class size.
        $this->assertSame(['status' => null, 'completed' => 0, 'total' => 2], $this->badge($lesson));

        // A digital score of 0 still finishes the game; the unrated practical isn't finished.
        $this->work($ana, $game, ['evaluated', 'score' => 0]);
        $this->work($ana, $hop, ['submitted']);
        $this->assertSame(['status' => 'in_progress', 'completed' => 0, 'total' => 2], $this->badge($lesson));

        // Rated: Ana completed it, Ben hasn't.
        DB::table('learner_activity_attempts')->where('status', 'submitted')->update(['status' => 'evaluated', 'practical_rating' => 2, 'evaluated_by' => $this->tina->id]);
        $this->assertSame(['status' => 'in_progress', 'completed' => 1, 'total' => 2], $this->badge($lesson));

        // Ben retried the game three times; repeated attempts count him once.
        $this->work($ben, $game, ['abandoned'], ['evaluated', 'score' => 1], ['evaluated', 'score' => 2]);
        $this->assertSame(['status' => 'in_progress', 'completed' => 1, 'total' => 2], $this->badge($lesson));
        $this->work($ben, $hop, ['evaluated', 'rating' => 5]);
        // Every learner finishing isn't Done; the teacher finishing the class is.
        $this->assertSame(['status' => 'in_progress', 'completed' => 2, 'total' => 2], $this->badge($lesson));
        $this->conduct([$game, $hop]);
        $this->assertSame(['status' => 'done', 'completed' => 2, 'total' => 2], $this->badge($lesson));

        // A new practical activity is required too, so the lesson is no longer done.
        $this->activity($lesson, 'practical');
        $this->assertSame(['status' => 'in_progress', 'completed' => 0, 'total' => 2], $this->badge($lesson));
    }

    public function test_an_absent_learner_does_not_hold_back_done(): void
    {
        $ana = $this->enroll('Ana', 'Joy');
        $this->enroll('Ben', 'Joy');
        $lesson = $this->lesson('Letter H');
        $game = $this->activity($lesson);
        $hop = $this->activity($lesson, 'practical');

        // Ben was absent; the teacher taught both activities and Ana finished them.
        $this->conduct([$game, $hop]);
        $this->work($ana, $game, ['evaluated']);
        $this->work($ana, $hop, ['evaluated', 'rating' => 3]);
        $this->assertSame(['status' => 'done', 'completed' => 1, 'total' => 2], $this->badge($lesson));
    }

    public function test_ending_a_class_finishes_only_the_activities_it_showed(): void
    {
        $ana = $this->enroll('Ana', 'Joy');
        $lesson = $this->lesson('Letter B');
        $game = $this->activity($lesson);
        $hop = $this->activity($lesson, 'practical');

        // The game is on screen and Ana was given it but hasn't touched it: in progress, and protected content.
        $session = $this->conduct([$game], 'active');
        DB::table('learner_activity_assignments')->insert([
            'enrollment_id' => $ana, 'activity_id' => $game, 'quarter_id' => $this->q1, 'teacher_id' => $this->tina->id,
            'session_activity_id' => DB::table('classroom_session_activities')->where('activity_id', $game)->value('id'), 'assigned_at' => '2026-07-01 08:00', 'status' => 'assigned',
        ]);
        $lessonRow = collect($this->actingAs($this->tina)->getJson("/api/modules?section={$this->sections['Joy']}")->json())->flatMap(fn ($m) => $m['lessons'])->firstWhere('id', $lesson);
        $this->assertSame([true, ['status' => 'in_progress', 'completed' => 0, 'total' => 1]], [$lessonRow['hasRecords'], $lessonRow['completion']]);

        // Ending the class closes the game, but the practical was never shown: still in progress.
        ClassSession::end($session);
        $this->assertSame('completed', DB::table('classroom_session_activities')->where('activity_id', $game)->value('status'));
        $this->assertFalse(DB::table('classroom_session_activities')->where('activity_id', $hop)->exists());
        $this->assertSame(['status' => 'in_progress', 'completed' => 0, 'total' => 1], $this->badge($lesson));

        // Once the practical is taught too the lesson is done, though Ana finished nothing.
        $this->conduct([$hop]);
        $this->assertSame(['status' => 'done', 'completed' => 0, 'total' => 1], $this->badge($lesson));
    }

    public function test_empty_cases_never_show_done(): void
    {
        $ana = $this->enroll('Ana', 'Joy');
        $empty = $this->lesson('No activities yet');
        $this->assertSame(['status' => null, 'completed' => 0, 'total' => 1], $this->badge($empty));

        // Faith has only a withdrawn learner, who finished everything: no applicable learners and no class.
        $gone = $this->enroll('Gil', 'Faith', 'withdrawn');
        $lesson = $this->lesson('Letter C');
        $game = $this->activity($lesson);
        $this->work($gone, $game, ['evaluated']);
        $this->work($ana, $game, ['evaluated']);
        $this->conduct([$game]);
        $this->assertSame(['status' => null, 'completed' => 0, 'total' => 0], $this->badge($lesson, 'Faith'));
        $this->assertSame(['status' => 'done', 'completed' => 1, 'total' => 1], $this->badge($lesson));

        // Without ?section= there is no section to measure.
        $this->assertNull($this->board(null)[$lesson]);
    }

    public function test_inactive_activities_and_enrollments_are_left_out_and_late_enrollments_count_without_undoing_done(): void
    {
        $ana = $this->enroll('Ana', 'Joy');
        $this->work($this->enroll('Dan', 'Joy', 'transferred'), $game = $this->activity($lesson = $this->lesson('Letter D')), ['evaluated']);
        $removed = $this->activity($lesson, 'practical', false);

        // Dan transferred out and isn't counted; touching the removed activity isn't participation.
        $this->work($ana, $removed, ['in_progress']);
        $this->assertSame(['status' => null, 'completed' => 0, 'total' => 1], $this->badge($lesson));
        $this->work($ana, $game, ['evaluated']);
        $this->assertSame(['status' => 'in_progress', 'completed' => 1, 'total' => 1], $this->badge($lesson));

        // The removed activity isn't required, so teaching the game alone is done.
        $this->conduct([$game]);
        $this->assertSame(['status' => 'done', 'completed' => 1, 'total' => 1], $this->badge($lesson));

        // A learner enrolled late still owes the lesson: counted, but the class was already taught.
        $this->enroll('Lia', 'Joy');
        $this->assertSame(['status' => 'done', 'completed' => 1, 'total' => 2], $this->badge($lesson));
    }

    public function test_same_named_lessons_in_a_week_are_one_card(): void
    {
        $ana = $this->enroll('Ana', 'Joy');
        $monday = $this->lesson('Letter E', 1, 1);
        $wednesday = $this->lesson(' letter e ', 1, 3);
        $nextWeek = $this->lesson('Letter E', 2, 1);
        $mon = $this->activity($monday);
        $wed = $this->activity($wednesday, 'practical');
        $later = $this->activity($nextWeek);

        // Teaching Monday's part alone leaves the merged card in progress; both rows report the same card.
        $this->work($ana, $mon, ['evaluated']);
        $this->conduct([$mon]);
        $board = $this->board();
        $this->assertSame(['status' => 'in_progress', 'completed' => 0, 'total' => 1], $board[$monday]);
        $this->assertSame($board[$monday], $board[$wednesday]);
        // Another week with the same title is its own card.
        $this->assertSame(['status' => null, 'completed' => 0, 'total' => 1], $board[$nextWeek]);

        $this->work($ana, $wed, ['evaluated', 'rating' => 3]);
        $this->conduct([$wed]);
        $board = $this->board();
        $this->assertSame(['status' => 'done', 'completed' => 1, 'total' => 1], $board[$monday]);
        $this->assertSame($board[$monday], $board[$wednesday]);
        $this->conduct([$later]);
        $this->assertSame('done', $this->badge($nextWeek)['status']);

        // The same title in another MAPEH domain is a different card.
        $mapeh = DB::table('module_domains')->where('module_id', DB::table('modules')->where('code', 'mapeh')->value('id'))->orderBy('id')->pluck('id');
        $music = $this->lesson('Rhythm', 1, 2, $mapeh[0]);
        $arts = $this->lesson('Rhythm', 1, 2, $mapeh[1]);
        $this->conduct([$this->activity($music)]);
        $this->activity($arts);
        $board = $this->board();
        $this->assertSame(['done', null], [$board[$music]['status'], $board[$arts]['status']]);
    }

    public function test_renaming_an_unused_lesson_regroups_its_card_and_used_lessons_stay_protected(): void
    {
        $ana = $this->enroll('Ana', 'Joy');
        $ben = $this->enroll('Ben', 'Joy');
        $used = $this->lesson('Letter F', 1, 1);
        $game = $this->activity($used);
        $this->conduct([$game]);
        $this->work($ana, $game, ['evaluated']);
        $this->work($ben, $game, ['evaluated']);
        $unused = $this->lesson('Shapes', 1, 5);
        $this->activity($unused, 'practical');

        $board = $this->board();
        $this->assertSame(['status' => 'done', 'completed' => 2, 'total' => 2], $board[$used]);
        $this->assertSame(['status' => null, 'completed' => 0, 'total' => 2], $board[$unused]);

        // Merging the unused lesson into the used one's card adds its untaught activity to the card: no longer done.
        $this->actingAs($this->tina)->patchJson("/api/lessons/{$unused}", ['title' => 'Letter F'])->assertOk();
        $board = $this->board();
        $this->assertSame(['status' => 'in_progress', 'completed' => 0, 'total' => 2], $board[$used]);
        $this->assertSame($board[$used], $board[$unused]);

        // Splitting it off again restores both cards.
        $this->actingAs($this->tina)->patchJson("/api/lessons/{$unused}", ['title' => 'Shapes'])->assertOk();
        $board = $this->board();
        $this->assertSame(['status' => 'done', 'completed' => 2, 'total' => 2], $board[$used]);
        $this->assertSame(['status' => null, 'completed' => 0, 'total' => 2], $board[$unused]);

        // The used lesson itself still can't be renamed (Step 2).
        $this->actingAs($this->tina)->patchJson("/api/lessons/{$used}", ['title' => 'Shapes'])->assertStatus(422)->assertJsonValidationErrors('protected');
        $this->assertSame('Letter F', DB::table('lessons')->where('id', $used)->value('title'));
    }

    public function test_other_sections_and_earlier_years_never_count_and_the_board_writes_nothing(): void
    {
        $ana = $this->enroll('Ana', 'Joy');
        $lesson = $this->lesson('Letter G');
        $game = $this->activity($lesson);

        // Faith was taught it and finished it, in the same year.
        $this->work($this->enroll('Cara', 'Faith'), $game, ['evaluated']);
        $this->conduct([$game], 'completed', 'Faith');
        // An old year's section was taught it and its learner finished it too.
        $old = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-06-01', 'end_date' => '2026-03-31', 'status' => 'completed']);
        DB::table('quarters')->insert(['school_year_id' => $old->id, 'quarter_number' => 1, 'name' => 'Q1', 'start_date' => '2025-06-01', 'end_date' => '2025-08-31', 'status' => 'completed']);
        $hopeOld = DB::table('sections')->insertGetId(['school_year_id' => $old->id, 'teacher_id' => $this->tina->id, 'name' => 'Old Hope', 'grade_level' => 'Kindergarten']);
        DB::table('section_modules')->insert(['section_id' => $hopeOld, 'module_id' => DB::table('modules')->where('code', 'reading')->value('id'), 'teacher_id' => $this->tina->id]);
        $this->work($this->enroll('Old', $hopeOld, 'completed', $old->id), $game, ['evaluated']);
        $this->conduct([$game], 'completed', $hopeOld);

        $this->assertSame(['status' => null, 'completed' => 0, 'total' => 1], $this->badge($lesson));
        $this->assertSame(['status' => 'done', 'completed' => 1, 'total' => 1], $this->badge($lesson, 'Faith'));

        // Reading the board changes no quarter and no learner result.
        $snapshot = fn () => collect(['quarters', 'school_years', 'learner_activity_assignments', 'learner_activity_attempts', 'classroom_sessions', 'learner_school_years'])
            ->mapWithKeys(fn ($t) => [$t => DB::table($t)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all()])->all();
        $before = $snapshot();
        $this->board();
        $this->board('Faith');
        $this->assertSame($before, $snapshot());
        $this->assertSame($ana, DB::table('learner_school_years')->where('section_id', $this->sections['Joy'])->value('id'));
    }
}

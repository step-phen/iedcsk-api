<?php

namespace Tests\Feature;

use App\Models\SchoolYear;
use App\Models\Teacher;
use Database\Seeders\CurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClassroomSessionTest extends TestCase
{
    use RefreshDatabase;

    private Teacher $tina;

    private int $joy;

    private int $lesson;

    /** @var array<string, int> first name => learner id */
    private array $kids = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Learner devices are browsers on the frontend, so their requests carry a session.
        $this->withHeader('Referer', 'http://localhost:3000/');

        $this->seed(CurriculumSeeder::class);
        $year = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $this->tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $this->joy = $this->actingAs($this->tina)->postJson('/api/sections', ['gradeLevel' => 'Kinder 1', 'sectionName' => 'Joy'])->json('sectionId');

        foreach (['Ana', 'Ben', 'Cara'] as $name) {
            $id = DB::table('learners')->insertGetId(['first_name' => $name, 'last_name' => 'Cruz', 'birth_date' => '2021-01-01']);
            DB::table('learner_school_years')->insert(['learner_id' => $id, 'school_year_id' => $year->id, 'section_id' => $this->joy, 'enrolled_on' => '2026-06-01', 'status' => 'active']);
            $this->kids[$name] = $id;
        }

        $readingDomain = DB::table('module_domains')->where('module_id', $this->reading())->value('id');
        $this->lesson = DB::table('lessons')->insertGetId(['module_domain_id' => $readingDomain, 'title' => 'Letter B', 'quarter_number' => 1, 'week_number' => 1, 'day_of_week' => 1, 'sequence_order' => 1]);
    }

    private function reading(): int
    {
        return DB::table('modules')->where('code', 'reading')->value('id');
    }

    private function quarter(): void
    {
        DB::table('quarters')->insert(['school_year_id' => DB::table('school_years')->value('id'), 'quarter_number' => 1, 'name' => '1st Quarter', 'start_date' => '2026-06-01', 'end_date' => '2026-12-31', 'status' => 'active']);
    }

    private function activity(string $code, string $format): int
    {
        $id = DB::table('activities')->insertGetId(['lesson_id' => $this->lesson, 'activity_code' => $code, 'name' => $code, 'instructions' => '-', 'activity_format' => $format, 'sequence_order' => 1]);
        if ($format === 'digital') {
            DB::table('digital_activity_details')->insert(['activity_id' => $id, 'game_type' => 'tap_choice', 'max_score' => 2]);
            $question = fn (string $key, int $order, string $letter, bool $gradable) => DB::table('activity_questions')->insert([
                'activity_id' => $id, 'question_key' => $key, 'question_order' => $order,
                'question_data' => json_encode(['letter' => $letter, 'gradable' => $gradable]), 'correct_answer' => json_encode(['answer' => $letter]),
            ]);
            $question('q1', 1, 'B', true);
            $question('q2', 2, 'b', true);
            $question('q3', 3, 'd', false);
        } else {
            DB::table('practical_activity_details')->insert(['activity_id' => $id, 'demo_video_url' => '/demonstrations/hop.mp4', 'teacher_guidelines' => '-']);
        }

        return $id;
    }

    private function start(int $activityId)
    {
        return $this->actingAs($this->tina)->postJson("/api/sections/{$this->joy}/classroom/start", [
            'moduleId' => $this->reading(), 'lessonId' => $this->lesson, 'activityId' => $activityId,
        ]);
    }

    // A learner device: no teacher login, only its own session.
    private function device(): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        return $this;
    }

    public function test_teacher_runs_a_class_and_a_learner_plays_a_digital_activity(): void
    {
        $game = $this->activity('RD-Q1W1-01', 'digital');
        $other = $this->activity('RD-Q1W1-02', 'digital');

        $this->device()->getJson('/api/classroom-session')->assertOk()->assertExactJson(['active' => false]);
        $this->getJson('/api/classroom-session/learners')->assertExactJson(['learners' => []]);

        // Another teacher can't see or run this section; no active quarter blocks starting.
        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        $this->actingAs($omar)->postJson("/api/sections/{$this->joy}/classroom/start", ['moduleId' => $this->reading(), 'lessonId' => $this->lesson, 'activityId' => $game])->assertNotFound();
        $this->start($game)->assertStatus(422)->assertJsonValidationErrors('activityId');
        $this->quarter();
        $this->start(99999)->assertStatus(422)->assertJsonValidationErrors('activityId');

        // A class can't start until every learner is marked.
        $this->start($game)->assertStatus(422)->assertJsonValidationErrors('activityId');

        // Attendance marked before class is copied into the class roster.
        $this->actingAs($this->tina)->putJson("/api/sections/{$this->joy}/attendance", ['entries' => [
            ['learnerId' => $this->kids['Ana'], 'status' => 'PRESENT'],
            ['learnerId' => $this->kids['Ben'], 'status' => 'ABSENT'],
        ]])->assertOk();
        $this->start($game)->assertStatus(422)->assertJsonValidationErrors('activityId');
        $this->actingAs($this->tina)->putJson("/api/sections/{$this->joy}/attendance", ['entries' => [['learnerId' => $this->kids['Cara'], 'status' => 'ABSENT']]])->assertOk();
        $this->actingAs($this->tina)->putJson("/api/sections/{$this->joy}/attendance", ['entries' => [['learnerId' => 99999, 'status' => 'PRESENT']]])
            ->assertStatus(422)->assertJsonValidationErrors('entries.0.learnerId');

        // Before class, today's present learners can already pick their name and photo and wait.
        DB::table('learners')->where('id', $this->kids['Ana'])->update(['avatar_url' => 'learners/ana.webp']);
        $this->device()->getJson('/api/classroom-session/learners')->assertExactJson(['learners' => [['id' => $this->kids['Ana'], 'firstName' => 'Ana', 'photoUrl' => '/storage/learners/ana.webp']]]);
        $this->postJson('/api/classroom-session/claim', ['learnerId' => $this->kids['Ben']])->assertForbidden();
        $this->postJson('/api/classroom-session/claim', ['learnerId' => $this->kids['Ana']])->assertOk();
        $this->getJson('/api/classroom-session/me')->assertOk()->assertJson(['firstName' => 'Ana', 'photoUrl' => '/storage/learners/ana.webp']);
        $this->getJson("/api/classroom-session?learnerId={$this->kids['Ana']}")->assertExactJson(['active' => false]);
        $this->postJson('/api/classroom-session/join', ['learnerId' => $this->kids['Ana']])->assertStatus(400);

        $this->start($game)->assertOk()->assertJsonStructure(['classroomSessionId', 'startedAt']);
        // The waiting device sees the class start without picking again.
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/classroom-session?learnerId={$this->kids['Ana']}")->assertJson(['active' => true, 'activityId' => $game]);
        $this->assertSame(['absent', 'absent', 'present'], DB::table('classroom_session_learners')->orderBy('attendance_status')->pluck('attendance_status')->all());

        $board = $this->actingAs($this->tina)->getJson("/api/sections/{$this->joy}/classroom")->assertOk()->json();
        $this->assertSame('RD-Q1W1-01', $board['activeSession']['activeActivityName']);
        $this->assertSame('digital', $board['activeSession']['activeActivityFormat']);
        $this->assertNotNull($board['activeSession']['activeLessonTitle']);
        $this->assertSame('PRESENT', $board['attendance'][$this->kids['Ana']]);
        $this->assertSame(['RD-Q1W1-01', 'RD-Q1W1-02'], array_column($board['modules'][0]['lessons'][0]['activities'], 'activityName'));

        // Only present/late learners are pickable or may claim a device.
        $this->device()->getJson('/api/classroom-session/learners')->assertExactJson(['learners' => [['id' => $this->kids['Ana'], 'firstName' => 'Ana', 'photoUrl' => '/storage/learners/ana.webp']]]);
        $this->postJson('/api/classroom-session/claim', ['learnerId' => $this->kids['Ben']])->assertForbidden();
        $this->postJson('/api/classroom-session/claim', ['learnerId' => $this->kids['Cara']])->assertForbidden();
        $this->postJson('/api/classroom-session/join', ['learnerId' => $this->kids['Ben']])->assertForbidden();

        // An unidentified device can't read the answer key; Ana's can while the game is on screen.
        $this->getJson("/api/activities/{$game}/questions")->assertUnauthorized();
        $this->postJson('/api/classroom-session/claim', ['learnerId' => $this->kids['Ana']])->assertOk();
        $this->getJson('/api/classroom-session/me')->assertOk()->assertJson(['firstName' => 'Ana']);
        $this->getJson("/api/activities/{$game}/questions")->assertOk()->assertJsonCount(3)->assertJsonPath('0.questionKey', 'q1');
        $this->getJson("/api/activities/{$other}/questions")->assertUnauthorized();

        $this->postJson('/api/classroom-session/join', ['learnerId' => $this->kids['Ana']])->assertOk();
        $this->postJson('/api/classroom-session/join', ['learnerId' => $this->kids['Ana']])->assertOk();
        $this->assertSame(1, DB::table('learner_activity_attempts')->count());
        $this->getJson("/api/classroom-session?learnerId={$this->kids['Ana']}")->assertJson(['active' => true, 'activityId' => $game, 'activityFormat' => 'digital', 'activityType' => 'tap_choice', 'participantStatus' => 'Started the Activity']);
        $this->getJson('/api/classroom-session/learners')->assertExactJson(['learners' => []]);

        // Graded on the server: first answer per question, decoys and unknown ids ignored.
        $answers = [
            ['questionId' => 'q1', 'response' => 'B'], ['questionId' => 'q1', 'response' => 'x'],
            ['questionId' => 'q2', 'response' => 'd'], ['questionId' => 'q3', 'response' => 'd'], ['questionId' => 'zz', 'response' => 'zz'],
        ];
        $this->postJson('/api/classroom-session/finish', ['activityId' => $other, 'answers' => $answers])->assertForbidden();
        $this->postJson('/api/classroom-session/finish', ['activityId' => $game, 'answers' => $answers])->assertOk()->assertExactJson(['success' => true, 'score' => 1, 'total' => 2]);
        $this->postJson('/api/classroom-session/finish', ['activityId' => $game, 'answers' => $answers])->assertStatus(409);
        $this->postJson('/api/classroom-session/join', ['learnerId' => $this->kids['Ana']])->assertStatus(409);
        $this->getJson("/api/classroom-session/completion?learnerId={$this->kids['Ana']}")->assertExactJson(['completed' => true, 'score' => 1, 'total' => 2]);

        $ana = collect($this->actingAs($this->tina)->getJson("/api/sections/{$this->joy}/classroom")->json('learners'))->firstWhere('learnerId', $this->kids['Ana']);
        $this->assertSame('Finished', $ana['participantStatus']);
        $this->assertEquals(['moduleName' => 'Reading', 'activityName' => 'RD-Q1W1-01', 'week' => 1, 'score' => 1, 'activityScore' => 2], $ana['completedActivities'][0]);

        // The finish rings the teacher's bell once, despite the retried finish; only she sees it, and reading clears it.
        $bell = $this->getJson('/api/notifications')->assertOk()->assertJsonPath('unread', 1)->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.kind', 'finished')->assertJsonPath('items.0.activityName', 'RD-Q1W1-01')->assertJsonPath('items.0.sectionId', $this->joy);
        $this->assertStringStartsWith('Ana', $bell->json('items.0.learnerName'));
        $this->actingAs($omar)->getJson('/api/notifications')->assertExactJson(['unread' => 0, 'items' => []]);
        $this->actingAs($this->tina)->postJson('/api/notifications/read')->assertOk();
        $this->getJson('/api/notifications')->assertJsonPath('unread', 0)->assertJsonPath('items.0.read', true);

        // Class Scores: every activity of the module and quarter as columns, Ana's result in hers, Ben's row empty.
        $record = $this->actingAs($this->tina)->getJson("/api/sections/{$this->joy}/classroom-record?moduleId={$this->reading()}&quarter=1")->assertOk()->json();
        $this->assertSame(['RD-Q1W1-01', 'RD-Q1W1-02'], array_column($record['columns'], 'name'));
        $rows = collect($record['rows'])->keyBy('firstName');
        $this->assertEquals(['score' => 1, 'maxScore' => 2], $rows['Ana']['cells'][$game]);
        $this->assertEquals(['score' => 1, 'maxScore' => 2], $rows['Ana']['total']);
        $this->assertEquals([[], null], [$rows['Ben']['cells'], $rows['Ben']['total']]);
        $this->actingAs($this->tina)->getJson("/api/sections/{$this->joy}/classroom-record?quarter=5")->assertStatus(422)->assertJsonValidationErrors('quarter');

        // A class runs in one section at a time.
        $faith = $this->actingAs($this->tina)->postJson('/api/sections', ['gradeLevel' => 'Kinder 1', 'sectionName' => 'Faith'])->json('sectionId');
        $this->actingAs($this->tina)->postJson("/api/sections/{$faith}/classroom/start", ['moduleId' => $this->reading(), 'lessonId' => $this->lesson, 'activityId' => $other])
            ->assertStatus(422)->assertJsonValidationErrors(['activityId' => 'End the class in Joy first.']);

        // A new activity, then the first one again: an activity Ana already finished stays finished in a later run.
        $this->start($other)->assertOk();
        $this->device();
        $this->postJson('/api/classroom-session/claim', ['learnerId' => $this->kids['Ana']])->assertOk();
        $this->postJson('/api/classroom-session/join', ['learnerId' => $this->kids['Ana']])->assertOk();
        $this->start($game)->assertOk();
        $this->assertSame('abandoned', DB::table('learner_activity_attempts')->orderByDesc('id')->value('status'));
        $this->device();
        $this->postJson('/api/classroom-session/claim', ['learnerId' => $this->kids['Ana']])->assertOk();
        $this->postJson('/api/classroom-session/join', ['learnerId' => $this->kids['Ana']])->assertStatus(409);
        $this->getJson("/api/classroom-session/completion?learnerId={$this->kids['Ana']}")->assertExactJson(['completed' => true, 'score' => 1, 'total' => 2]);
        $this->getJson("/api/classroom-session?learnerId={$this->kids['Ana']}")->assertJson(['participantStatus' => 'Finished']);
        $ana = collect($this->actingAs($this->tina)->getJson("/api/sections/{$this->joy}/classroom")->json('learners'))->firstWhere('learnerId', $this->kids['Ana']);
        $this->assertSame('Finished', $ana['participantStatus']);
        // A leftover in-progress run in this class does not hide a learner who already finished the activity.
        DB::table('learner_activity_assignments')->insert([
            'enrollment_id' => DB::table('learner_school_years')->where('learner_id', $this->kids['Ana'])->value('id'),
            'activity_id' => $game, 'quarter_id' => DB::table('classroom_sessions')->value('quarter_id'), 'teacher_id' => $this->tina->id,
            'session_activity_id' => DB::table('classroom_session_activities')->where('status', 'active')->value('id'),
            'assigned_at' => now(), 'status' => 'in_progress',
        ]);
        $this->device()->getJson('/api/classroom-session/learners')->assertJsonPath('learners.0.firstName', 'Ana');
        $this->assertSame(1, DB::table('classroom_session_lessons')->count());
        $this->assertSame(['completed', 'completed', 'active'], DB::table('classroom_session_activities')->orderBy('sequence_order')->pluck('status')->all());

        // Ending the class closes everything still open.
        $this->actingAs($this->tina)->postJson("/api/sections/{$this->joy}/classroom/end")->assertOk();
        $this->actingAs($this->tina)->postJson("/api/sections/{$this->joy}/classroom/end")->assertStatus(422);
        $this->device()->getJson('/api/classroom-session')->assertExactJson(['active' => false]);
        $this->assertSame(0, DB::table('learner_activity_attempts')->where('status', 'in_progress')->count());
        $this->assertSame('completed', DB::table('classroom_session_learners')->where('participation_status', '<>', 'not_joined')->value('participation_status'));
        $this->assertSame(0, DB::table('classroom_session_activities')->where('status', 'active')->count());
        $this->actingAs($this->tina)->getJson("/api/sections/{$this->joy}/classroom")->assertJson(['activeSession' => null]);
    }

    public function test_a_finish_after_the_section_changes_hands_alerts_the_new_owner(): void
    {
        $this->quarter();
        $game = $this->activity('RD-Q1W1-01', 'digital');
        $this->actingAs($this->tina)->putJson("/api/sections/{$this->joy}/attendance", ['entries' => [
            ['learnerId' => $this->kids['Ana'], 'status' => 'PRESENT'],
            ['learnerId' => $this->kids['Ben'], 'status' => 'ABSENT'],
            ['learnerId' => $this->kids['Cara'], 'status' => 'ABSENT'],
        ]])->assertOk();
        $this->start($game)->assertOk();
        $this->device()->postJson('/api/classroom-session/join', ['learnerId' => $this->kids['Ana']])->assertOk();

        // Ownership moves while Ana's assignment, made under Tina, is still open.
        $bea = Teacher::create(['username' => 'bea', 'name' => 'Bea', 'password_hash' => 'x']);
        DB::table('sections')->where('id', $this->joy)->update(['teacher_id' => $bea->id]);
        $this->postJson('/api/classroom-session/finish', ['activityId' => $game, 'answers' => [['questionId' => 'q1', 'response' => 'B']]])->assertOk();

        $this->assertSame($bea->id, DB::table('notifications')->value('teacher_id'));
        $this->assertSame($this->tina->id, DB::table('learner_activity_assignments')->value('teacher_id'));
    }

    public function test_practical_activity_is_watched_then_rated_by_the_teacher(): void
    {
        $this->quarter();
        $hop = $this->activity('MP-Q1W1-01', 'practical');
        $this->actingAs($this->tina)->putJson("/api/sections/{$this->joy}/attendance", ['entries' => [
            ['learnerId' => $this->kids['Ana'], 'status' => 'LATE'],
            ['learnerId' => $this->kids['Ben'], 'status' => 'ABSENT'],
            ['learnerId' => $this->kids['Cara'], 'status' => 'ABSENT'],
        ]])->assertOk();
        $this->start($hop)->assertOk();

        $this->device()->postJson('/api/classroom-session/join', ['learnerId' => $this->kids['Ana']])->assertOk();
        $this->getJson("/api/classroom-session?learnerId={$this->kids['Ana']}")
            ->assertJson(['activityFormat' => 'practical', 'activityType' => 'demonstration', 'activityVideoUrl' => '/demonstrations/hop.mp4']);
        $this->postJson('/api/classroom-session/finish', ['activityId' => $hop, 'answers' => [['questionId' => 'a', 'response' => 'b']]])->assertStatus(400);

        $rate = fn (string $result) => $this->actingAs($this->tina)->putJson("/api/sections/{$this->joy}/classroom/practical-rating", ['learnerId' => $this->kids['Ana'], 'activityId' => $hop, 'result' => $result, 'remarks' => ' Hopped well ']);
        $rate('good')->assertStatus(422)->assertJsonValidationErrors('result');

        $this->device()->postJson('/api/classroom-session/claim', ['learnerId' => $this->kids['Ana']])->assertOk();
        $this->postJson('/api/classroom-session/demonstration', ['activityId' => $hop])->assertOk();
        $this->postJson('/api/classroom-session/demonstration', ['activityId' => $hop])->assertStatus(409);
        $this->getJson("/api/classroom-session/practical-result?activityId={$hop}")->assertExactJson(['rated' => false]);

        $card = fn () => collect($this->actingAs($this->tina)->getJson("/api/sections/{$this->joy}/classroom")->json('learners'))->firstWhere('learnerId', $this->kids['Ana']);
        $this->assertSame(['Finished', 'NOT_RATED'], [$card()['participantStatus'], $card()['activePracticalActivity']['ratingStatus']]);
        $this->getJson('/api/notifications')->assertJsonPath('unread', 1)->assertJsonPath('items.0.kind', 'needs_rating');

        $rate('bad')->assertStatus(422)->assertJsonValidationErrors('result');
        $rate('good')->assertOk();
        $this->assertSame(['good', 'Hopped well'], [$card()['activePracticalActivity']['ratingStatus'], $card()['activePracticalActivity']['remarks']]);
        $this->assertSame('good', $card()['completedActivities'][0]['practicalResult']);
        $record = $this->actingAs($this->tina)->getJson("/api/sections/{$this->joy}/classroom-record?moduleId={$this->reading()}&quarter=1")->json('rows');
        $this->assertSame(['rating' => 'good'], collect($record)->firstWhere('firstName', 'Ana')['cells'][$hop]);
        // The rated practical joins the Total on a 3-point scale: Good is 2 of 3.
        $this->assertEquals(['score' => 2, 'maxScore' => 3], collect($record)->firstWhere('firstName', 'Ana')['total']);

        $this->device()->postJson('/api/classroom-session/claim', ['learnerId' => $this->kids['Ana']])->assertOk();
        $this->getJson("/api/classroom-session/practical-result?activityId={$hop}")->assertExactJson(['rated' => true, 'result' => 'good']);
    }
}

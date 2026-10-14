<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Checks the database-level rules from the Kindergarten structure spec.
class SchemaRulesTest extends TestCase
{
    use RefreshDatabase;

    private function assertRejected(callable $write): void
    {
        DB::statement('SAVEPOINT rule_check');
        try {
            $write();
        } catch (QueryException) {
            DB::statement('ROLLBACK TO SAVEPOINT rule_check');
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail('Expected the database to reject this write.');
    }

    private function year(string $name, string $status = 'upcoming'): int
    {
        return DB::table('school_years')->insertGetId(['name' => $name, 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => $status]);
    }

    private function teacher(): int
    {
        return DB::table('teachers')->insertGetId(['name' => 'T', 'username' => 't', 'password_hash' => 'x']);
    }

    private function enroll(int $year, int $teacher): int
    {
        $section = DB::table('sections')->insertGetId(['school_year_id' => $year, 'teacher_id' => $teacher, 'name' => 'Joy', 'grade_level' => 'Kindergarten']);
        $learner = DB::table('learners')->insertGetId(['first_name' => 'A', 'last_name' => 'B', 'birth_date' => '2021-01-01']);

        return DB::table('learner_school_years')->insertGetId(['learner_id' => $learner, 'school_year_id' => $year, 'section_id' => $section, 'enrolled_on' => '2026-06-01', 'status' => 'active']);
    }

    public function test_school_year_and_quarter_rules(): void
    {
        $active = $this->year('2026-2027', 'active');

        $this->assertRejected(fn () => $this->year('2027-2028', 'active'));
        $this->assertRejected(fn () => DB::table('school_years')->insert(['name' => 'bad', 'start_date' => '2027-01-01', 'end_date' => '2026-01-01']));
        $this->assertRejected(fn () => $this->year('x', 'archived'));
        $this->assertRejected(fn () => DB::table('quarters')->insert(['school_year_id' => $active, 'quarter_number' => 5, 'name' => 'Q5', 'start_date' => '2026-06-01', 'end_date' => '2026-08-01', 'status' => 'upcoming']));

        // One active quarter per school year; another year may have its own.
        $quarter = fn (int $year, int $number) => DB::table('quarters')->insert(['school_year_id' => $year, 'quarter_number' => $number, 'name' => "Q$number", 'start_date' => '2026-06-01', 'end_date' => '2026-08-01', 'status' => 'active']);
        $quarter($active, 1);
        $this->assertRejected(fn () => $quarter($active, 2));
        $quarter($this->year('2027-2028'), 1);
    }

    public function test_enrollment_section_must_belong_to_the_same_school_year(): void
    {
        $y1 = $this->year('2026-2027');
        $y2 = $this->year('2027-2028');
        $teacher = $this->teacher();
        $enrollment = $this->enroll($y1, $teacher);
        $row = (array) DB::table('learner_school_years')->find($enrollment);

        $this->assertRejected(fn () => DB::table('learner_school_years')->where('id', $enrollment)->update(['school_year_id' => $y2]));
        $this->assertRejected(fn () => DB::table('teachers')->where('id', $teacher)->delete());
        $this->assertRejected(fn () => DB::table('teachers')->insert(['name' => 'U', 'username' => 'Upper', 'password_hash' => 'x']));
        $this->assertSame($y1, $row['school_year_id']);
    }

    public function test_one_active_schedule_per_section_module(): void
    {
        $year = $this->year('2026-2027', 'active');
        $teacher = $this->teacher();
        $section = DB::table('sections')->insertGetId(['school_year_id' => $year, 'teacher_id' => $teacher, 'name' => 'Joy', 'grade_level' => 'Kindergarten']);
        $reading = DB::table('modules')->insertGetId(['code' => 'reading', 'name' => 'Reading', 'grade_level' => 'Kindergarten']);
        $math = DB::table('modules')->insertGetId(['code' => 'math', 'name' => 'Math', 'grade_level' => 'Kindergarten']);
        $joyReading = DB::table('section_modules')->insertGetId(['section_id' => $section, 'module_id' => $reading, 'teacher_id' => $teacher]);
        $joyMath = DB::table('section_modules')->insertGetId(['section_id' => $section, 'module_id' => $math, 'teacher_id' => $teacher]);
        $schedule = fn (int $sectionModule, string $start, string $end, bool $active = true) => DB::table('class_schedules')->insertGetId([
            'section_module_id' => $sectionModule, 'start_time' => $start, 'end_time' => $end,
            'effective_from' => '2026-06-01', 'effective_until' => '2027-03-31', 'is_active' => $active,
        ]);

        // First active schedule for Joy Reading is fine.
        $schedule($joyReading, '08:00', '09:00');
        // A second active one for the same section + module is rejected, even at a different time.
        $this->assertRejected(fn () => $schedule($joyReading, '13:00', '14:00'));
        // An inactive (historical) row for the same section + module is allowed.
        $schedule($joyReading, '07:00', '08:00', false);
        // A different section_module keeps its own active schedule.
        $schedule($joyMath, '09:45', '11:15');

        $this->assertSame(1, DB::table('class_schedules')->where(['section_module_id' => $joyReading, 'is_active' => true])->count());
        $this->assertSame(2, DB::table('class_schedules')->where('section_module_id', $joyReading)->count());
        $this->assertSame(1, DB::table('class_schedules')->where(['section_module_id' => $joyMath, 'is_active' => true])->count());
    }

    public function test_curriculum_and_result_rules(): void
    {
        $module = DB::table('modules')->insertGetId(['code' => 'math', 'name' => 'Math', 'grade_level' => 'Kindergarten']);
        // Any new module code is allowed, but not twice in the same grade level.
        DB::table('modules')->insert(['code' => 'science', 'name' => 'Science', 'grade_level' => 'Kindergarten']);
        $this->assertRejected(fn () => DB::table('modules')->insert(['code' => 'math', 'name' => 'Math 2', 'grade_level' => 'Kindergarten']));
        $domain = DB::table('developmental_domains')->insertGetId(['code' => 'math_readiness', 'name' => 'Math Readiness', 'display_order' => 1]);
        $mapping = DB::table('module_domains')->insertGetId(['module_id' => $module, 'domain_id' => $domain]);

        $this->assertRejected(fn () => DB::table('lessons')->insert(['module_domain_id' => $mapping, 'title' => 'L', 'week_number' => 2, 'sequence_order' => 1]));
        $lesson = DB::table('lessons')->insertGetId(['module_domain_id' => $mapping, 'title' => 'L', 'quarter_number' => 1, 'week_number' => 2, 'sequence_order' => 1]);
        $this->assertRejected(fn () => DB::table('activities')->insert(['lesson_id' => $lesson, 'activity_code' => 'a', 'name' => 'A', 'instructions' => 'i', 'activity_format' => 'video', 'sequence_order' => 1]));
        $activity = DB::table('activities')->insertGetId(['lesson_id' => $lesson, 'activity_code' => 'a', 'name' => 'A', 'instructions' => 'i', 'activity_format' => 'digital', 'sequence_order' => 1]);
        $this->assertRejected(fn () => DB::table('digital_activity_details')->insert(['activity_id' => $activity, 'game_type' => 'tap_choice', 'max_score' => 0]));

        // Questions: unique key and order per activity, positive order, object data, and a string answer.
        $question = fn (array $row) => DB::table('activity_questions')->insert([...['activity_id' => $activity, 'question_key' => 'q1', 'question_order' => 1, 'question_data' => '{"letter": "B"}', 'correct_answer' => '{"answer": "B"}'], ...$row]);
        $question([]);
        $question(['question_key' => 'q2', 'question_order' => 2, 'question_data' => '{"letter": "d", "gradable": false}', 'correct_answer' => '{"answer": "d"}']);
        $this->assertRejected(fn () => $question(['question_order' => 3]));
        $this->assertRejected(fn () => $question(['question_key' => 'q3']));
        $this->assertRejected(fn () => $question(['question_key' => 'q3', 'question_order' => 0]));
        $this->assertRejected(fn () => $question(['question_key' => ' ', 'question_order' => 3]));
        $this->assertRejected(fn () => $question(['question_key' => 'q3', 'question_order' => 3, 'question_data' => '[1, 2]']));
        $this->assertRejected(fn () => $question(['question_key' => 'q3', 'question_order' => 3, 'correct_answer' => '{"answer": 1}']));
        $this->assertRejected(fn () => $question(['question_key' => 'q3', 'question_order' => 3, 'correct_answer' => '"B"']));
        $other = DB::table('activities')->insertGetId(['lesson_id' => $lesson, 'activity_code' => 'b', 'name' => 'B', 'instructions' => 'i', 'activity_format' => 'digital', 'sequence_order' => 2]);
        $question(['activity_id' => $other]);
        $this->assertSame(3, DB::table('activity_questions')->count());

        $year = $this->year('2026-2027', 'active');
        $quarter = DB::table('quarters')->insertGetId(['school_year_id' => $year, 'quarter_number' => 1, 'name' => '1st Quarter', 'start_date' => '2026-06-01', 'end_date' => '2026-08-31', 'status' => 'active']);
        $teacher = $this->teacher();
        $enrollment = $this->enroll($year, $teacher);
        $assignment = DB::table('learner_activity_assignments')->insertGetId(['enrollment_id' => $enrollment, 'activity_id' => $activity, 'quarter_id' => $quarter, 'teacher_id' => $teacher, 'assigned_at' => now(), 'status' => 'assigned']);

        $attempt = fn (array $row) => DB::table('learner_activity_attempts')->insert([...['assignment_id' => $assignment, 'attempt_number' => 1, 'started_at' => now()], ...$row]);
        $this->assertRejected(fn () => $attempt(['status' => 'evaluated', 'score' => 11, 'max_score' => 10]));
        $this->assertRejected(fn () => $attempt(['status' => 'evaluated', 'score' => 5, 'max_score' => 10, 'practical_rating' => 'good']));
        $this->assertRejected(fn () => $attempt(['status' => 'evaluated', 'practical_rating' => 'good']));
        $attempt(['status' => 'evaluated', 'score' => 8, 'max_score' => 10]);

        $this->assertRejected(fn () => DB::table('learner_activity_assignments')->where('id', $assignment)->update(['status' => 'completed']));
        $this->assertRejected(fn () => DB::table('quarterly_progress_reports')->insert(['enrollment_id' => $enrollment, 'quarter_id' => $quarter, 'teacher_id' => $teacher, 'status' => 'finalized', 'generated_at' => now()]));

        // One open class per teacher; ended classes don't count.
        $sectionModule = DB::table('section_modules')->insertGetId(['section_id' => DB::table('sections')->value('id'), 'module_id' => $module, 'teacher_id' => $teacher]);
        $session = fn (string $status) => DB::table('classroom_sessions')->insertGetId(['section_module_id' => $sectionModule, 'quarter_id' => $quarter, 'teacher_id' => $teacher, 'session_date' => '2026-07-01', 'planned_start_time' => '08:00', 'planned_end_time' => '09:00', 'status' => $status]);
        $open = $session('ongoing');
        $this->assertRejected(fn () => $session('ongoing'));
        $this->assertRejected(fn () => $session('scheduled'));
        $session('completed');

        // One activity on screen per class lesson.
        $sessionLesson = DB::table('classroom_session_lessons')->insertGetId(['classroom_session_id' => $open, 'lesson_id' => $lesson, 'sequence_order' => 1, 'status' => 'active']);
        $shown = fn (int $order, string $status) => DB::table('classroom_session_activities')->insert(['session_lesson_id' => $sessionLesson, 'activity_id' => $activity, 'sequence_order' => $order, 'assigned_by' => $teacher, 'assigned_at' => now(), 'status' => $status]);
        $shown(1, 'active');
        $this->assertRejected(fn () => $shown(2, 'active'));
        $shown(2, 'completed');
    }
}

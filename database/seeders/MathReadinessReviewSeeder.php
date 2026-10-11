<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Math Readiness Review" for Math, Q1 Week 7 Tuesday; run with: php artisan db:seed --class=MathReadinessReviewSeeder
class MathReadinessReviewSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'MR-Q1W7-01';

    private const CHECKLIST_ITEM = 'Reviews number recognition, counting up to 10, number-quantity matching, and comparing more and less';

    // Ten picture tasks mixing recognize (numerals 1 to 10), count (rows or scattered), match and compare; each is [question_key, question_data] and its answer is worked out in answerOf().
    private const TASKS = [
        ['recognize-7', ['kind' => 'recognize', 'number' => 7, 'choices' => [4, 7, 1, 9]]],
        ['count-apple', ['kind' => 'count', 'object' => 'apple', 'count' => 5, 'layout' => 'rows', 'choices' => [5, 6, 4]]],
        ['compare-duck', ['kind' => 'compare', 'object' => 'duck', 'left' => 6, 'right' => 3, 'ask' => 'more']],
        ['match-star', ['kind' => 'match', 'object' => 'star', 'number' => 8, 'groups' => [6, 8, 9]]],
        ['count-ball', ['kind' => 'count', 'object' => 'ball', 'count' => 8, 'layout' => 'scattered', 'choices' => [7, 8, 10]]],
        ['recognize-10', ['kind' => 'recognize', 'number' => 10, 'choices' => [10, 6, 8, 1]]],
        ['match-fish', ['kind' => 'match', 'object' => 'fish', 'number' => 3, 'groups' => [3, 5, 2]]],
        ['compare-flower', ['kind' => 'compare', 'object' => 'flower', 'left' => 7, 'right' => 2, 'ask' => 'fewer']],
        ['count-car', ['kind' => 'count', 'object' => 'car', 'count' => 10, 'layout' => 'rows', 'choices' => [9, 10, 8]]],
        ['recognize-4', ['kind' => 'recognize', 'number' => 4, 'choices' => [2, 4, 5, 3]]],
    ];

    // The number tapped, or "left"/"right" for the group with more or fewer.
    private static function answerOf(array $task): string
    {
        return match ($task['kind']) {
            'recognize', 'match' => (string) $task['number'],
            'count' => (string) $task['count'],
            'compare' => ($task['left'] > $task['right']) === ($task['ask'] === 'more') ? 'left' : 'right',
        };
    }

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Math Q1 Week 7 Tuesday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 1, 'lessons.week_number' => 7, 'lessons.day_of_week' => 2, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 1, Week 7, Tuesday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Math Readiness Review',
                'description' => 'Complete mixed picture-based tasks using the number and quantity skills already practiced.',
                'instructions' => 'Listen to each task, then tap the right number or group to grow the number flower.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(self::TASKS),
            ]);

            // Cleared and re-added, since kept rows would clash with new question_order values.
            DB::table('activity_questions')->where('activity_id', $activityId)->delete();
            foreach (self::TASKS as $i => [$key, $task]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => $key,
                    'question_order' => $i + 1,
                    'question_data' => json_encode($task),
                    'correct_answer' => json_encode(['answer' => self::answerOf($task)]),
                ]);
            }

            // The skill this game practises, on the Developmental Checklist under Math Readiness for Quarter 1.
            $domainId = DB::table('developmental_domains')->where('code', 'math_readiness')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kinder 1', 'quarter_number' => 1, 'item' => self::CHECKLIST_ITEM];
            // Matched by activity first, so renaming the item updates its row instead of adding a second one.
            $existing = DB::table('developmental_checklist_items')->where('activity_id', $activityId)->value('id')
                ?? DB::table('developmental_checklist_items')->where($item)->value('id');
            if ($existing) {
                DB::table('developmental_checklist_items')->where('id', $existing)->update(['activity_id' => $activityId, 'item' => self::CHECKLIST_ITEM, 'updated_at' => now()]);
            } else {
                DB::table('developmental_checklist_items')->insert($item + [
                    'activity_id' => $activityId,
                    'display_order' => (DB::table('developmental_checklist_items')->where(['domain_id' => $domainId, 'quarter_number' => 1])->max('display_order') ?? 0) + 1,
                ]);
            }
        });
    }
}

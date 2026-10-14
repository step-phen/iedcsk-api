<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Math Readiness Final Assessment" for Math, Q1 Week 7 Thursday; run with: php artisan db:seed --class=MathReadinessFinalAssessmentSeeder
class MathReadinessFinalAssessmentSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'MR-Q1W7-02';

    private const CHECKLIST_ITEM = 'Demonstrates number recognition, counting, quantity matching, and comparison skills taught during the quarter';

    // Fifteen picture tasks mixing recognize (numerals 1 to 10), count (rows or scattered), match and compare; each is [question_key, question_data] and its answer is worked out in answerOf().
    private const TASKS = [
        ['recognize-3', ['kind' => 'recognize', 'number' => 3, 'choices' => [3, 8, 5, 1]]],
        ['count-banana', ['kind' => 'count', 'object' => 'banana', 'count' => 7, 'layout' => 'scattered', 'choices' => [6, 7, 8]]],
        ['match-apple', ['kind' => 'match', 'object' => 'apple', 'number' => 4, 'groups' => [4, 6, 3]]],
        ['compare-car', ['kind' => 'compare', 'object' => 'car', 'left' => 3, 'right' => 8, 'ask' => 'more']],
        ['recognize-9', ['kind' => 'recognize', 'number' => 9, 'choices' => [6, 9, 2, 7]]],
        ['count-duck', ['kind' => 'count', 'object' => 'duck', 'count' => 6, 'layout' => 'rows', 'choices' => [6, 5, 9]]],
        ['match-ball', ['kind' => 'match', 'object' => 'ball', 'number' => 10, 'groups' => [8, 10, 7]]],
        ['compare-fish', ['kind' => 'compare', 'object' => 'fish', 'left' => 9, 'right' => 4, 'ask' => 'fewer']],
        ['recognize-6', ['kind' => 'recognize', 'number' => 6, 'choices' => [6, 9, 3, 10]]],
        ['count-star', ['kind' => 'count', 'object' => 'star', 'count' => 9, 'layout' => 'scattered', 'choices' => [8, 9, 10]]],
        ['match-flower', ['kind' => 'match', 'object' => 'flower', 'number' => 5, 'groups' => [7, 4, 5]]],
        ['compare-banana', ['kind' => 'compare', 'object' => 'banana', 'left' => 1, 'right' => 5, 'ask' => 'fewer']],
        ['recognize-2', ['kind' => 'recognize', 'number' => 2, 'choices' => [5, 2, 7, 4]]],
        ['count-fish', ['kind' => 'count', 'object' => 'fish', 'count' => 3, 'layout' => 'scattered', 'choices' => [3, 4, 2]]],
        ['match-duck', ['kind' => 'match', 'object' => 'duck', 'number' => 7, 'groups' => [7, 9, 6]]],
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
        // Lessons are teacher-made, so this attaches to the existing Math Q1 Week 7 Thursday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 1, 'lessons.week_number' => 7, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 1, Week 7, Thursday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Math Readiness Final Assessment',
                'description' => 'Answer picture-based tasks on taught numerals, quantities up to 10, and more or less, with spoken instructions.',
                'instructions' => 'Listen to each task, then tap the right number or group to build the number tower.',
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
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kindergarten', 'quarter_number' => 1, 'item' => self::CHECKLIST_ITEM];
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

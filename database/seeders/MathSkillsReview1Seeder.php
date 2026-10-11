<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Math Skills Review 1" review game for Math, Q1 Week 4 Thursday; run with: php artisan db:seed --class=MathSkillsReview1Seeder
class MathSkillsReview1Seeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'MR-Q1W4-02';

    private const CHECKLIST_ITEM = 'Recognizes numerals 1 to 5, counts and matches quantities up to 10, and compares more and less';

    // Ten picture tasks mixing the four skills; each is [question_key, question_data] and its answer is worked out in answerOf().
    private const TASKS = [
        ['recognize-3', ['kind' => 'recognize', 'number' => 3]],
        ['count-apple', ['kind' => 'count', 'object' => 'apple', 'count' => 4, 'choices' => [3, 4, 5]]],
        ['match-duck', ['kind' => 'match', 'object' => 'duck', 'number' => 2, 'groups' => [4, 2, 1]]],
        ['compare-star', ['kind' => 'compare', 'object' => 'star', 'left' => 5, 'right' => 2, 'ask' => 'more']],
        ['recognize-5', ['kind' => 'recognize', 'number' => 5]],
        ['count-fish', ['kind' => 'count', 'object' => 'fish', 'count' => 7, 'choices' => [6, 9, 7]]],
        ['match-car', ['kind' => 'match', 'object' => 'car', 'number' => 6, 'groups' => [8, 6, 5]]],
        ['compare-ball', ['kind' => 'compare', 'object' => 'ball', 'left' => 1, 'right' => 4, 'ask' => 'fewer']],
        ['recognize-2', ['kind' => 'recognize', 'number' => 2]],
        ['count-flower', ['kind' => 'count', 'object' => 'flower', 'count' => 9, 'choices' => [10, 9, 8]]],
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
        // Lessons are teacher-made, so this attaches to the existing Math Q1 Week 4 Thursday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 1, 'lessons.week_number' => 4, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 1, Week 4, Thursday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Math Skills Review 1',
                'description' => 'Complete picture-based tasks on number recognition, counting, matching quantities, and more or less.',
                'instructions' => 'Listen to each task, then tap the right number or group to fill the number train.',
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

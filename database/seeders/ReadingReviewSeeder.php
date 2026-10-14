<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Reading Review" game for Reading, Q1 Week 7 Wednesday; run with: php artisan db:seed --class=ReadingReviewSeeder
class ReadingReviewSeeder extends Seeder
{
    private const CODE = 'RD-Q1W7-02';

    private const CHECKLIST_ITEM = 'Reviews comparisons, shapes, letters, vowel pairs, and beginning vowel sounds';

    // key => [task type, target (picture, shape, letter or vowel the task is about, or null), choices, answer]; one stone on the trail each, in play order.
    private const TASKS = [
        'same-apple' => ['same', 'apple', ['ant', 'apple', 'egg'], 'apple'],
        'different-umbrella' => ['different', null, ['octopus', 'umbrella', 'octopus'], 'umbrella'],
        'long-snake' => ['long', null, ['pencil', 'snake', 'spoon'], 'snake'],
        'big-elephant' => ['big', null, ['mouse', 'ant', 'elephant'], 'elephant'],
        'shape-circle' => ['shape', 'circle', ['square', 'circle', 'triangle'], 'circle'],
        'shape-triangle' => ['shape', 'triangle', ['triangle', 'rectangle', 'star'], 'triangle'],
        'letter-m' => ['letter', 'M', ['N', 'M', 'W'], 'M'],
        'partner-b' => ['partner', 'B', ['d', 'b', 'p'], 'b'],
        'vowel-e' => ['vowel', 'e', ['egg', 'ox', 'ink'], 'egg'],
        'vowel-u' => ['vowel', 'u', ['apple', 'insect', 'umbrella'], 'umbrella'],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Reading Q1 Week 7 Wednesday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'reading', 'lessons.quarter_number' => 1, 'lessons.week_number' => 7, 'lessons.day_of_week' => 3, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Reading lesson for Quarter 1, Week 7, Wednesday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Reading Review',
                'description' => 'Complete picture-based tasks on comparisons, shapes, familiar alphabet letters, vowel pairs, and beginning vowel sounds.',
                'instructions' => 'Listen to each question, then tap the right answer to hop along the trail.',
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
            $order = 0;
            foreach (self::TASKS as $key => [$type, $target, $choices, $answer]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => $key,
                    'question_order' => ++$order,
                    'question_data' => json_encode(['type' => $type, 'target' => $target, 'choices' => $choices]),
                    'correct_answer' => json_encode(['answer' => $answer]),
                ]);
            }

            // The skill this game practises, on the Developmental Checklist under Reading and Language for Quarter 1.
            $domainId = DB::table('developmental_domains')->where('code', 'reading_language')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kindergarten', 'quarter_number' => 1, 'item' => self::CHECKLIST_ITEM];
            // Matched by activity first, so renaming the item updates its row instead of adding a second one.
            $existing = DB::table('developmental_checklist_items')->where('activity_id', $activityId)->value('id')
                ?? DB::table('developmental_checklist_items')->where($item)->value('id');
            if ($existing) {
                DB::table('developmental_checklist_items')->where('id', $existing)->update(['activity_id' => $activityId, 'item' => self::CHECKLIST_ITEM, 'updated_at' => now()]);
            } else {
                DB::table('developmental_checklist_items')->insert($item + [
                    'activity_id' => $activityId,
                    'display_order' => DB::table('developmental_checklist_items')->where(['domain_id' => $domainId, 'quarter_number' => 1])->max('display_order') + 1,
                ]);
            }
        });
    }
}

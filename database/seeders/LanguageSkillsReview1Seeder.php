<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Language Skills Review 1" game for Language, Q1 Week 4 Friday; run with: php artisan db:seed --class=LanguageSkillsReview1Seeder
class LanguageSkillsReview1Seeder extends Seeder
{
    private const CODE = 'LG-Q1W4-03';

    private const CHECKLIST_ITEM = 'Reviews similarities, differences, related objects, missing parts, opposite directions, and vowels Aa, Ee, Ii';

    // key => [task type, target (picture, object, direction or letter the task is about, or null), choices, answer], in play order.
    // Missing-part choices are part names and opposite choices are directions; the game draws both with the Week 2 and Week 3 art.
    private const TASKS = [
        'same-cat' => ['same', 'cat', ['dog', 'cat', 'fish'], 'cat'],
        'different-ball' => ['different', null, ['ball-red', 'ball-blue', 'ball-red'], 'ball-blue'],
        'together-sock' => ['together', 'sock', ['shoe', 'spoon', 'key'], 'shoe'],
        'missing-car' => ['missing', 'car', ['leaf', 'wheel', 'star'], 'wheel'],
        'opposite-up' => ['opposite', 'up', ['left', 'right', 'down'], 'down'],
        'letter-e' => ['letter', 'E', ['A', 'E', 'I'], 'E'],
        'same-flower' => ['same', 'flower', ['flower', 'star', 'balloon'], 'flower'],
        'together-key' => ['together', 'key', ['book', 'lock', 'bowl'], 'lock'],
        'missing-cup' => ['missing', 'cup', ['cup-handle', 'sock', 'cloud'], 'cup-handle'],
        'letter-i' => ['letter', 'i', ['a', 'e', 'i'], 'i'],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Language Q1 Week 4 Friday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'language', 'lessons.quarter_number' => 1, 'lessons.week_number' => 4, 'lessons.day_of_week' => 5, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Language lesson for Quarter 1, Week 4, Friday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Language Skills Review 1',
                'description' => 'Identify and match pictures, follow simple directions, and name Aa, Ee, and Ii.',
                'instructions' => 'Listen to each question, then pop the bubble with the right answer.',
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
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kinder 1', 'quarter_number' => 1, 'item' => self::CHECKLIST_ITEM];
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

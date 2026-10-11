<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Reading Final Exam" game for Reading, Q1 Week 7 Friday; run with: php artisan db:seed --class=ReadingFinalExamSeeder
class ReadingFinalExamSeeder extends Seeder
{
    private const CODE = 'RD-Q1W7-03';

    private const CHECKLIST_ITEM = 'Shows first-quarter skills in comparisons, shapes, letters, vowel pairs, and beginning vowel sounds';

    // key => [task type, target (picture, shape, letter or vowel the task is about, or null), choices, answer]; one star in the sky each, in play order.
    private const TASKS = [
        'same-egg' => ['same', 'egg', ['egg', 'ox', 'ant'], 'egg'],
        'different-apple' => ['different', null, ['ink', 'ink', 'apple'], 'apple'],
        'long-snake' => ['long', null, ['spoon', 'pencil', 'snake'], 'snake'],
        'small-mouse' => ['small', null, ['elephant', 'mouse', 'ox'], 'mouse'],
        'shape-square' => ['shape', 'square', ['circle', 'square', 'star'], 'square'],
        'shape-rectangle' => ['shape', 'rectangle', ['triangle', 'circle', 'rectangle'], 'rectangle'],
        'letter-d' => ['letter', 'D', ['B', 'D', 'P'], 'D'],
        'letter-a' => ['letter', 'A', ['A', 'E', 'O'], 'A'],
        'letter-o' => ['letter', 'O', ['U', 'C', 'O'], 'O'],
        'partner-e' => ['partner', 'E', ['e', 'a', 'c'], 'e'],
        'partner-m' => ['partner', 'M', ['n', 'm', 'w'], 'm'],
        'partner-u' => ['partner', 'U', ['n', 'o', 'u'], 'u'],
        'vowel-a' => ['vowel', 'a', ['ant', 'octopus', 'egg'], 'ant'],
        'vowel-i' => ['vowel', 'i', ['umbrella', 'insect', 'apple'], 'insect'],
        'vowel-o' => ['vowel', 'o', ['egg', 'umbrella', 'ox'], 'ox'],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Reading Q1 Week 7 Friday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'reading', 'lessons.quarter_number' => 1, 'lessons.week_number' => 7, 'lessons.day_of_week' => 5, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Reading lesson for Quarter 1, Week 7, Friday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Reading Final Exam',
                'description' => 'Complete picture-based tasks on comparisons, shapes, taught alphabet letters, vowel pairs, and beginning vowel sounds, with spoken instructions.',
                'instructions' => 'Listen to each question, then tap the right answer to light up a star.',
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

            // The skill this game assesses, on the Developmental Checklist under Reading and Language for Quarter 1.
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

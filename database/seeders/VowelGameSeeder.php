<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;
use RuntimeException;

// Shared by the "Vowel Xx" picture games; each subclass names its letter, lesson day and rounds.
abstract class VowelGameSeeder extends Seeder
{
    // Two-letter form shown in names, e.g. "Aa".
    protected const LETTER = '';

    // Module, quarter, week, then the activity's number in that week.
    protected const CODE = '';

    protected const WEEK = 0;

    // 1 = Monday ... 5 = Friday.
    protected const DAY = 0;

    // [the vowel word, the four pictures in the order shown]; the game draws each word from its own word list.
    protected const ROUNDS = [];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Reading Q1 lesson for that week and day.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'reading', 'lessons.quarter_number' => 1, 'lessons.week_number' => static::WEEK, 'lessons.day_of_week' => static::DAY, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Reading lesson for Quarter 1, Week '.static::WEEK.', day '.static::DAY.' first.');
        }

        $letter = static::LETTER;
        $checklistItem = "Identifies pictures that begin with {$letter}";

        DB::transaction(function () use ($lessonId, $letter, $checklistItem) {
            DB::table('activities')->updateOrInsert(['activity_code' => static::CODE], [
                'lesson_id' => $lessonId,
                'name' => "Vowel {$letter}",
                'description' => 'Tap the picture that starts with the letter '.$letter[0].'.',
                'instructions' => "Listen to the question, then tap the picture whose name starts with {$letter}.",
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', static::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(static::ROUNDS),
            ]);

            foreach (static::ROUNDS as $i => [$target, $choices]) {
                DB::table('activity_questions')->updateOrInsert(['activity_id' => $activityId, 'question_key' => $target], [
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['choices' => $choices]),
                    'correct_answer' => json_encode(['answer' => $target]),
                ]);
            }

            // The skill this game practises, on the Developmental Checklist under Reading and Language for Quarter 1.
            $domainId = DB::table('developmental_domains')->where('code', 'reading_language')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kindergarten', 'quarter_number' => 1, 'item' => $checklistItem];
            // Matched by activity first, so renaming the item updates its row instead of adding a second one.
            $existing = DB::table('developmental_checklist_items')->where('activity_id', $activityId)->value('id')
                ?? DB::table('developmental_checklist_items')->where($item)->value('id');
            if ($existing) {
                DB::table('developmental_checklist_items')->where('id', $existing)->update(['activity_id' => $activityId, 'item' => $checklistItem, 'updated_at' => now()]);
            } else {
                DB::table('developmental_checklist_items')->insert($item + [
                    'activity_id' => $activityId,
                    'display_order' => DB::table('developmental_checklist_items')->where(['domain_id' => $domainId, 'quarter_number' => 1])->max('display_order') + 1,
                ]);
            }
        });
    }
}

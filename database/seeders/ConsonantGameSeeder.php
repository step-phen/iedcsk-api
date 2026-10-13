<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Shared by the Reading Q2 and Q3 "Consonant Xx" picture games; each subclass names its letter, lesson day and rounds.
abstract class ConsonantGameSeeder extends Seeder
{
    // Two-letter form shown in names, e.g. "Bb".
    protected const LETTER = '';

    // The sound as written in the checklist, e.g. "/b/".
    protected const SOUND = '';

    protected const QUARTER = 2;

    protected const CODE = '';

    protected const WEEK = 0;

    // 1 = Monday ... 5 = Friday.
    protected const DAY = 0;

    // [the word that begins with the letter, the three pictures in the order shown].
    protected const ROUNDS = [];

    public function run(): void
    {
        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday'];
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'reading', 'lessons.quarter_number' => static::QUARTER, 'lessons.week_number' => static::WEEK, 'lessons.day_of_week' => static::DAY, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Reading lesson for Quarter '.static::QUARTER.', Week '.static::WEEK.', '.$days[static::DAY].' first.');
        }

        $letter = static::LETTER;
        $checklistItem = "Recognizes {$letter} and identifies pictures that begin with the ".static::SOUND.' sound';

        DB::transaction(function () use ($lessonId, $letter, $checklistItem) {
            DB::table('activities')->updateOrInsert(['activity_code' => static::CODE], [
                'lesson_id' => $lessonId,
                'name' => "Consonant {$letter}",
                'description' => 'Tap the picture that begins with the '.$letter[0].' sound.',
                'instructions' => "Listen to the picture names, then tap the picture beginning with the {$letter} sound.",
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', static::CODE)->value('id');

            DB::table('practical_activity_details')->where('activity_id', $activityId)->delete();

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(static::ROUNDS),
            ]);

            DB::table('activity_questions')->where('activity_id', $activityId)->delete();
            foreach (static::ROUNDS as $i => [$target, $choices]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-'.($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['choices' => $choices]),
                    'correct_answer' => json_encode(['answer' => $target]),
                ]);
            }

            $domainId = DB::table('developmental_domains')->where('code', 'reading_language')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kinder 1', 'quarter_number' => static::QUARTER, 'item' => $checklistItem];
            $existing = DB::table('developmental_checklist_items')->where('activity_id', $activityId)->value('id')
                ?? DB::table('developmental_checklist_items')->where($item)->value('id');
            if ($existing) {
                DB::table('developmental_checklist_items')->where('id', $existing)->update(['activity_id' => $activityId, 'item' => $checklistItem, 'updated_at' => now()]);
            } else {
                DB::table('developmental_checklist_items')->insert($item + [
                    'activity_id' => $activityId,
                    'display_order' => (DB::table('developmental_checklist_items')->where(['domain_id' => $domainId, 'quarter_number' => static::QUARTER])->max('display_order') ?? 0) + 1,
                ]);
            }
        });
    }
}

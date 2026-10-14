<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Beginning Vowel Match" game for Reading, Q1 Week 6 Friday; run with: php artisan db:seed --class=BeginningVowelMatchSeeder
class BeginningVowelMatchSeeder extends Seeder
{
    private const CODE = 'RD-Q1W6-03';

    private const CHECKLIST_ITEM = 'Identifies the short beginning vowel sound of familiar words';

    // picture => its beginning vowel, in play order; one picture per vowel, so all five sounds are heard.
    private const PICTURES = [
        'egg' => 'e',
        'umbrella' => 'u',
        'ant' => 'a',
        'octopus' => 'o',
        'insect' => 'i',
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Reading Q1 Week 6 Friday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'reading', 'lessons.quarter_number' => 1, 'lessons.week_number' => 6, 'lessons.day_of_week' => 5, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Reading lesson for Quarter 1, Week 6, Friday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Beginning Vowel Match',
                'description' => 'Listen to the picture name and tap its beginning vowel.',
                'instructions' => 'Listen to the picture name, then tap the flower with its beginning vowel.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(self::PICTURES),
            ]);

            // Cleared and re-added, since kept rows would clash with new question_order values.
            DB::table('activity_questions')->where('activity_id', $activityId)->delete();
            foreach (array_keys(self::PICTURES) as $i => $picture) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => $picture,
                    'question_order' => $i + 1,
                    'question_data' => '{}',
                    'correct_answer' => json_encode(['answer' => self::PICTURES[$picture]]),
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

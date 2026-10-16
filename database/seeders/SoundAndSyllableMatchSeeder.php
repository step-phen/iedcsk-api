<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Sound and Syllable Match" digital activity for Language, Q4 Week 4 Wednesday; run with: php artisan db:seed --class=SoundAndSyllableMatchSeeder
class SoundAndSyllableMatchSeeder extends Seeder
{
    private const CODE = 'LG-Q4W4-02';

    private const CHECKLIST_ITEM = 'Matches heard syllables and beginning word sounds to their written forms';

    // [question data, answer]; a syllable round plays a syllable, a sound round names a picture and asks for its first letter.
    private const ROUNDS = [
        [['kind' => 'syllable', 'target' => 'bi', 'choices' => ['bi', 'ba', 'li']], 'bi'],
        [['kind' => 'sound', 'word' => 'moon', 'choices' => ['s', 'm', 't']], 'm'],
        [['kind' => 'syllable', 'target' => 'lo', 'choices' => ['so', 'lu', 'lo']], 'lo'],
        [['kind' => 'sound', 'word' => 'ball', 'choices' => ['b', 'l', 'm']], 'b'],
        [['kind' => 'syllable', 'target' => 'te', 'choices' => ['ti', 'se', 'te']], 'te'],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'language', 'lessons.quarter_number' => 4, 'lessons.week_number' => 4, 'lessons.day_of_week' => 3, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Language lesson for Quarter 4, Week 4, Wednesday first (LanguageQuarterFourLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Sound and Syllable Match',
                'description' => 'Listen, then tap the matching syllable or beginning sound.',
                'instructions' => 'Listen to the syllable or word, then tap the xylophone bar with the matching syllable or first sound.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('practical_activity_details')->where('activity_id', $activityId)->delete();

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(self::ROUNDS),
            ]);

            DB::table('activity_questions')->where('activity_id', $activityId)->delete();
            foreach (self::ROUNDS as $i => [$data, $answer]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-'.($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode($data),
                    'correct_answer' => json_encode(['answer' => $answer]),
                ]);
            }

            $domainId = DB::table('developmental_domains')->where('code', 'reading_language')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kindergarten', 'quarter_number' => 4, 'item' => self::CHECKLIST_ITEM];
            $existing = DB::table('developmental_checklist_items')->where('activity_id', $activityId)->value('id')
                ?? DB::table('developmental_checklist_items')->where($item)->value('id');
            if ($existing) {
                DB::table('developmental_checklist_items')->where('id', $existing)->update(['activity_id' => $activityId, 'item' => self::CHECKLIST_ITEM, 'updated_at' => now()]);
            } else {
                DB::table('developmental_checklist_items')->insert($item + [
                    'activity_id' => $activityId,
                    'display_order' => (DB::table('developmental_checklist_items')->where(['domain_id' => $domainId, 'quarter_number' => 4])->max('display_order') ?? 0) + 1,
                ]);
            }
        });
    }
}

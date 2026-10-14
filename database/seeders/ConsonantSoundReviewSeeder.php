<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Consonant Sound Review 1" digital activity for Reading, Q2 Week 4 Friday; run with: php artisan db:seed --class=ConsonantSoundReviewSeeder
class ConsonantSoundReviewSeeder extends Seeder
{
    private const CODE = 'RD-Q2W4-03';

    private const CHECKLIST_ITEM = 'Matches pictures to the consonants Bb to Nn by their beginning sound';

    // [picture word, its beginning consonant, the three letters on the train cars]; cat and key never share a round with K and C, since both make /k/.
    private const ROUNDS = [
        ['ball', 'B', ['B', 'D', 'M']],
        ['cat', 'C', ['F', 'C', 'L']],
        ['dog', 'D', ['D', 'B', 'H']],
        ['fish', 'F', ['N', 'J', 'F']],
        ['goat', 'G', ['G', 'D', 'M']],
        ['hat', 'H', ['L', 'H', 'B']],
        ['key', 'K', ['K', 'M', 'H']],
        ['lemon', 'L', ['N', 'F', 'L']],
        ['moon', 'M', ['M', 'N', 'B']],
        ['nose', 'N', ['J', 'M', 'N']],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'reading', 'lessons.quarter_number' => 2, 'lessons.week_number' => 4, 'lessons.day_of_week' => 5, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Reading lesson for Quarter 2, Week 4, Friday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Consonant Sound Review 1',
                'description' => 'Hear a picture name, then tap the consonant it begins with.',
                'instructions' => 'Listen to each picture name, then choose the matching taught consonant.',
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
            foreach (self::ROUNDS as $i => [$word, $letter, $choices]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-'.($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['word' => $word, 'choices' => $choices]),
                    'correct_answer' => json_encode(['answer' => $letter]),
                ]);
            }

            $domainId = DB::table('developmental_domains')->where('code', 'reading_language')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kindergarten', 'quarter_number' => 2, 'item' => self::CHECKLIST_ITEM];
            $existing = DB::table('developmental_checklist_items')->where('activity_id', $activityId)->value('id')
                ?? DB::table('developmental_checklist_items')->where($item)->value('id');
            if ($existing) {
                DB::table('developmental_checklist_items')->where('id', $existing)->update(['activity_id' => $activityId, 'item' => self::CHECKLIST_ITEM, 'updated_at' => now()]);
            } else {
                DB::table('developmental_checklist_items')->insert($item + [
                    'activity_id' => $activityId,
                    'display_order' => (DB::table('developmental_checklist_items')->where(['domain_id' => $domainId, 'quarter_number' => 2])->max('display_order') ?? 0) + 1,
                ]);
            }
        });
    }
}

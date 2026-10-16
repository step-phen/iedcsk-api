<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Listen and Find: Aa, Ee, Ii, Oo, Uu" digital activity for Language, Q2 Week 6 Monday; run with: php artisan db:seed --class=ListenAndFindVowelsSeeder
class ListenAndFindVowelsSeeder extends Seeder
{
    private const CODE = 'LG-Q2W6-01';

    private const CHECKLIST_ITEM = 'Recognizes uppercase and lowercase Aa, Ee, Ii, Oo, Uu after hearing the letter names';

    // [vowel to find, the four planets]; the other vowels and the other case sit beside it, so the child must listen.
    private const ROUNDS = [
        ['A', ['A', 'E', 'a', 'O']],
        ['i', ['e', 'i', 'I', 'u']],
        ['O', ['U', 'O', 'o', 'A']],
        ['u', ['u', 'o', 'U', 'a']],
        ['E', ['I', 'e', 'E', 'A']],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'language', 'lessons.quarter_number' => 2, 'lessons.week_number' => 6, 'lessons.day_of_week' => 1, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Language lesson for Quarter 2, Week 6, Monday first (LanguageQuarterTwoLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Listen and Find: Aa, Ee, Ii, Oo, Uu',
                'description' => 'Listen to the letter name, then tap its uppercase or lowercase form.',
                'instructions' => 'Listen for a big or small vowel, then tap the planet with that letter.',
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
            foreach (self::ROUNDS as $i => [$target, $choices]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-'.($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['target' => $target, 'choices' => $choices]),
                    'correct_answer' => json_encode(['answer' => $target]),
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

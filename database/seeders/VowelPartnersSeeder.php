<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Vowel Partners" game for Reading, Q1 Week 4 Wednesday; run with: php artisan db:seed --class=VowelPartnersSeeder
class VowelPartnersSeeder extends Seeder
{
    private const CODE = 'RD-Q1W4-02';

    private const CHECKLIST_ITEM = 'Matches uppercase and lowercase vowels';

    // [uppercase letter shown, lowercase answer, the five lowercase choices in the order shown].
    private const ROUNDS = [
        ['A', 'a', ['i', 'a', 'u', 'e', 'o']],
        ['E', 'e', ['a', 'o', 'e', 'u', 'i']],
        ['I', 'i', ['u', 'e', 'i', 'a', 'o']],
        ['O', 'o', ['e', 'a', 'o', 'i', 'u']],
        ['U', 'u', ['o', 'i', 'a', 'u', 'e']],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Reading Q1 Week 4 Wednesday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'reading', 'lessons.quarter_number' => 1, 'lessons.week_number' => 4, 'lessons.day_of_week' => 3, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Reading lesson for Quarter 1, Week 4, Wednesday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Vowel Partners',
                'description' => 'Match each uppercase vowel with its lowercase partner.',
                'instructions' => 'Look at the big letter, then tap its small letter partner.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(self::ROUNDS),
            ]);

            // Keyed by the uppercase letter, since that's what makes each round unique.
            foreach (self::ROUNDS as $i => [$upper, $lower, $choices]) {
                DB::table('activity_questions')->updateOrInsert(['activity_id' => $activityId, 'question_key' => $upper], [
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['upper' => $upper, 'choices' => $choices]),
                    'correct_answer' => json_encode(['answer' => $lower]),
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

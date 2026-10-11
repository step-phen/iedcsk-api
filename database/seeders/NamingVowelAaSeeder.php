<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;
use RuntimeException;

// "Naming Vowel Aa" game for Language, Q1 Week 3 Friday; run with: php artisan db:seed --class=NamingVowelAaSeeder
class NamingVowelAaSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'LG-Q1W3-03';

    private const CHECKLIST_ITEM = 'Matches big and small letter Aa';

    // [letter shown, the matching letter, the three letters in the order shown]; the wrong letters share the answer's case, so case alone never gives it away.
    private const ROUNDS = [
        ['A', 'a', ['b', 'a', 'o']],
        ['a', 'A', ['A', 'S', 'M']],
        ['A', 'a', ['t', 'o', 'a']],
        ['a', 'A', ['B', 'A', 'S']],
        ['A', 'a', ['a', 'm', 't']],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Language Q1 Week 3 Friday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'language', 'lessons.quarter_number' => 1, 'lessons.week_number' => 3, 'lessons.day_of_week' => 5, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Language lesson for Quarter 1, Week 3, Friday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Naming Vowel Aa',
                'description' => 'Match big A with small a.',
                'instructions' => 'Look at the letter, then tap the one that is the same.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(self::ROUNDS),
            ]);

            // Keyed by round, since the same letter is asked more than once.
            foreach (self::ROUNDS as $i => [$prompt, $answer, $choices]) {
                DB::table('activity_questions')->updateOrInsert(['activity_id' => $activityId, 'question_key' => 'round-'.($i + 1)], [
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['prompt' => $prompt, 'choices' => $choices]),
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

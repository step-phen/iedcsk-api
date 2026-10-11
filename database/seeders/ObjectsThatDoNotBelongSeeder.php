<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Objects That Do Not Belong" game for Language, Q1 Week 1 Friday; run with: php artisan db:seed --class=ObjectsThatDoNotBelongSeeder
class ObjectsThatDoNotBelongSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'LG-Q1W1-03';

    private const CHECKLIST_ITEM = 'Finds what does not belong';

    // [group, the three that belong, the odd one out]; the game draws each word and names the group aloud.
    private const ROUNDS = [
        ['fruits', ['apple', 'banana', 'orange'], 'car'],
        ['animals', ['cat', 'dog', 'fish'], 'pencil'],
        ['school', ['pencil', 'book', 'eraser'], 'banana'],
        ['clothes', ['hat', 'shirt', 'shoe'], 'apple'],
        ['toys', ['ball', 'doll', 'teddybear'], 'fish'],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Language Q1 Week 1 Friday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'language', 'lessons.quarter_number' => 1, 'lessons.week_number' => 1, 'lessons.day_of_week' => 5, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Language lesson for Quarter 1, Week 1, Friday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Objects That Do Not Belong',
                'description' => 'Tap the object that does not belong with the others.',
                'instructions' => 'Look at the four pictures, then tap the one that does not belong in the group.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(self::ROUNDS),
            ]);

            // The answer is the odd one's word, which is what the game sends for the tile tapped.
            foreach (self::ROUNDS as $i => [$group, $belong, $odd]) {
                DB::table('activity_questions')->updateOrInsert(['activity_id' => $activityId, 'question_key' => $group], [
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['group' => $group, 'belong' => $belong, 'odd' => $odd]),
                    'correct_answer' => json_encode(['answer' => $odd]),
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

<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;
use RuntimeException;

// "Odd One Out" game for Language, Q1 Week 3 Monday; run with: php artisan db:seed --class=OddOneOutSeeder
class OddOneOutSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'LG-Q1W3-01';

    private const CHECKLIST_ITEM = 'Identifies the odd one out';

    // [the odd one out, the four pictures in the order shown, why it is different]; one round each for color, shape, size, group and use.
    private const ROUNDS = [
        ['banana', ['apple', 'banana', 'strawberry', 'cherries'], 'It is yellow. The others are red.'],
        ['box', ['ball', 'orange', 'moon', 'box'], 'It is square. The others are round.'],
        ['ant', ['ant', 'elephant', 'giraffe', 'whale'], 'It is small. The others are big.'],
        ['car', ['grapes', 'car', 'banana', 'strawberry'], 'It is not a fruit. The others are fruits.'],
        ['sock', ['spoon', 'fork', 'sock', 'bowl'], 'We wear it. We eat with the others.'],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Language Q1 Week 3 Monday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'language', 'lessons.quarter_number' => 1, 'lessons.week_number' => 3, 'lessons.day_of_week' => 1, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Language lesson for Quarter 1, Week 3, Monday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Odd One Out',
                'description' => 'Find the picture that is different from the others.',
                'instructions' => 'Look at the four pictures, then tap the one that is different.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(self::ROUNDS),
            ]);

            // Clears questions from an earlier version of this activity before writing the current ones.
            DB::table('activity_questions')->where('activity_id', $activityId)->whereNotIn('question_key', array_column(self::ROUNDS, 0))->delete();
            foreach (self::ROUNDS as $i => [$odd, $choices, $reason]) {
                DB::table('activity_questions')->updateOrInsert(['activity_id' => $activityId, 'question_key' => $odd], [
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['choices' => $choices, 'reason' => $reason]),
                    'correct_answer' => json_encode(['answer' => $odd]),
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

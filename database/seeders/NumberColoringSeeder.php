<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Number Coloring" game for Math, Q1 Week 1 Thursday; run with: php artisan db:seed --class=NumberColoringSeeder
class NumberColoringSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'MT-Q1W1-02';

    private const CHECKLIST_ITEM = 'Colors numbers 1 to 5';

    // [number, crayon color, object shown that many times], played in order since 1 to 5 is a progression.
    private const ROUNDS = [
        [1, 'yellow', 'sun'],
        [2, 'red', 'apple'],
        [3, 'blue', 'balloon'],
        [4, 'pink', 'flower'],
        [5, 'purple', 'star'],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Math Q1 Week 1 Thursday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 1, 'lessons.week_number' => 1, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 1, Week 1, Thursday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Number Coloring',
                'description' => 'Color the numbers 1 to 5, each with the color named for it.',
                'instructions' => 'Pick the crayon that is named, then color inside the number.',
                'activity_format' => 'digital',
                'sequence_order' => 2,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(self::ROUNDS),
            ]);

            // Graded on the first crayon picked for each number; coloring it in is how the round ends, not what scores it.
            foreach (self::ROUNDS as $i => [$number, $color, $object]) {
                DB::table('activity_questions')->updateOrInsert(['activity_id' => $activityId, 'question_key' => "number-{$number}"], [
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['number' => $number, 'color' => $color, 'object' => $object]),
                    'correct_answer' => json_encode(['answer' => $color]),
                ]);
            }

            // The skill this game practises, on the Developmental Checklist under Math Readiness for Quarter 1.
            $domainId = DB::table('developmental_domains')->where('code', 'math_readiness')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kinder 1', 'quarter_number' => 1, 'item' => self::CHECKLIST_ITEM];
            // Matched by activity first, so renaming the item updates its row instead of adding a second one.
            $existing = DB::table('developmental_checklist_items')->where('activity_id', $activityId)->value('id')
                ?? DB::table('developmental_checklist_items')->where($item)->value('id');
            if ($existing) {
                DB::table('developmental_checklist_items')->where('id', $existing)->update(['activity_id' => $activityId, 'item' => self::CHECKLIST_ITEM, 'updated_at' => now()]);
            } else {
                DB::table('developmental_checklist_items')->insert($item + [
                    'activity_id' => $activityId,
                    'display_order' => (DB::table('developmental_checklist_items')->where(['domain_id' => $domainId, 'quarter_number' => 1])->max('display_order') ?? 0) + 1,
                ]);
            }
        });
    }
}

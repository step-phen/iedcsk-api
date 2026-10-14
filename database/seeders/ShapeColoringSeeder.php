<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Shape Coloring" game for Math, Q2 Week 1 Thursday; run with: php artisan db:seed --class=ShapeColoringSeeder
class ShapeColoringSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'MR-Q2W1-02';

    private const CHECKLIST_ITEM = 'Identifies and colors circles, squares, triangles, and rectangles';

    // [shape, crayon color], one of each shape the curriculum names.
    private const ROUNDS = [
        ['circle', 'red'],
        ['square', 'blue'],
        ['triangle', 'yellow'],
        ['rectangle', 'purple'],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Math Q2 Week 1 Thursday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 2, 'lessons.week_number' => 1, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 2, Week 1, Thursday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Shape Coloring',
                'description' => 'Color the circles, squares, triangles, or rectangles named in the instruction.',
                'instructions' => 'Pick the crayon that is named, then color inside the shape.',
                'activity_format' => 'digital',
                'sequence_order' => 2,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(self::ROUNDS),
            ]);

            // Graded on the first crayon picked for each shape; coloring it in is how the round ends, not what scores it.
            foreach (self::ROUNDS as $i => [$shape, $color]) {
                DB::table('activity_questions')->updateOrInsert(['activity_id' => $activityId, 'question_key' => $shape], [
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['shape' => $shape, 'color' => $color]),
                    'correct_answer' => json_encode(['answer' => $color]),
                ]);
            }

            // The skill this game practises, on the Developmental Checklist under Math Readiness for Quarter 2.
            $domainId = DB::table('developmental_domains')->where('code', 'math_readiness')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kindergarten', 'quarter_number' => 2, 'item' => self::CHECKLIST_ITEM];
            // Matched by activity first, so renaming the item updates its row instead of adding a second one.
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

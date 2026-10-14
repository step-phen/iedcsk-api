<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Shape Matching" digital activity for Math, Q2 Week 2 Tuesday; run with: php artisan db:seed --class=ShapeMatchingSeeder
class ShapeMatchingSeeder extends Seeder
{
    private const CODE = 'MR-Q2W2-01';

    private const CHECKLIST_ITEM = 'Matches shapes of the same type despite differences in color or size';

    // [target shape, reference {color,size}, choices: correct first, then 3 decoys — each {shape,color,size}].
    private const ROUNDS = [
        ['circle', ['color' => 'red', 'size' => 'large'], [
            ['shape' => 'circle', 'color' => 'blue', 'size' => 'small'],
            ['shape' => 'square', 'color' => 'green', 'size' => 'medium'],
            ['shape' => 'triangle', 'color' => 'yellow', 'size' => 'large'],
            ['shape' => 'rectangle', 'color' => 'purple', 'size' => 'small'],
        ]],
        ['square', ['color' => 'green', 'size' => 'small'], [
            ['shape' => 'square', 'color' => 'purple', 'size' => 'large'],
            ['shape' => 'circle', 'color' => 'red', 'size' => 'medium'],
            ['shape' => 'triangle', 'color' => 'blue', 'size' => 'small'],
            ['shape' => 'rectangle', 'color' => 'yellow', 'size' => 'large'],
        ]],
        ['triangle', ['color' => 'yellow', 'size' => 'medium'], [
            ['shape' => 'triangle', 'color' => 'red', 'size' => 'small'],
            ['shape' => 'square', 'color' => 'blue', 'size' => 'large'],
            ['shape' => 'rectangle', 'color' => 'green', 'size' => 'medium'],
            ['shape' => 'circle', 'color' => 'purple', 'size' => 'small'],
        ]],
        ['rectangle', ['color' => 'purple', 'size' => 'large'], [
            ['shape' => 'rectangle', 'color' => 'blue', 'size' => 'small'],
            ['shape' => 'circle', 'color' => 'yellow', 'size' => 'medium'],
            ['shape' => 'square', 'color' => 'red', 'size' => 'large'],
            ['shape' => 'triangle', 'color' => 'green', 'size' => 'small'],
        ]],
        ['circle', ['color' => 'blue', 'size' => 'medium'], [
            ['shape' => 'circle', 'color' => 'green', 'size' => 'large'],
            ['shape' => 'square', 'color' => 'yellow', 'size' => 'small'],
            ['shape' => 'triangle', 'color' => 'purple', 'size' => 'medium'],
            ['shape' => 'rectangle', 'color' => 'red', 'size' => 'large'],
        ]],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 2, 'lessons.week_number' => 2, 'lessons.day_of_week' => 2, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 2, Week 2, Tuesday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Shape Matching',
                'description' => 'Match each circle, square, triangle, or rectangle with the same shape.',
                'instructions' => 'Look at the shape card, then tap the one below shaped just like it — color and size do not matter.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(self::ROUNDS),
            ]);

            DB::table('activity_questions')->where('activity_id', $activityId)->delete();
            // Each round's 4 choices are one of each shape, so a choice's own shape name is already a unique id within the round.
            foreach (self::ROUNDS as $i => [$shape, $reference, $choices]) {
                $choicesWithIds = array_map(fn ($c) => $c + ['id' => $c['shape']], $choices);
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-'.($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['shape' => $shape, 'reference' => $reference, 'choices' => $choicesWithIds]),
                    'correct_answer' => json_encode(['answer' => $shape]),
                ]);
            }

            $domainId = DB::table('developmental_domains')->where('code', 'math_readiness')->value('id');
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

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Math Game" digital activity for Math, Q4 Week 4 Tuesday; run with: php artisan db:seed --class=MathGameSeeder
class MathGameSeeder extends Seeder
{
    private const CODE = 'MR-Q4W4-01';

    private const CHECKLIST_ITEM = 'Counts familiar objects and identifies requested shapes in mixed picture tasks';

    // 'count' asks how many toys, 'shape' asks for one shape, 'countShape' asks how many of one shape in a mix; 'answer' is what the child must tap.
    private const ROUNDS = [
        ['kind' => 'count', 'object' => 'ball', 'count' => 4, 'choices' => [3, 4, 5], 'answer' => '4'],
        ['kind' => 'shape', 'ask' => 'triangle', 'choices' => ['circle', 'triangle', 'square'], 'answer' => 'triangle'],
        ['kind' => 'countShape', 'ask' => 'circle', 'shapes' => ['circle', 'square', 'circle', 'triangle', 'circle', 'rectangle'], 'choices' => [2, 3, 4], 'answer' => '3'],
        ['kind' => 'shape', 'ask' => 'rectangle', 'choices' => ['square', 'rectangle', 'triangle'], 'answer' => 'rectangle'],
        ['kind' => 'count', 'object' => 'car', 'count' => 7, 'choices' => [6, 7, 8], 'answer' => '7'],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 4, 'lessons.week_number' => 4, 'lessons.day_of_week' => 2, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 4, Week 4, Tuesday first (MathQuarterFourWeeksFourFiveLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Math Game',
                'description' => 'Count familiar objects and identify the shapes requested in the instruction.',
                'instructions' => 'Listen to each task, then count the toys or find the shape, and tap your answer.',
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
            foreach (self::ROUNDS as $i => $round) {
                $answer = $round['answer'];
                unset($round['answer']);
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-'.($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode($round),
                    'correct_answer' => json_encode(['answer' => $answer]),
                ]);
            }

            $domainId = DB::table('developmental_domains')->where('code', 'math_readiness')->value('id');
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

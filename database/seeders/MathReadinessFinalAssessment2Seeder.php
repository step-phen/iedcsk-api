<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Math Readiness Final Assessment" digital activity for Math, Q2 Week 7 Thursday; run with: php artisan db:seed --class=MathReadinessFinalAssessment2Seeder
class MathReadinessFinalAssessment2Seeder extends Seeder
{
    private const CODE = 'MR-Q2W7-02';

    private const CHECKLIST_ITEM = 'Demonstrates shape recognition, matching, AB pattern completion, and one-feature sorting';

    // One round per skill practiced this quarter, with its own content so this never repeats the Review's exact rounds.
    private const ROUNDS = [
        ['kind' => 'shape', 'shape' => 'triangle', 'reference' => ['color' => 'purple', 'size' => 'large'], 'choices' => [
            ['shape' => 'triangle', 'color' => 'red', 'size' => 'small'],
            ['shape' => 'square', 'color' => 'green', 'size' => 'medium'],
            ['shape' => 'rectangle', 'color' => 'blue', 'size' => 'large'],
        ]],
        ['kind' => 'pattern', 'pattern' => ['square', 'triangle', 'square', 'triangle'], 'choices' => ['square', 'circle', 'rectangle']],
        ['kind' => 'patternGap', 'pattern' => ['circle', 'square', 'circle', null, 'circle'], 'choices' => ['square', 'triangle', 'rectangle']],
        ['kind' => 'color', 'object' => 'grapes', 'bins' => ['purple', 'red', 'yellow']],
        ['kind' => 'namedShape', 'shape' => 'circle', 'choices' => ['circle', 'square', 'rectangle']],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 2, 'lessons.week_number' => 7, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 2, Week 7, Thursday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Math Readiness Final Assessment',
                'description' => 'Answer tasks on taught shapes, repeating patterns, and color or shape sorting, with spoken instructions.',
                'instructions' => 'Listen to each task, then tap the picture that answers it.',
                'activity_format' => 'digital',
                'sequence_order' => 2,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(self::ROUNDS),
            ]);

            DB::table('activity_questions')->where('activity_id', $activityId)->delete();
            foreach (self::ROUNDS as $i => $round) {
                $answer = match ($round['kind']) {
                    'shape', 'namedShape' => $round['shape'],
                    'pattern', 'patternGap' => $round['choices'][0],
                    'color' => $round['bins'][0],
                };
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-'.($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode($round),
                    'correct_answer' => json_encode(['answer' => $answer]),
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

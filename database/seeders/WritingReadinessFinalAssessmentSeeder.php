<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Writing Readiness Final Assessment" game for Writing, Q1 Week 7 Friday; run with: php artisan db:seed --class=WritingReadinessFinalAssessmentSeeder
class WritingReadinessFinalAssessmentSeeder extends Seeder
{
    private const CODE = 'WR-Q1W7-03';

    private const CHECKLIST_ITEM = 'Independently shows writing tools, posture, and stroke control for lines, curves, and shapes';

    // key => [question data, answer], one task each in play order; a trace or copy task's answer is "correct" when its first try passes.
    private const PAGES = [
        'tool-pencil' => [['type' => 'tool', 'choices' => ['spoon', 'pencil', 'ball']], 'pencil'],
        'tool-crayons' => [['type' => 'tool', 'choices' => ['crayons', 'apple', 'toy-car']], 'crayons'],
        'tool-eraser' => [['type' => 'tool', 'choices' => ['teddy-bear', 'eraser', 'spoon']], 'eraser'],
        'posture' => [['type' => 'posture', 'choices' => ['upright', 'slouch']], 'upright'],
        'line' => [['type' => 'trace', 'stroke' => 'line'], 'correct'],
        'vertical' => [['type' => 'trace', 'stroke' => 'vertical'], 'correct'],
        'left-slant' => [['type' => 'trace', 'stroke' => 'left-slant'], 'correct'],
        'slant' => [['type' => 'trace', 'stroke' => 'slant'], 'correct'],
        'curve' => [['type' => 'trace', 'stroke' => 'curve'], 'correct'],
        'overcurve' => [['type' => 'trace', 'stroke' => 'overcurve'], 'correct'],
        'undercurve' => [['type' => 'trace', 'stroke' => 'undercurve'], 'correct'],
        'zigzag' => [['type' => 'trace', 'stroke' => 'zigzag'], 'correct'],
        'copy-circle' => [['type' => 'copy', 'shape' => 'circle'], 'correct'],
        'copy-square' => [['type' => 'copy', 'shape' => 'square'], 'correct'],
        'copy-triangle' => [['type' => 'copy', 'shape' => 'triangle'], 'correct'],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Writing Q1 Week 7 Friday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'writing', 'lessons.quarter_number' => 1, 'lessons.week_number' => 7, 'lessons.day_of_week' => 5, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Writing lesson for Quarter 1, Week 7, Friday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Writing Readiness Final Assessment',
                'description' => 'Identify writing tools, demonstrate proper posture, and independently trace or copy selected taught strokes and shapes.',
                'instructions' => 'Listen to each task, then tap the right picture, trace the line, or copy the shape all by yourself to fill the writing passport.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tracing',
                'configuration' => '{}',
                'max_score' => count(self::PAGES),
            ]);

            // Cleared and re-added, since kept rows would clash with new question_order values.
            DB::table('activity_questions')->where('activity_id', $activityId)->delete();
            $order = 0;
            foreach (self::PAGES as $key => [$data, $answer]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => $key,
                    'question_order' => ++$order,
                    'question_data' => json_encode($data),
                    'correct_answer' => json_encode(['answer' => $answer]),
                ]);
            }

            // The skill this game practises, on the Developmental Checklist under Writing Readiness for Quarter 1.
            $domainId = DB::table('developmental_domains')->where('code', 'writing_readiness')->value('id');
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

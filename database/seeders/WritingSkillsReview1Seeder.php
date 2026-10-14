<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Writing Skills Review 1" game for Writing, Q1 Week 4 Friday; run with: php artisan db:seed --class=WritingSkillsReview1Seeder
class WritingSkillsReview1Seeder extends Seeder
{
    private const CODE = 'WR-Q1W4-03';

    private const CHECKLIST_ITEM = 'Reviews writing tools, proper posture, and tracing of lines and curves';

    // key => [question data, answer], one notebook page each in play order; a trace page's answer is "correct" when its first try follows the guide.
    private const PAGES = [
        'tool' => [['type' => 'tool', 'choices' => ['ball', 'pencil', 'spoon']], 'pencil'],
        'posture' => [['type' => 'posture', 'choices' => ['slouch', 'upright']], 'upright'],
        'line' => [['type' => 'trace', 'stroke' => 'line'], 'correct'],
        'vertical' => [['type' => 'trace', 'stroke' => 'vertical'], 'correct'],
        'slant' => [['type' => 'trace', 'stroke' => 'slant'], 'correct'],
        'left-slant' => [['type' => 'trace', 'stroke' => 'left-slant'], 'correct'],
        'tool-2' => [['type' => 'tool', 'choices' => ['teddy-bear', 'apple', 'crayons']], 'crayons'],
        'curve' => [['type' => 'trace', 'stroke' => 'curve'], 'correct'],
        'overcurve' => [['type' => 'trace', 'stroke' => 'overcurve'], 'correct'],
        'undercurve' => [['type' => 'trace', 'stroke' => 'undercurve'], 'correct'],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Writing Q1 Week 4 Friday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'writing', 'lessons.quarter_number' => 1, 'lessons.week_number' => 4, 'lessons.day_of_week' => 5, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Writing lesson for Quarter 1, Week 4, Friday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Writing Skills Review 1',
                'description' => 'Identify writing tools, demonstrate proper writing posture, and trace selected lines and curves.',
                'instructions' => 'Listen to each notebook page, then tap the right picture or trace the line from the green dot to the star.',
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
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kindergarten', 'quarter_number' => 1, 'item' => self::CHECKLIST_ITEM];
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

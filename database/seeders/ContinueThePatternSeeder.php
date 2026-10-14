<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Continue the Pattern" digital activity for Math, Q2 Week 3 Tuesday; run with: php artisan db:seed --class=ContinueThePatternSeeder
class ContinueThePatternSeeder extends Seeder
{
    private const CODE = 'MR-Q2W3-01';

    private const CHECKLIST_ITEM = 'Recognizes and extends simple AB patterns using colors or shapes';

    // [4-item AB pattern, 3 answer choices — the first choice is what continues the pattern].
    private const ROUNDS = [
        [['circle', 'square', 'circle', 'square'], ['circle', 'triangle', 'rectangle']],
        [['square', 'triangle', 'square', 'triangle'], ['square', 'circle', 'rectangle']],
        [['triangle', 'rectangle', 'triangle', 'rectangle'], ['triangle', 'circle', 'square']],
        [['rectangle', 'circle', 'rectangle', 'circle'], ['rectangle', 'square', 'triangle']],
        [['circle', 'triangle', 'circle', 'triangle'], ['circle', 'square', 'rectangle']],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 2, 'lessons.week_number' => 3, 'lessons.day_of_week' => 2, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 2, Week 3, Tuesday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Continue the Pattern',
                'description' => 'Look at the repeating pattern, then choose the item that comes next.',
                'instructions' => 'Look at the row of shapes, then tap the one that continues the pattern.',
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
            foreach (self::ROUNDS as $i => [$pattern, $choices]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-'.($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['pattern' => $pattern, 'choices' => $choices]),
                    'correct_answer' => json_encode(['answer' => $choices[0]]),
                ]);
            }

            $domainId = DB::table('developmental_domains')->where('code', 'math_readiness')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kinder 1', 'quarter_number' => 2, 'item' => self::CHECKLIST_ITEM];
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

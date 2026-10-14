<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Math Skills Review 4" digital activity for Math, Q4 Week 4 Thursday; run with: php artisan db:seed --class=MathSkillsReview4Seeder
class MathSkillsReview4Seeder extends Seeder
{
    private const CODE = 'MR-Q4W4-02';

    private const CHECKLIST_ITEM = 'Completes counting, addition, take-away, shape, pattern, and comparison tasks at guided stations';

    // One station task per round; compare answers 'left' or 'right', pattern and shape answer a shape id, the rest answer a numeral.
    private const ROUNDS = [
        ['kind' => 'count', 'object' => 'leaf', 'count' => 6, 'choices' => [5, 6, 7], 'answer' => '6'],
        ['kind' => 'add', 'object' => 'shell', 'groupA' => 2, 'groupB' => 3, 'choices' => [4, 5, 6], 'answer' => '5'],
        ['kind' => 'takeAway', 'object' => 'balloon', 'start' => 5, 'removed' => 1, 'choices' => [3, 4, 5], 'answer' => '4'],
        ['kind' => 'shape', 'ask' => 'square', 'choices' => ['triangle', 'square', 'circle'], 'answer' => 'square'],
        ['kind' => 'pattern', 'sequence' => ['circle', 'triangle', 'circle', 'triangle'], 'choices' => ['circle', 'triangle', 'square'], 'answer' => 'circle'],
        ['kind' => 'compare', 'object' => 'apple', 'left' => 5, 'right' => 2, 'ask' => 'more', 'answer' => 'left'],
        ['kind' => 'add', 'object' => 'leaf', 'groupA' => 4, 'groupB' => 4, 'choices' => [7, 8, 9], 'answer' => '8'],
        ['kind' => 'takeAway', 'object' => 'shell', 'start' => 3, 'removed' => 3, 'choices' => [0, 1, 2], 'answer' => '0'],
        ['kind' => 'pattern', 'sequence' => ['square', 'rectangle', 'square', 'rectangle'], 'choices' => ['rectangle', 'square', 'triangle'], 'answer' => 'square'],
        ['kind' => 'compare', 'object' => 'balloon', 'left' => 6, 'right' => 3, 'ask' => 'fewer', 'answer' => 'right'],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 4, 'lessons.week_number' => 4, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 4, Week 4, Thursday first (MathQuarterFourWeeksFourFiveLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Math Skills Review 4',
                'description' => 'Complete taught math tasks at guided stations: counting, adding, taking away, shapes, patterns, and comparing.',
                'instructions' => 'Visit each station, listen to the task, then tap your answer.',
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
                    'question_key' => 'round-' . ($i + 1),
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

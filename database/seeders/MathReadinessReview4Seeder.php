<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Math Readiness Review" digital activity for Math, Q4 Week 7 Tuesday; run with: php artisan db:seed --class=MathReadinessReview4Seeder
class MathReadinessReview4Seeder extends Seeder
{
    private const CODE = 'MR-Q4W7-01';

    private const CHECKLIST_ITEM = 'Joins, takes away, orders numbers, counts, and identifies shapes in mixed review tasks';

    // One fiesta task per round; compare answers 'left' or 'right', shape answers a shape id, the rest answer a numeral.
    private const ROUNDS = [
        ['kind' => 'add', 'object' => 'mango', 'groupA' => 3, 'groupB' => 4, 'choices' => [6, 7, 8], 'answer' => '7'],
        ['kind' => 'takeAway', 'object' => 'candy', 'start' => 6, 'removed' => 2, 'choices' => [3, 4, 5], 'answer' => '4'],
        ['kind' => 'next', 'sequence' => [3, 4, 5, 6], 'choices' => [7, 8, 5], 'answer' => '7'],
        ['kind' => 'missing', 'sequence' => [2, 3, null, 5, 6], 'choices' => [4, 7, 1], 'answer' => '4'],
        ['kind' => 'count', 'object' => 'lantern', 'count' => 8, 'choices' => [7, 8, 9], 'answer' => '8'],
        ['kind' => 'shape', 'ask' => 'circle', 'choices' => ['square', 'circle', 'triangle'], 'answer' => 'circle'],
        ['kind' => 'add', 'object' => 'balloon', 'groupA' => 5, 'groupB' => 5, 'choices' => [8, 9, 10], 'answer' => '10'],
        ['kind' => 'takeAway', 'object' => 'mango', 'start' => 4, 'removed' => 4, 'choices' => [0, 1, 4], 'answer' => '0'],
        ['kind' => 'compare', 'object' => 'candy', 'left' => 3, 'right' => 7, 'ask' => 'more', 'answer' => 'right'],
        ['kind' => 'missing', 'sequence' => [6, 7, 8, null, 10], 'choices' => [9, 8, 6], 'answer' => '9'],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 4, 'lessons.week_number' => 7, 'lessons.day_of_week' => 2, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 4, Week 7, Tuesday first (MathQuarterFourWeeksSixSevenLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Math Readiness Review',
                'description' => 'Complete mixed picture tasks on joining, taking away, number order, and taught math concepts.',
                'instructions' => 'Listen to each fiesta task, then tap your answer.',
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

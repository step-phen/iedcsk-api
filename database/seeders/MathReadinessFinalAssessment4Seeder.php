<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Math Readiness Final Assessment" digital activity for Math, Q4 Week 7 Thursday; run with: php artisan db:seed --class=MathReadinessFinalAssessment4Seeder
class MathReadinessFinalAssessment4Seeder extends Seeder
{
    private const CODE = 'MR-Q4W7-02';

    private const CHECKLIST_ITEM = 'Demonstrates addition and subtraction within 10 and forward number order';

    // One picture task per round, all answered with a numeral.
    private const ROUNDS = [
        ['kind' => 'add', 'object' => 'star', 'groupA' => 2, 'groupB' => 3, 'choices' => [4, 5, 6], 'answer' => '5'],
        ['kind' => 'takeAway', 'object' => 'pencil', 'start' => 5, 'removed' => 1, 'choices' => [3, 4, 6], 'answer' => '4'],
        ['kind' => 'next', 'sequence' => [1, 2, 3, 4], 'choices' => [5, 6, 3], 'answer' => '5'],
        ['kind' => 'missing', 'sequence' => [4, null, 6, 7, 8], 'choices' => [5, 3, 9], 'answer' => '5'],
        ['kind' => 'add', 'object' => 'book', 'groupA' => 4, 'groupB' => 2, 'choices' => [5, 6, 7], 'answer' => '6'],
        ['kind' => 'takeAway', 'object' => 'crayon', 'start' => 7, 'removed' => 3, 'choices' => [3, 4, 5], 'answer' => '4'],
        ['kind' => 'next', 'sequence' => [5, 6, 7, 8], 'choices' => [7, 9, 10], 'answer' => '9'],
        ['kind' => 'missing', 'sequence' => [1, 2, 3, null, 5], 'choices' => [4, 6, 2], 'answer' => '4'],
        ['kind' => 'add', 'object' => 'pencil', 'groupA' => 6, 'groupB' => 3, 'choices' => [8, 9, 10], 'answer' => '9'],
        ['kind' => 'takeAway', 'object' => 'star', 'start' => 3, 'removed' => 3, 'choices' => [0, 1, 3], 'answer' => '0'],
        ['kind' => 'next', 'sequence' => [6, 7, 8, 9], 'choices' => [10, 9, 7], 'answer' => '10'],
        ['kind' => 'missing', 'sequence' => [null, 4, 5, 6, 7], 'choices' => [3, 2, 8], 'answer' => '3'],
        ['kind' => 'add', 'object' => 'crayon', 'groupA' => 1, 'groupB' => 7, 'choices' => [7, 8, 9], 'answer' => '8'],
        ['kind' => 'takeAway', 'object' => 'book', 'start' => 9, 'removed' => 5, 'choices' => [4, 5, 3], 'answer' => '4'],
        ['kind' => 'missing', 'sequence' => [6, 7, null, 9, 10], 'choices' => [8, 5, 6], 'answer' => '8'],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 4, 'lessons.week_number' => 7, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 4, Week 7, Thursday first (MathQuarterFourWeeksSixSevenLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Math Readiness Final Assessment',
                'description' => 'Answer picture tasks on addition and subtraction within 10 and number order, with spoken instructions.',
                'instructions' => 'Listen to each task, then tap the number that answers it, all by yourself.',
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

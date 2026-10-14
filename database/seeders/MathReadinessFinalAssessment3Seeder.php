<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Math Readiness Final Assessment" digital activity for Math, Q3 Week 7 Thursday; run with: php artisan db:seed --class=MathReadinessFinalAssessment3Seeder
class MathReadinessFinalAssessment3Seeder extends Seeder
{
    private const CODE = 'MR-Q3W7-02';

    private const CHECKLIST_ITEM = 'Demonstrates size and length comparison, ordering of three objects, and near, far, inside, and outside concepts';

    // Three tasks per skill with space pictures, so it never repeats the Review; order rounds hide one of three sizes and 'answer' is the size that fills the gap.
    private const ROUNDS = [
        ['kind' => 'size', 'object' => 'planet', 'ask' => 'big', 'answer' => 'big'],
        ['kind' => 'length', 'object' => 'comet', 'ask' => 'long', 'answer' => 'long'],
        ['kind' => 'order', 'object' => 'star', 'missing' => 'medium', 'answer' => 'medium'],
        ['kind' => 'distance', 'object' => 'ufo', 'ask' => 'near', 'side' => 'left', 'answer' => 'near'],
        ['kind' => 'position', 'object' => 'astronaut', 'ask' => 'inside', 'answer' => 'inside'],
        ['kind' => 'size', 'object' => 'moon', 'ask' => 'small', 'answer' => 'small'],
        ['kind' => 'length', 'object' => 'comet', 'ask' => 'short', 'answer' => 'short'],
        ['kind' => 'order', 'object' => 'planet', 'missing' => 'small', 'answer' => 'small'],
        ['kind' => 'distance', 'object' => 'astronaut', 'ask' => 'far', 'side' => 'right', 'answer' => 'far'],
        ['kind' => 'position', 'object' => 'alien', 'ask' => 'outside', 'answer' => 'outside'],
        ['kind' => 'size', 'object' => 'rocket', 'ask' => 'big', 'answer' => 'big'],
        ['kind' => 'length', 'object' => 'comet', 'ask' => 'long', 'answer' => 'long'],
        ['kind' => 'order', 'object' => 'alien', 'missing' => 'big', 'answer' => 'big'],
        ['kind' => 'distance', 'object' => 'rocket', 'ask' => 'far', 'side' => 'left', 'answer' => 'far'],
        ['kind' => 'position', 'object' => 'robot', 'ask' => 'inside', 'answer' => 'inside'],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 3, 'lessons.week_number' => 7, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 3, Week 7, Thursday first (MathQuarterThreeWeeksSixSevenLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Math Readiness Final Assessment',
                'description' => 'Answer picture tasks on size, length, order, near and far, and inside and outside, with spoken instructions.',
                'instructions' => 'Listen to each space task and tap your answer all by yourself.',
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
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kindergarten', 'quarter_number' => 3, 'item' => self::CHECKLIST_ITEM];
            $existing = DB::table('developmental_checklist_items')->where('activity_id', $activityId)->value('id')
                ?? DB::table('developmental_checklist_items')->where($item)->value('id');
            if ($existing) {
                DB::table('developmental_checklist_items')->where('id', $existing)->update(['activity_id' => $activityId, 'item' => self::CHECKLIST_ITEM, 'updated_at' => now()]);
            } else {
                DB::table('developmental_checklist_items')->insert($item + [
                    'activity_id' => $activityId,
                    'display_order' => (DB::table('developmental_checklist_items')->where(['domain_id' => $domainId, 'quarter_number' => 3])->max('display_order') ?? 0) + 1,
                ]);
            }
        });
    }
}

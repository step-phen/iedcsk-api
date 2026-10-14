<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Compare and Choose" digital activity for Math, Q3 Week 6 Tuesday; run with: php artisan db:seed --class=CompareAndChooseSeeder
class CompareAndChooseSeeder extends Seeder
{
    private const CODE = 'MR-Q3W6-01';

    private const CHECKLIST_ITEM = 'Chooses the object that matches a requested size word or length word';

    // Size rounds compare overall size (big/small) and length rounds compare how long (long/short); 'answer' is the choice the child must tap.
    private const ROUNDS = [
        ['kind' => 'size', 'object' => 'fish', 'ask' => 'big', 'answer' => 'big'],
        ['kind' => 'length', 'object' => 'seaweed', 'ask' => 'long', 'answer' => 'long'],
        ['kind' => 'size', 'object' => 'crab', 'ask' => 'small', 'answer' => 'small'],
        ['kind' => 'length', 'object' => 'rope', 'ask' => 'short', 'answer' => 'short'],
        ['kind' => 'size', 'object' => 'turtle', 'ask' => 'big', 'answer' => 'big'],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 3, 'lessons.week_number' => 6, 'lessons.day_of_week' => 2, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 3, Week 6, Tuesday first (MathQuarterThreeWeeksSixSevenLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Compare and Choose',
                'description' => 'Listen to the instruction, then choose the object that matches the requested size or length.',
                'instructions' => 'Listen carefully for big or small, or long or short, then tap the matching sea thing.',
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
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kinder 1', 'quarter_number' => 3, 'item' => self::CHECKLIST_ITEM];
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

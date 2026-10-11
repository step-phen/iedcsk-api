<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Add the Story" digital activity for Math, Q4 Week 5 Tuesday; run with: php artisan db:seed --class=AddTheStorySeeder
class AddTheStorySeeder extends Seeder
{
    private const CODE = 'MR-Q4W5-01';

    private const CHECKLIST_ITEM = 'Solves a short joining story by combining pictured groups, totaling up to 10';

    // [story character, how many at first, how many more join, numeral choices]; the story lines live in AddTheStory.data.ts.
    private const ROUNDS = [
        ['bird', 3, 2, [4, 5, 6]],
        ['duck', 4, 3, [6, 7, 8]],
        ['butterfly', 2, 4, [5, 6, 7]],
        ['fish', 5, 4, [8, 9, 10]],
        ['bee', 6, 2, [7, 8, 9]],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 4, 'lessons.week_number' => 5, 'lessons.day_of_week' => 2, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 4, Week 5, Tuesday first (MathQuarterFourWeeksFourFiveLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Add the Story',
                'description' => 'Listen to the joining story, count the combined objects, then choose the total.',
                'instructions' => 'Listen to the story, watch more friends join, count them all, then tap the total.',
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
            foreach (self::ROUNDS as $i => [$character, $first, $more, $choices]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-' . ($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['character' => $character, 'first' => $first, 'more' => $more, 'choices' => $choices]),
                    'correct_answer' => json_encode(['answer' => (string) ($first + $more)]),
                ]);
            }

            $domainId = DB::table('developmental_domains')->where('code', 'math_readiness')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kinder 1', 'quarter_number' => 4, 'item' => self::CHECKLIST_ITEM];
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

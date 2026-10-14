<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Count and Add" digital activity for Math, Q4 Week 1 Tuesday; run with: php artisan db:seed --class=CountAndAddSeeder
class CountAndAddSeeder extends Seeder
{
    private const CODE = 'MR-Q4W1-01';

    private const CHECKLIST_ITEM = 'Combines two small groups and counts the total, up to 10';

    // [object, basket A count, basket B count, numeral choices]; the choices must include the sum.
    private const ROUNDS = [
        ['ladybug', 2, 3, [4, 5, 6]],
        ['apple', 3, 4, [6, 7, 8]],
        ['flower', 1, 5, [5, 6, 7]],
        ['leaf', 4, 4, [7, 8, 9]],
        ['acorn', 2, 6, [7, 8, 9]],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 4, 'lessons.week_number' => 1, 'lessons.day_of_week' => 2, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 4, Week 1, Tuesday first (MathQuarterFourWeeksOneTwoThreeLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Count and Add',
                'description' => 'Combine the pictured groups, count all the objects, then tap the total.',
                'instructions' => 'Count both baskets together, then tap the number that shows the total.',
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
            foreach (self::ROUNDS as $i => [$object, $groupA, $groupB, $choices]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-'.($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['object' => $object, 'groupA' => $groupA, 'groupB' => $groupB, 'choices' => $choices]),
                    'correct_answer' => json_encode(['answer' => (string) ($groupA + $groupB)]),
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

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "More or Less?" comparing game for Math, Q1 Week 4 Tuesday; run with: php artisan db:seed --class=MoreOrLessSeeder
class MoreOrLessSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'MR-Q1W4-01';

    private const CHECKLIST_ITEM = 'Compares two small groups and identifies which has more or fewer';

    // [object, left seat, right seat, which to find]; the child taps the side with more or fewer, so the answer is "left" or "right".
    private const ROUNDS = [
        ['apple', 4, 2, 'more'],
        ['duck', 1, 3, 'fewer'],
        ['star', 2, 5, 'more'],
        ['fish', 5, 3, 'fewer'],
        ['flower', 6, 2, 'more'],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Math Q1 Week 4 Tuesday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 1, 'lessons.week_number' => 4, 'lessons.day_of_week' => 2, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 1, Week 4, Tuesday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'More or Less?',
                'description' => 'Count or compare the two groups, then tap the group with more or fewer objects as instructed.',
                'instructions' => 'Look at both seats of the seesaw, then tap the side with more or fewer, as you are told.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(self::ROUNDS),
            ]);

            // Cleared and re-added, since kept rows would clash with new question_order values.
            DB::table('activity_questions')->where('activity_id', $activityId)->delete();
            foreach (self::ROUNDS as $i => [$object, $left, $right, $ask]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => $object,
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['object' => $object, 'left' => $left, 'right' => $right, 'ask' => $ask]),
                    'correct_answer' => json_encode(['answer' => ($left > $right) === ($ask === 'more') ? 'left' : 'right']),
                ]);
            }

            // The skill this game practises, on the Developmental Checklist under Math Readiness for Quarter 1.
            $domainId = DB::table('developmental_domains')->where('code', 'math_readiness')->value('id');
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

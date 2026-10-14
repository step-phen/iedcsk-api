<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Count the Objects" counting game for Math, Q1 Week 2 Tuesday; run with: php artisan db:seed --class=CountTheObjectsSeeder
class CountTheObjectsSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'MR-Q1W2-01';

    private const CHECKLIST_ITEM = 'Counts objects one by one and identifies totals from 1 to 10';

    // [object, how many, numeral choices]; the child taps each object into the jar to count it, then taps the numeral.
    private const ROUNDS = [
        ['apple', 3, [2, 3, 4, 5]],
        ['duck', 6, [5, 6, 7, 9]],
        ['star', 8, [6, 7, 8, 10]],
        ['ball', 5, [4, 5, 6, 8]],
        ['flower', 10, [7, 8, 9, 10]],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Math Q1 Week 2 Tuesday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 1, 'lessons.week_number' => 2, 'lessons.day_of_week' => 2, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 1, Week 2, Tuesday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Count the Objects',
                'description' => 'Count the pictures, then tap the numeral that shows how many there are.',
                'instructions' => 'Tap each picture to count it into the jar, then tap the number that shows how many.',
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
            foreach (self::ROUNDS as $i => [$object, $count, $choices]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => $object,
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['object' => $object, 'count' => $count, 'choices' => $choices]),
                    'correct_answer' => json_encode(['answer' => (string) $count]),
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

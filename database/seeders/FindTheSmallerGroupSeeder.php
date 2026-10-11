<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Find the Smaller Group" comparing game for Math, Q1 Week 5 Thursday; run with: php artisan db:seed --class=FindTheSmallerGroupSeeder
class FindTheSmallerGroupSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'MR-Q1W5-02';

    private const CHECKLIST_ITEM = 'Compares two groups of up to 10 objects and identifies the group with fewer';

    // [object, left group, right group, side drawn with big pictures or null]; the big pictures sit on the smaller group to test counting over size, and the answer is "left" or "right".
    private const ROUNDS = [
        ['ball', 3, 7, 'left'],
        ['fish', 8, 5, null],
        ['flower', 6, 2, 'right'],
        ['banana', 4, 10, null],
        ['duck', 5, 9, 'left'],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Math Q1 Week 5 Thursday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 1, 'lessons.week_number' => 5, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 1, Week 5, Thursday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Find the Smaller Group',
                'description' => 'Tap the group with fewer objects. Compare quantities rather than the size of individual objects.',
                'instructions' => 'Look at both clue cards, then tap the group with fewer. Count how many, not how big.',
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
            foreach (self::ROUNDS as $i => [$object, $left, $right, $big]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => $object,
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['object' => $object, 'left' => $left, 'right' => $right, 'big' => $big]),
                    'correct_answer' => json_encode(['answer' => $left < $right ? 'left' : 'right']),
                ]);
            }

            // The skill this game practises, on the Developmental Checklist under Math Readiness for Quarter 1.
            $domainId = DB::table('developmental_domains')->where('code', 'math_readiness')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kinder 1', 'quarter_number' => 1, 'item' => self::CHECKLIST_ITEM];
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

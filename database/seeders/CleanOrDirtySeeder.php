<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Clean or Dirty?" game for MAPEH Health, Q1 Week 1 Tuesday; run with: php artisan db:seed --class=CleanOrDirtySeeder
class CleanOrDirtySeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'MP-Q1W1-03';

    private const CHECKLIST_ITEM = 'Tells clean from dirty when caring for the body';

    // [picture, clean or dirty]; each key must match a picture drawn in the game, the game shuffles the order.
    private const PICTURES = [
        ['washing-hands', 'clean'],
        ['brushing-teeth', 'clean'],
        ['washing-face', 'clean'],
        ['muddy-hands', 'dirty'],
        ['dirty-teeth', 'dirty'],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing MAPEH Health lesson for Quarter 1, Week 1, Tuesday.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->where(['modules.code' => 'mapeh', 'developmental_domains.code' => 'health_habits', 'lessons.quarter_number' => 1, 'lessons.week_number' => 1, 'lessons.day_of_week' => 2, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the MAPEH Health lesson for Quarter 1, Week 1, Tuesday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Clean or Dirty?',
                'description' => 'Look at each picture and say if it is clean or dirty.',
                'instructions' => 'Look at the picture, then tap CLEAN or DIRTY.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(self::PICTURES),
            ]);

            // Pictures dropped from the list leave the game too.
            DB::table('activity_questions')->where('activity_id', $activityId)->whereNotIn('question_key', array_column(self::PICTURES, 0))->delete();

            foreach (self::PICTURES as $i => [$picture, $answer]) {
                DB::table('activity_questions')->updateOrInsert(['activity_id' => $activityId, 'question_key' => $picture], [
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['picture' => $picture]),
                    'correct_answer' => json_encode(['answer' => $answer]),
                ]);
            }

            // The skill this game practises, on the Developmental Checklist under Health, Works, Habits and Attitude for Quarter 1.
            $domainId = DB::table('developmental_domains')->where('code', 'health_habits')->value('id');
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

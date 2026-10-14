<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Left Curves" tracing game for Writing, Q1 Week 3 Wednesday; run with: php artisan db:seed --class=LeftCurvesSeeder
class LeftCurvesSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'WR-Q1W3-02';

    private const CHECKLIST_ITEM = 'Traces left curved lines';

    // One question per row of left curves, in the order the rows are traced; the game draws every row itself.
    private const ROWS = ['row-1', 'row-2', 'row-3', 'row-4', 'row-5'];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Writing Q1 Week 3 Wednesday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'writing', 'lessons.quarter_number' => 1, 'lessons.week_number' => 3, 'lessons.day_of_week' => 3, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Writing lesson for Quarter 1, Week 3, Wednesday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Left Curve Paths',
                'description' => 'Trace each left curve from the marked starting point.',
                'instructions' => 'Use your finger to trace each curved line in the row from the top, staying on the dotted line.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tracing',
                'configuration' => '{}',
                'max_score' => count(self::ROWS),
            ]);

            // A row always completes once every curve is traced, so the game sends "correct" for each row it finishes.
            foreach (self::ROWS as $i => $row) {
                DB::table('activity_questions')->updateOrInsert(['activity_id' => $activityId, 'question_key' => $row], [
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['row' => $row]),
                    'correct_answer' => json_encode(['answer' => 'correct']),
                ]);
            }

            // The skill this game practises, on the Developmental Checklist under Writing Readiness for Quarter 1.
            $domainId = DB::table('developmental_domains')->where('code', 'writing_readiness')->value('id');
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

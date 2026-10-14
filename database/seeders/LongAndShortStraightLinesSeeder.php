<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Long and Short Straight Lines" tracing game for Writing, Q1 Week 2 Wednesday; run with: php artisan db:seed --class=LongAndShortStraightLinesSeeder
class LongAndShortStraightLinesSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'WR-Q1W2-02';

    private const CHECKLIST_ITEM = 'Traces long and short straight lines';

    // The lines in the order they are traced; the game draws each one from its word.
    private const LINES = ['long-horizontal', 'short-horizontal', 'long-vertical', 'short-vertical', 'long-horizontal-low'];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Writing Q1 Week 2 Wednesday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'writing', 'lessons.quarter_number' => 1, 'lessons.week_number' => 2, 'lessons.day_of_week' => 3, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Writing lesson for Quarter 1, Week 2, Wednesday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Long and Short Lines',
                'description' => 'Trace each long or short straight line from its starting point.',
                'instructions' => 'Use your finger to trace each long or short line from the green dot to the orange star, staying on the line.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tracing',
                'configuration' => '{}',
                'max_score' => count(self::LINES),
            ]);

            // A round scores when the trace stays on the line: the game sends "clean", or "off-path" when part of it strayed.
            foreach (self::LINES as $i => $line) {
                DB::table('activity_questions')->updateOrInsert(['activity_id' => $activityId, 'question_key' => $line], [
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['line' => $line]),
                    'correct_answer' => json_encode(['answer' => 'clean']),
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

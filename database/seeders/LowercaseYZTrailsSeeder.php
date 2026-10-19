<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Letter y and z Trails" digital activity for Writing, Q4 Week 7 Monday; run with: php artisan db:seed --class=LowercaseYZTrailsSeeder
class LowercaseYZTrailsSeeder extends Seeder
{
    private const CODE = 'WR-Q4W7-01';

    private const CHECKLIST_ITEM = 'Traces lowercase y and z following the modeled stroke order';

    // One y-word and one z-word picture per round, in play order; each round traces small y, then small z.
    private const ROUNDS = [
        ['y' => 'yoyo', 'z' => 'zebra'],
        ['y' => 'yarn', 'z' => 'zero'],
        ['y' => 'yawn', 'z' => 'zipper'],
        ['y' => 'yacht', 'z' => 'zucchini'],
        ['y' => 'yellow', 'z' => 'zigzag'],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Writing Q4 Week 7 Monday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'writing', 'lessons.quarter_number' => 4, 'lessons.week_number' => 7, 'lessons.day_of_week' => 1, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Writing lesson for Quarter 4, Week 7, Monday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Letter y and z Trails',
                'description' => 'Watch each animated letter guide, then trace y and z from the marked starting points.',
                'instructions' => 'Trace small y, then small z, from the green dot: y is a short slant and a long slant; z is across, slant down, across.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('practical_activity_details')->where('activity_id', $activityId)->delete();
            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tracing',
                'configuration' => '{}',
                'max_score' => count(self::ROUNDS),
            ]);

            // Cleared and re-added, since kept rows would clash with new question_order values; the game sends "correct" when both letters follow their strokes on the first try.
            DB::table('activity_questions')->where('activity_id', $activityId)->delete();
            foreach (self::ROUNDS as $i => $pair) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-'.($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode($pair),
                    'correct_answer' => json_encode(['answer' => 'correct']),
                ]);
            }

            // The skill this game practises, on the Developmental Checklist under Writing Readiness for Quarter 4.
            $domainId = DB::table('developmental_domains')->where('code', 'writing_readiness')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kindergarten', 'quarter_number' => 4, 'item' => self::CHECKLIST_ITEM];
            // Matched by activity first, so renaming the item updates its row instead of adding a second one.
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

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Color Hunt" game for MAPEH Arts and Crafts, Q1 Week 1 Tuesday; run with: php artisan db:seed --class=ColorHuntSeeder
class ColorHuntSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'MP-Q1W1-02';

    private const CHECKLIST_ITEM = 'Identifies and names basic colors';

    // One round per target color, in play order; the game draws the shapes and decoys itself.
    private const COLORS = ['red', 'blue', 'yellow', 'green', 'orange'];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing MAPEH Arts and Crafts lesson for Quarter 1, Week 1, Tuesday.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->where(['modules.code' => 'mapeh', 'developmental_domains.code' => 'arts_crafts', 'lessons.quarter_number' => 1, 'lessons.week_number' => 1, 'lessons.day_of_week' => 2, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the MAPEH Arts and Crafts lesson for Quarter 1, Week 1, Tuesday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Color Hunt',
                'description' => 'Find all the objects of the color called out.',
                'instructions' => 'Listen to the color, then tap the three objects that have that color.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(self::COLORS),
            ]);

            // Drops rounds no longer in the list, so a shortened game does not keep its old questions.
            DB::table('activity_questions')->where('activity_id', $activityId)->whereNotIn('question_key', self::COLORS)->delete();

            // A round scores when all three objects are found: the game sends "found", or "missed" when the tries ran out.
            foreach (self::COLORS as $i => $color) {
                DB::table('activity_questions')->updateOrInsert(['activity_id' => $activityId, 'question_key' => $color], [
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['target' => $color]),
                    'correct_answer' => json_encode(['answer' => 'found']),
                ]);
            }

            // The skill this game practises, on the Developmental Checklist under Arts and Crafts for Quarter 1.
            $domainId = DB::table('developmental_domains')->where('code', 'arts_crafts')->value('id');
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

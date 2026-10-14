<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Shape Hunt" digital activity for Math, Q2 Week 1 Tuesday; run with: php artisan db:seed --class=ShapeHuntSeeder
class ShapeHuntSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'MR-Q2W1-01';

    private const CHECKLIST_ITEM = 'Finds and names circles, squares, triangles, and rectangles in familiar objects';

    // [target shape, correct object, 4 decoy objects of other shapes] — one round per shape the curriculum names, plus a 5th that revisits circle with a different object.
    private const ROUNDS = [
        ['circle', 'clock', ['tile', 'flag', 'door', 'book']],
        ['square', 'window', ['plate', 'sandwich', 'ruler', 'cookie']],
        ['triangle', 'sandwich', ['box', 'clock', 'door', 'tile']],
        ['rectangle', 'book', ['cookie', 'window', 'flag', 'plate']],
        ['circle', 'cookie', ['ruler', 'slice', 'box', 'door']],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Math Q2 Week 1 Tuesday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 2, 'lessons.week_number' => 1, 'lessons.day_of_week' => 2, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 2, Week 1, Tuesday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Shape Hunt',
                'description' => 'Search a busy scene and tap the object shaped like the one named.',
                'instructions' => 'Look at all the scattered objects, then tap the one shaped like the named shape.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            // Switching from practical to digital — drop the now-unused practical row.
            DB::table('practical_activity_details')->where('activity_id', $activityId)->delete();

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(self::ROUNDS),
            ]);

            // Keyed by round number, not shape — circle now appears twice, and a shape-only key would collide.
            // (question_order is unique per activity, so old shape-named rows are cleared before the new ones go in.)
            DB::table('activity_questions')->where('activity_id', $activityId)->delete();
            foreach (self::ROUNDS as $i => [$shape, $correct, $decoys]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-' . ($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['shape' => $shape, 'objects' => [$correct, ...$decoys]]),
                    'correct_answer' => json_encode(['answer' => $correct]),
                ]);
            }

            // The skill this game practises, on the Developmental Checklist under Math Readiness for Quarter 2.
            $domainId = DB::table('developmental_domains')->where('code', 'math_readiness')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kindergarten', 'quarter_number' => 2, 'item' => self::CHECKLIST_ITEM];
            // Matched by activity first, so renaming the item updates its row instead of adding a second one.
            $existing = DB::table('developmental_checklist_items')->where('activity_id', $activityId)->value('id')
                ?? DB::table('developmental_checklist_items')->where($item)->value('id');
            if ($existing) {
                DB::table('developmental_checklist_items')->where('id', $existing)->update(['activity_id' => $activityId, 'item' => self::CHECKLIST_ITEM, 'updated_at' => now()]);
            } else {
                DB::table('developmental_checklist_items')->insert($item + [
                    'activity_id' => $activityId,
                    'display_order' => (DB::table('developmental_checklist_items')->where(['domain_id' => $domainId, 'quarter_number' => 2])->max('display_order') ?? 0) + 1,
                ]);
            }
        });
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Find the Different One" game for Language, Q1 Week 7 Monday; run with: php artisan db:seed --class=FindTheDifferentOneSeeder
class FindTheDifferentOneSeeder extends Seeder
{
    private const CODE = 'LG-Q1W7-01';

    private const CHECKLIST_ITEM = 'Identifies the picture with a different feature in a group';

    // Each shelf of four in play order: three look alike and one differs by color, size, direction, a missing part or length; the odd one is the answer.
    private const SHELVES = [
        ['balloon-red', 'balloon-red', 'balloon-blue', 'balloon-red'],
        ['teddy', 'teddy-small', 'teddy', 'teddy'],
        ['car', 'car', 'car', 'car-back'],
        ['flower-no-petal', 'flower', 'flower', 'flower'],
        ['pencil', 'pencil', 'pencil-short', 'pencil'],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Language Q1 Week 7 Monday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'language', 'lessons.quarter_number' => 1, 'lessons.week_number' => 7, 'lessons.day_of_week' => 1, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Language lesson for Quarter 1, Week 7, Monday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Find the Different One',
                'description' => 'Tap the picture that differs from the rest of the group.',
                'instructions' => 'Look at the toys on each shelf, then tap the one that is different.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(self::SHELVES),
            ]);

            // Cleared and re-added, since kept rows would clash with new question_order values.
            DB::table('activity_questions')->where('activity_id', $activityId)->delete();
            foreach (self::SHELVES as $i => $choices) {
                // The one picture that appears only once on the shelf.
                $odd = array_search(1, array_count_values($choices), true);
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'shelf-'.($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['choices' => $choices]),
                    'correct_answer' => json_encode(['answer' => $odd]),
                ]);
            }

            // The skill this game practises, on the Developmental Checklist under Reading and Language for Quarter 1.
            $domainId = DB::table('developmental_domains')->where('code', 'reading_language')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kindergarten', 'quarter_number' => 1, 'item' => self::CHECKLIST_ITEM];
            // Matched by activity first, so renaming the item updates its row instead of adding a second one.
            $existing = DB::table('developmental_checklist_items')->where('activity_id', $activityId)->value('id')
                ?? DB::table('developmental_checklist_items')->where($item)->value('id');
            if ($existing) {
                DB::table('developmental_checklist_items')->where('id', $existing)->update(['activity_id' => $activityId, 'item' => self::CHECKLIST_ITEM, 'updated_at' => now()]);
            } else {
                DB::table('developmental_checklist_items')->insert($item + [
                    'activity_id' => $activityId,
                    'display_order' => DB::table('developmental_checklist_items')->where(['domain_id' => $domainId, 'quarter_number' => 1])->max('display_order') + 1,
                ]);
            }
        });
    }
}

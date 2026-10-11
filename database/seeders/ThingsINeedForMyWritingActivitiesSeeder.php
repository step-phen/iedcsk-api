<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Things I Need for My Writing Activities" game for Writing, Q1 Week 1 Monday; run with: php artisan db:seed --class=ThingsINeedForMyWritingActivitiesSeeder
class ThingsINeedForMyWritingActivitiesSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'WR-Q1W1-01';

    private const CHECKLIST_ITEM = 'Names writing tools';

    // [item, pack or leave]; the game turns each word into its picture, label and spoken explanation.
    private const ITEMS = [
        ['pencil', 'pack'],
        ['eraser', 'pack'],
        ['notebook', 'pack'],
        ['crayons', 'pack'],
        ['sharpener', 'pack'],
        ['teddy-bear', 'leave'],
        ['ball', 'leave'],
        ['apple', 'leave'],
        ['toy-car', 'leave'],
        ['spoon', 'leave'],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Writing Q1 Week 1 Monday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'writing', 'lessons.quarter_number' => 1, 'lessons.week_number' => 1, 'lessons.day_of_week' => 1, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Writing lesson for Quarter 1, Week 1, Monday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Pack My Writing Bag',
                'description' => 'Choose writing tools and place them in the bag.',
                'instructions' => 'Drag or tap the things you need for writing into the school bag.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'drag_sort',
                'configuration' => '{}',
                'max_score' => count(array_filter(self::ITEMS, fn ($i) => $i[1] === 'pack')),
            ]);

            // Only the writing things are graded, so the score is out of 5; the others are decoys (gradable false) that only fill the tray.
            foreach (self::ITEMS as $i => [$item, $answer]) {
                DB::table('activity_questions')->updateOrInsert(['activity_id' => $activityId, 'question_key' => $item], [
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['item' => $item] + ($answer === 'leave' ? ['gradable' => false] : [])),
                    'correct_answer' => json_encode(['answer' => $answer]),
                ]);
            }

            // The skill this game practises, on the Developmental Checklist under Writing Readiness for Quarter 1.
            $domainId = DB::table('developmental_domains')->where('code', 'writing_readiness')->value('id');
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

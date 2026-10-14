<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Objects That Go Together" game for Language, Q1 Week 2 Monday; run with: php artisan db:seed --class=ObjectsThatGoTogetherSeeder
class ObjectsThatGoTogetherSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'LG-Q1W2-01';

    private const CHECKLIST_ITEM = 'Identifies objects that go together';

    // [object, the one it goes with, the three choices in the order shown]; pictures are in public/images/activities/objects-that-go-together/items.
    private const ROUNDS = [
        ['pencil', 'book', ['shoe', 'book', 'bowl']],
        ['sock', 'shoe', ['shoe', 'lock', 'toothpaste']],
        ['key', 'lock', ['book', 'bowl', 'lock']],
        ['spoon', 'bowl', ['toothpaste', 'bowl', 'shoe']],
        ['toothbrush', 'toothpaste', ['lock', 'book', 'toothpaste']],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Language Q1 Week 2 Monday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'language', 'lessons.quarter_number' => 1, 'lessons.week_number' => 2, 'lessons.day_of_week' => 1, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Language lesson for Quarter 1, Week 2, Monday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Objects That Go Together',
                'description' => 'Find the object that goes with each picture.',
                'instructions' => 'Look at the picture, then tap the one that goes with it.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(self::ROUNDS),
            ]);

            foreach (self::ROUNDS as $i => [$object, $partner, $choices]) {
                DB::table('activity_questions')->updateOrInsert(['activity_id' => $activityId, 'question_key' => $object], [
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['choices' => $choices]),
                    'correct_answer' => json_encode(['answer' => $partner]),
                ]);
            }

            // The skill this game practises, on the Developmental Checklist under Reading and Language for Quarter 1.
            $domainId = DB::table('developmental_domains')->where('code', 'reading_language')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kinder 1', 'quarter_number' => 1, 'item' => self::CHECKLIST_ITEM];
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

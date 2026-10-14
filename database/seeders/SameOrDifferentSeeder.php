<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Same or Different?" game for Reading, Q1 Week 1 Monday; run with: php artisan db:seed --class=SameOrDifferentSeeder
class SameOrDifferentSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'RD-Q1W1-01';

    private const CHECKLIST_ITEM = 'Tells same from different';

    // [question_key, picture A, picture B, answer]; the game turns each word into its image and label.
    private const ROUNDS = [
        ['apples', 'apple', 'apple', 'same'],
        ['sun-moon', 'sun', 'moon', 'different'],
        ['ducks', 'duck', 'duck', 'same'],
        ['butterfly-ladybug', 'butterfly', 'ladybug', 'different'],
        ['balloons', 'balloon', 'balloon', 'same'],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Reading Q1 Week 1 Monday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'reading', 'lessons.quarter_number' => 1, 'lessons.week_number' => 1, 'lessons.day_of_week' => 1, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Reading lesson for Quarter 1, Week 1, Monday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Same or Different?',
                'description' => 'Tap the pictures into the correct group: Same or Different.',
                'instructions' => 'Look at the two pictures. Tap SAME if they match, or DIFFERENT if they do not.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(self::ROUNDS),
            ]);

            foreach (self::ROUNDS as $i => [$key, $a, $b, $answer]) {
                DB::table('activity_questions')->updateOrInsert(['activity_id' => $activityId, 'question_key' => $key], [
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['a' => $a, 'b' => $b]),
                    'correct_answer' => json_encode(['answer' => $answer]),
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

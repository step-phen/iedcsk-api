<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Length Line-Up" digital activity for Math, Q3 Week 5 Thursday; run with: php artisan db:seed --class=LengthLineUpSeeder
class LengthLineUpSeeder extends Seeder
{
    private const CODE = 'MR-Q3W5-02';

    private const CHECKLIST_ITEM = 'Arranges three objects with aligned ends in order from shortest to longest';

    // [item, three lengths in blocks, shortest first] — the game shuffles them in the tray.
    private const ROUNDS = [
        ['train', [2, 4, 6]],
        ['pencil', [3, 5, 7]],
        ['ribbon', [2, 4, 7]],
        ['caterpillar', [3, 5, 7]],
        ['train', [2, 3, 5]],
    ];

    // The lane each object was dropped in, listed for the shortest, middle and longest object in that order.
    private const ANSWER = 'shortest,middle,longest';

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 3, 'lessons.week_number' => 5, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 3, Week 5, Thursday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Length Line-Up',
                'description' => 'Drag three objects into order from shortest to longest.',
                'instructions' => 'Drag each object into a lane: the shortest in lane 1, the longest in lane 3.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('practical_activity_details')->where('activity_id', $activityId)->delete();

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'drag_sort',
                'configuration' => '{}',
                'max_score' => count(self::ROUNDS),
            ]);

            DB::table('activity_questions')->where('activity_id', $activityId)->delete();
            foreach (self::ROUNDS as $i => [$item, $lengths]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-' . ($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['item' => $item, 'lengths' => $lengths]),
                    'correct_answer' => json_encode(['answer' => self::ANSWER]),
                ]);
            }

            $domainId = DB::table('developmental_domains')->where('code', 'math_readiness')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kindergarten', 'quarter_number' => 3, 'item' => self::CHECKLIST_ITEM];
            $existing = DB::table('developmental_checklist_items')->where('activity_id', $activityId)->value('id')
                ?? DB::table('developmental_checklist_items')->where($item)->value('id');
            if ($existing) {
                DB::table('developmental_checklist_items')->where('id', $existing)->update(['activity_id' => $activityId, 'item' => self::CHECKLIST_ITEM, 'updated_at' => now()]);
            } else {
                DB::table('developmental_checklist_items')->insert($item + [
                    'activity_id' => $activityId,
                    'display_order' => (DB::table('developmental_checklist_items')->where(['domain_id' => $domainId, 'quarter_number' => 3])->max('display_order') ?? 0) + 1,
                ]);
            }
        });
    }
}

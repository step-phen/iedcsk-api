<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Differences" game for Language, Q1 Week 1 Wednesday; run with: php artisan db:seed --class=DifferencesSeeder
class DifferencesSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'LG-Q1W1-02';

    private const CHECKLIST_ITEM = 'Finds the different picture';

    // [kind, base, diff, size]; three copies of base and one diff, and a size round draws diff smaller.
    private const ROUNDS = [
        ['shape', 'circle', 'triangle', null],
        ['fruit', 'apple', 'banana', null],
        ['color', 'ball-blue', 'ball-red', null],
        ['size', 'cup', 'cup', 'small'],
        ['picture', 'car', 'cup', null],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Language Q1 Week 1 Wednesday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'language', 'lessons.quarter_number' => 1, 'lessons.week_number' => 1, 'lessons.day_of_week' => 3, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Language lesson for Quarter 1, Week 1, Wednesday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Differences',
                'description' => 'Tap the picture that is different from the others.',
                'instructions' => 'Look at the four pictures, then tap the one that is different.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(self::ROUNDS),
            ]);

            // The answer is the different picture's spoken name, which is what the game sends for the tile tapped ("triangle", "red ball", "small cup").
            foreach (self::ROUNDS as $i => [$kind, $base, $diff, $size]) {
                [$noun, $color] = array_pad(explode('-', $diff), 2, null);
                DB::table('activity_questions')->updateOrInsert(['activity_id' => $activityId, 'question_key' => $kind], [
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['kind' => $kind, 'base' => $base, 'diff' => $diff] + ($size ? ['size' => $size] : [])),
                    'correct_answer' => json_encode(['answer' => implode(' ', array_filter([$size, $color, $noun]))]),
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

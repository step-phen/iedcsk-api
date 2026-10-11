<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;
use RuntimeException;

// "Opposite Directions" game for Language, Q1 Week 3 Wednesday; run with: php artisan db:seed --class=OppositeDirectionsSeeder
class OppositeDirectionsSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'LG-Q1W3-02';

    private const CHECKLIST_ITEM = 'Identifies opposite directions';

    // [direction shown, its opposite, the three directions in the order shown]; all arrows, since a lone top or bottom box among arrows would give itself away.
    private const ROUNDS = [
        ['up', 'down', ['left', 'down', 'right']],
        ['left', 'right', ['right', 'up', 'down']],
        ['down', 'up', ['left', 'right', 'up']],
        ['right', 'left', ['down', 'left', 'up']],
        ['up', 'down', ['right', 'left', 'down']],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Language Q1 Week 3 Wednesday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'language', 'lessons.quarter_number' => 1, 'lessons.week_number' => 3, 'lessons.day_of_week' => 3, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Language lesson for Quarter 1, Week 3, Wednesday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Opposite Directions',
                'description' => 'Find the direction that goes the other way.',
                'instructions' => 'Look at the arrow, then tap the one that goes the other way.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(self::ROUNDS),
            ]);

            // Keyed by round, so the same direction can be asked again later.
            foreach (self::ROUNDS as $i => [$prompt, $answer, $choices]) {
                DB::table('activity_questions')->updateOrInsert(['activity_id' => $activityId, 'question_key' => 'round-'.($i + 1)], [
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['prompt' => $prompt, 'choices' => $choices]),
                    'correct_answer' => json_encode(['answer' => $answer]),
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

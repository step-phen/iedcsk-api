<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Subtraction Game" digital activity for Math, Q4 Week 2 Thursday; run with: php artisan db:seed --class=SubtractionGameSeeder
class SubtractionGameSeeder extends Seeder
{
    private const CODE = 'MR-Q4W2-02';

    private const CHECKLIST_ITEM = 'Removes counters from a group and counts what remains, up to 10';

    // [counters on the board, counters to take away, numeral choices]; the choices must include what is left (0 allowed).
    private const ROUNDS = [
        [6, 2, [3, 4, 5]],
        [5, 5, [0, 1, 2]],
        [8, 3, [4, 5, 6]],
        [9, 6, [2, 3, 4]],
        [10, 3, [6, 7, 8]],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 4, 'lessons.week_number' => 2, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 4, Week 2, Thursday first (MathQuarterFourWeeksOneTwoThreeLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Subtraction Game',
                'description' => 'Remove the requested counters, then count and tap how many remain.',
                'instructions' => 'Tap the counters you are asked to take away to drop them in the cup, then tap the number that shows how many are left.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('practical_activity_details')->where('activity_id', $activityId)->delete();

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(self::ROUNDS),
            ]);

            DB::table('activity_questions')->where('activity_id', $activityId)->delete();
            foreach (self::ROUNDS as $i => [$start, $remove, $choices]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-' . ($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['start' => $start, 'remove' => $remove, 'choices' => $choices]),
                    'correct_answer' => json_encode(['answer' => (string) ($start - $remove)]),
                ]);
            }

            $domainId = DB::table('developmental_domains')->where('code', 'math_readiness')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kinder 1', 'quarter_number' => 4, 'item' => self::CHECKLIST_ITEM];
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

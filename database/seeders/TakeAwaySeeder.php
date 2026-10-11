<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Take Away" digital activity for Math, Q4 Week 2 Tuesday; run with: php artisan db:seed --class=TakeAwaySeeder
class TakeAwaySeeder extends Seeder
{
    private const CODE = 'MR-Q4W2-01';

    private const CHECKLIST_ITEM = 'Removes objects from a group of up to 10 and counts what remains, including zero';

    // [object, starting count, removed count, numeral choices]; the choices must include what is left (0 allowed).
    private const ROUNDS = [
        ['cookie', 5, 2, [2, 3, 4]],
        ['cupcake', 4, 4, [0, 1, 2]],
        ['donut', 6, 2, [3, 4, 5]],
        ['grape', 3, 1, [1, 2, 3]],
        ['strawberry', 7, 5, [1, 2, 3]],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 4, 'lessons.week_number' => 2, 'lessons.day_of_week' => 2, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 4, Week 2, Tuesday first (MathQuarterFourWeeksOneTwoThreeLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Take Away',
                'description' => 'Remove the requested objects, then choose the number left, including 0 when none remain.',
                'instructions' => 'Take away the requested treats, then tap the number that shows how many are left.',
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
            foreach (self::ROUNDS as $i => [$object, $start, $removed, $choices]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-' . ($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['object' => $object, 'start' => $start, 'removed' => $removed, 'choices' => $choices]),
                    'correct_answer' => json_encode(['answer' => (string) ($start - $removed)]),
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

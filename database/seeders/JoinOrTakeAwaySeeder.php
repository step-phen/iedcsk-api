<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Join or Take Away?" digital activity for Math, Q4 Week 6 Thursday; run with: php artisan db:seed --class=JoinOrTakeAwaySeeder
class JoinOrTakeAwaySeeder extends Seeder
{
    private const CODE = 'MR-Q4W6-02';

    private const CHECKLIST_ITEM = 'Identifies whether an object story needs joining or taking away and tells the result';

    // [story object, how many at first, how many change, 'join' or 'takeAway']; the child taps which one the story needs.
    private const ROUNDS = [
        ['duck', 4, 2, 'join'],
        ['bird', 5, 3, 'takeAway'],
        ['apple', 3, 3, 'join'],
        ['cookie', 6, 2, 'takeAway'],
        ['balloon', 4, 4, 'takeAway'],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 4, 'lessons.week_number' => 6, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 4, Week 6, Thursday first (MathQuarterFourWeeksSixSevenLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Join or Take Away?',
                'description' => 'Listen to the story, decide if it joins or takes away, then watch the counters show the result.',
                'instructions' => 'Listen to each story, then tap Join if more are added or Take Away if some are removed.',
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
            foreach (self::ROUNDS as $i => [$object, $start, $change, $action]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-' . ($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['object' => $object, 'start' => $start, 'change' => $change, 'action' => $action]),
                    'correct_answer' => json_encode(['answer' => $action]),
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

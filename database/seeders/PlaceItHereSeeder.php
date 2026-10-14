<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Place It Here" digital activity for Math, Q3 Week 6 Thursday; run with: php artisan db:seed --class=PlaceItHereSeeder
class PlaceItHereSeeder extends Seeder
{
    private const CODE = 'MR-Q3W6-02';

    private const CHECKLIST_ITEM = 'Places objects near, far, inside, or outside when given a spoken position instruction';

    // 'side' is where the pen or barn stands: inside/outside rounds use the pen, near/far rounds use the barn, and 'answer' is the spot the animal must be dropped on.
    private const ROUNDS = [
        ['animal' => 'hen', 'ask' => 'inside', 'side' => 'left', 'answer' => 'inside'],
        ['animal' => 'pig', 'ask' => 'near', 'side' => 'right', 'answer' => 'near'],
        ['animal' => 'sheep', 'ask' => 'outside', 'side' => 'right', 'answer' => 'outside'],
        ['animal' => 'cow', 'ask' => 'far', 'side' => 'left', 'answer' => 'far'],
        ['animal' => 'duck', 'ask' => 'inside', 'side' => 'right', 'answer' => 'inside'],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 3, 'lessons.week_number' => 6, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 3, Week 6, Thursday first (MathQuarterThreeWeeksSixSevenLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Place It Here',
                'description' => 'Follow spoken position instructions to place farm animals near, far, inside, or outside.',
                'instructions' => 'Listen to where the animal goes, then drag it to that spot.',
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
            foreach (self::ROUNDS as $i => $round) {
                $answer = $round['answer'];
                unset($round['answer']);
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-' . ($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode($round),
                    'correct_answer' => json_encode(['answer' => $answer]),
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

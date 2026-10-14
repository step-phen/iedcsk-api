<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Math Skills Review 3" digital activity for Math, Q3 Week 4 Thursday; run with: php artisan db:seed --class=MathSkillsReview3Seeder
class MathSkillsReview3Seeder extends Seeder
{
    private const CODE = 'MR-Q3W4-02';

    private const CHECKLIST_ITEM = 'Uses size, length, and position words to complete mixed picture tasks';

    // One picture card per skill taught so far; 'answer' is the choice id the child must tap.
    private const ROUNDS = [
        ['kind' => 'size', 'object' => 'cookie', 'ask' => 'small', 'answer' => 'small'],
        ['kind' => 'order', 'object' => 'ball', 'ask' => 'biggest', 'answer' => 'big'],
        ['kind' => 'length', 'item' => 'pencil', 'lengths' => [6, 3], 'ask' => 'short', 'answer' => 'short'],
        ['kind' => 'distance', 'landmark' => 'house', 'side' => 'right', 'object' => 'bike', 'ask' => 'near', 'answer' => 'near'],
        ['kind' => 'position', 'container' => 'basket', 'object' => 'apple', 'ask' => 'inside', 'answer' => 'inside'],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 3, 'lessons.week_number' => 4, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 3, Week 4, Thursday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Math Skills Review 3',
                'description' => 'Complete picture tasks using the comparisons and position words already taught.',
                'instructions' => 'Listen to each picture card, then tap the picture that answers it.',
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
            foreach (self::ROUNDS as $i => $round) {
                $answer = $round['answer'];
                unset($round['answer']);
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'card-'.($i + 1),
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

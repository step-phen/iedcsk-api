<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Shape and Pattern Challenge" digital activity for Math, Q2 Week 6 Thursday; run with: php artisan db:seed --class=ShapeAndPatternChallengeSeeder
class ShapeAndPatternChallengeSeeder extends Seeder
{
    private const CODE = 'MR-Q2W6-02';

    private const CHECKLIST_ITEM = 'Identifies a named shape by ear and extends AB patterns using familiar picture objects';

    // Mixes two skills: naming a shape by ear alone (no picture clue) and extending a pattern.
    private const ROUNDS = [
        ['kind' => 'namedShape', 'shape' => 'triangle', 'choices' => ['triangle', 'circle', 'square']],
        ['kind' => 'patternFill', 'pattern' => ['circle', 'square', 'circle', 'square'], 'choices' => ['circle', 'triangle', 'rectangle']],
        ['kind' => 'namedShape', 'shape' => 'rectangle', 'choices' => ['rectangle', 'triangle', 'circle']],
        ['kind' => 'patternFill', 'pattern' => ['triangle', 'rectangle', 'triangle', 'rectangle'], 'choices' => ['triangle', 'circle', 'square']],
        ['kind' => 'namedShape', 'shape' => 'square', 'choices' => ['square', 'rectangle', 'triangle']],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 2, 'lessons.week_number' => 6, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 2, Week 6, Thursday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Shape and Pattern Challenge',
                'description' => 'Choose the requested shape and complete taught AB patterns.',
                'instructions' => 'Listen carefully, then tap the shape asked for or the piece that completes the pattern.',
                'activity_format' => 'digital',
                'sequence_order' => 2,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(self::ROUNDS),
            ]);

            DB::table('activity_questions')->where('activity_id', $activityId)->delete();
            foreach (self::ROUNDS as $i => $round) {
                $answer = $round['kind'] === 'namedShape' ? $round['shape'] : $round['choices'][0];
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-' . ($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode($round),
                    'correct_answer' => json_encode(['answer' => $answer]),
                ]);
            }

            $domainId = DB::table('developmental_domains')->where('code', 'math_readiness')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kinder 1', 'quarter_number' => 2, 'item' => self::CHECKLIST_ITEM];
            $existing = DB::table('developmental_checklist_items')->where('activity_id', $activityId)->value('id')
                ?? DB::table('developmental_checklist_items')->where($item)->value('id');
            if ($existing) {
                DB::table('developmental_checklist_items')->where('id', $existing)->update(['activity_id' => $activityId, 'item' => self::CHECKLIST_ITEM, 'updated_at' => now()]);
            } else {
                DB::table('developmental_checklist_items')->insert($item + [
                    'activity_id' => $activityId,
                    'display_order' => (DB::table('developmental_checklist_items')->where(['domain_id' => $domainId, 'quarter_number' => 2])->max('display_order') ?? 0) + 1,
                ]);
            }
        });
    }
}

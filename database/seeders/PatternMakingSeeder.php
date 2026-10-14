<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Pattern Making" digital activity for Math, Q2 Week 3 Thursday; run with: php artisan db:seed --class=PatternMakingSeeder
class PatternMakingSeeder extends Seeder
{
    private const CODE = 'MR-Q2W3-02';

    private const CHECKLIST_ITEM = 'Copies and extends simple AB patterns using familiar objects';

    // One AB pattern: the reference shows A,B,A,B and the learner fills BLANKS more steps, each blank its own scored question.
    private const SHAPES = ['circle', 'triangle'];

    private const BLANKS = 5;

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 2, 'lessons.week_number' => 3, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 2, Week 3, Thursday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Pattern Making',
                'description' => 'Look at the pattern, then tap the shapes to build what comes next, one at a time.',
                'instructions' => 'Look at the pattern shown, then tap the shapes in order to build the next 5 steps yourself.',
                'activity_format' => 'digital',
                'sequence_order' => 2,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('practical_activity_details')->where('activity_id', $activityId)->delete();

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => self::BLANKS,
            ]);

            DB::table('activity_questions')->where('activity_id', $activityId)->delete();
            for ($i = 0; $i < self::BLANKS; $i++) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'blank-'.($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['shapes' => self::SHAPES]),
                    'correct_answer' => json_encode(['answer' => self::SHAPES[$i % 2]]),
                ]);
            }

            $domainId = DB::table('developmental_domains')->where('code', 'math_readiness')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kindergarten', 'quarter_number' => 2, 'item' => self::CHECKLIST_ITEM];
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

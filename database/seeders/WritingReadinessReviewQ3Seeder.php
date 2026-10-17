<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Writing Readiness Review" digital activity for Writing, Q3 Week 7 Wednesday; run with: php artisan db:seed --class=WritingReadinessReviewQ3Seeder
class WritingReadinessReviewQ3Seeder extends Seeder
{
    private const CODE = 'WR-Q3W7-02';

    private const CHECKLIST_ITEM = 'Reviews tracing of the capital and lowercase letters taught in Quarter 3';

    // One taught capital or small letter per round, picked from across the quarter; the game knows each letter's strokes.
    private const ROUNDS = ['O', 'P', 'G', 'J', 'S', 'd', 'h', 'i', 'f', 'n'];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Writing lesson for Quarter 3, Week 7, Wednesday.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'writing', 'lessons.quarter_number' => 3, 'lessons.week_number' => 7, 'lessons.day_of_week' => 3, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Writing lesson for Quarter 3, Week 7, Wednesday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Writing Readiness Review',
                'description' => 'Watch the demonstration, then trace selected taught capital and lowercase letters from the starting point following the modeled stroke order.',
                'instructions' => 'Watch the pencil, then trace O, P, G, J, S, d, h, i, f, and n from the green dot.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('practical_activity_details')->where('activity_id', $activityId)->delete();
            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tracing',
                'configuration' => '{}',
                'max_score' => count(self::ROUNDS),
            ]);

            // Cleared and re-added, since kept rows would clash with new question_order values; the game sends "correct" when the first try follows every stroke in order.
            DB::table('activity_questions')->where('activity_id', $activityId)->delete();
            foreach (self::ROUNDS as $i => $letter) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-'.($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['letter' => $letter]),
                    'correct_answer' => json_encode(['answer' => 'correct']),
                ]);
            }

            // The skill this activity practises, on the Developmental Checklist under Writing Readiness for Quarter 3.
            $domainId = DB::table('developmental_domains')->where('code', 'writing_readiness')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kindergarten', 'quarter_number' => 3, 'item' => self::CHECKLIST_ITEM];
            // Matched by activity first, so renaming the item updates its row instead of adding a second one.
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

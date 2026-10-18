<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Writing Readiness Final Assessment" digital activity for Writing, Q4 Week 7 Friday; run with: php artisan db:seed --class=WritingReadinessFinalAssessmentQ4Seeder
class WritingReadinessFinalAssessmentQ4Seeder extends Seeder
{
    private const CODE = 'WR-Q4W7-03';

    private const CHECKLIST_ITEM = 'Writes own name and forms taught capital and lowercase letters and numerals 1 to 10 independently';

    // The child's name first, then taught capitals, small letters and numerals, all without a model first; the game knows each one's strokes.
    private const ROUNDS = [
        ['kind' => 'name', 'mode' => 'copy'],
        ['kind' => 'letter', 'letter' => 'D'],
        ['kind' => 'letter', 'letter' => 'K'],
        ['kind' => 'letter', 'letter' => 'M'],
        ['kind' => 'letter', 'letter' => 'a'],
        ['kind' => 'letter', 'letter' => 'g'],
        ['kind' => 'letter', 'letter' => 'j'],
        ['kind' => 'letter', 'letter' => 'r'],
        ['kind' => 'letter', 'letter' => 'x'],
        ['kind' => 'letter', 'letter' => 'z'],
        ['kind' => 'number', 'number' => 2],
        ['kind' => 'number', 'number' => 5],
        ['kind' => 'number', 'number' => 6],
        ['kind' => 'number', 'number' => 8],
        ['kind' => 'number', 'number' => 10],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Writing lesson for Quarter 4, Week 7, Friday.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'writing', 'lessons.quarter_number' => 4, 'lessons.week_number' => 7, 'lessons.day_of_week' => 5, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Writing lesson for Quarter 4, Week 7, Friday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Writing Readiness Final Assessment',
                'description' => 'Write your name, then selected taught capital and small letters and numerals independently, following the correct stroke order.',
                'instructions' => 'Sit up tall, write your name from the diploma, then write each letter and number all by yourself from the green dot.',
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

            // Cleared and re-added, since kept rows would clash with new question_order values; the game sends "correct" when its first try, made without a model, follows every stroke in order (for a name, every letter).
            DB::table('activity_questions')->where('activity_id', $activityId)->delete();
            foreach (self::ROUNDS as $i => $round) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-'.($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode($round),
                    'correct_answer' => json_encode(['answer' => 'correct']),
                ]);
            }

            // The skill this activity practises, on the Developmental Checklist under Writing Readiness for Quarter 4.
            $domainId = DB::table('developmental_domains')->where('code', 'writing_readiness')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kindergarten', 'quarter_number' => 4, 'item' => self::CHECKLIST_ITEM];
            // Matched by activity first, so renaming the item updates its row instead of adding a second one.
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

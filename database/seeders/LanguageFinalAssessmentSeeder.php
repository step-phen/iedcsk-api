<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Language Final Assessment" game for Language, Q1 Week 7 Friday; run with: php artisan db:seed --class=LanguageFinalAssessmentSeeder
class LanguageFinalAssessmentSeeder extends Seeder
{
    private const CODE = 'LG-Q1W7-03';

    private const CHECKLIST_ITEM = 'Shows picture concepts, follows spoken directions, and names vowels Aa, Ee, Ii, Oo independently';

    // key => [task type, target (picture, object, direction or letter the task is about, or null), choices, answer], in play order.
    // "point" asks for the arrow pointing the way the child hears; missing-part choices are part names and arrow choices are directions.
    private const TASKS = [
        'same-balloon' => ['same', 'balloon', ['star', 'balloon', 'flower'], 'balloon'],
        'letter-a' => ['letter', 'a', ['a', 'o', 'i'], 'a'],
        'point-right' => ['point', 'right', ['right', 'left', 'up'], 'right'],
        'together-key' => ['together', 'key', ['book', 'lock', 'spoon'], 'lock'],
        'missing-car' => ['missing', 'car', ['wheel', 'cloud', 'petal'], 'wheel'],
        'letter-E' => ['letter', 'E', ['A', 'I', 'E'], 'E'],
        'opposite-up' => ['opposite', 'up', ['left', 'down', 'right'], 'down'],
        'different-cat' => ['different', null, ['dog', 'dog', 'cat'], 'cat'],
        'letter-i' => ['letter', 'i', ['e', 'i', 'a'], 'i'],
        'together-sock' => ['together', 'sock', ['shoe', 'bowl', 'book'], 'shoe'],
        'point-down' => ['point', 'down', ['up', 'right', 'down'], 'down'],
        'letter-O' => ['letter', 'O', ['O', 'E', 'A'], 'O'],
        'missing-cup' => ['missing', 'cup', ['leaf', 'cup-handle', 'star'], 'cup-handle'],
        'opposite-right' => ['opposite', 'right', ['left', 'up', 'down'], 'left'],
        'same-star' => ['same', 'star', ['balloon', 'fish', 'star'], 'star'],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Language Q1 Week 7 Friday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'language', 'lessons.quarter_number' => 1, 'lessons.week_number' => 7, 'lessons.day_of_week' => 5, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Language lesson for Quarter 1, Week 7, Friday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Language Final Assessment',
                'description' => 'Respond to picture tasks, follow spoken directions, and name Aa, Ee, Ii, and Oo independently.',
                'instructions' => 'Listen to each question, then tap the card with the right answer to uncover the prize puzzle.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(self::TASKS),
            ]);

            // Cleared and re-added, since kept rows would clash with new question_order values.
            DB::table('activity_questions')->where('activity_id', $activityId)->delete();
            $order = 0;
            foreach (self::TASKS as $key => [$type, $target, $choices, $answer]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => $key,
                    'question_order' => ++$order,
                    'question_data' => json_encode(['type' => $type, 'target' => $target, 'choices' => $choices]),
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

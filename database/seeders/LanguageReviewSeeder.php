<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Language Review" game for Language, Q1 Week 7 Wednesday; run with: php artisan db:seed --class=LanguageReviewSeeder
class LanguageReviewSeeder extends Seeder
{
    private const CODE = 'LG-Q1W7-02';

    private const CHECKLIST_ITEM = 'Reviews picture comparisons, related objects, missing parts, directions, and vowels Aa, Ee, Ii, Oo';

    // key => [task type, target (picture, object, direction or letter the task is about, or null), choices, answer], in play order.
    // "point" asks for the arrow pointing the way the child hears; missing-part choices are part names and arrow choices are directions.
    private const TASKS = [
        'same-dog' => ['same', 'dog', ['cat', 'fish', 'dog'], 'dog'],
        'letter-A' => ['letter', 'A', ['A', 'O', 'E'], 'A'],
        'together-spoon' => ['together', 'spoon', ['sock', 'bowl', 'key'], 'bowl'],
        'point-up' => ['point', 'up', ['left', 'up', 'down'], 'up'],
        'missing-umbrella' => ['missing', 'umbrella', ['tail', 'wheel', 'umbrella-handle'], 'umbrella-handle'],
        'letter-e' => ['letter', 'e', ['a', 'e', 'o'], 'e'],
        'opposite-left' => ['opposite', 'left', ['up', 'right', 'down'], 'right'],
        'different-fish' => ['different', null, ['star', 'fish', 'star'], 'fish'],
        'letter-I' => ['letter', 'I', ['E', 'O', 'I'], 'I'],
        'letter-o' => ['letter', 'o', ['o', 'i', 'e'], 'o'],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Language Q1 Week 7 Wednesday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'language', 'lessons.quarter_number' => 1, 'lessons.week_number' => 7, 'lessons.day_of_week' => 3, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Language lesson for Quarter 1, Week 7, Wednesday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Language Review',
                'description' => 'Identify and match pictures, follow directions, and name Aa, Ee, Ii, and Oo.',
                'instructions' => 'Listen to each question, then tap the button with the right answer to fuel the rocket.',
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

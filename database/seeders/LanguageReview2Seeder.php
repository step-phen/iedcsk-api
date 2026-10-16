<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Language Review" digital activity for Language, Q2 Week 7 Wednesday; run with: php artisan db:seed --class=LanguageReview2Seeder
class LanguageReview2Seeder extends Seeder
{
    private const CODE = 'LG-Q2W7-02';

    private const CHECKLIST_ITEM = 'Reviews second-quarter letter names, uppercase and lowercase partners, and first letters of pictures';

    // [task, target, choices, answer]: "listen" taps the heard letter, "match" taps the other form of the shown letter,
    // "picture" taps the picture that starts with the shown letter (answer is the picture key).
    private const ROUNDS = [
        ['listen', 'C', ['C', 'G', 'O'], 'C'],
        ['match', 'J', ['i', 'j', 'g'], 'j'],
        ['picture', 'B', ['ball', 'cat', 'fish'], 'ball'],
        ['listen', 'k', ['x', 'k', 'h'], 'k'],
        ['match', 'n', ['M', 'H', 'N'], 'N'],
        ['picture', 'G', ['tree', 'goat', 'duck'], 'goat'],
        ['listen', 'P', ['B', 'R', 'P'], 'P'],
        ['match', 'Y', ['v', 'y', 'x'], 'y'],
        ['picture', 'H', ['sun', 'lion', 'house'], 'house'],
        ['listen', 't', ['t', 'f', 'l'], 't'],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'language', 'lessons.quarter_number' => 2, 'lessons.week_number' => 7, 'lessons.day_of_week' => 3, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Language lesson for Quarter 2, Week 7, Wednesday first (LanguageQuarterTwoLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Language Review',
                'description' => 'Complete digital listening, matching, and picture-choice tasks.',
                'instructions' => 'Listen to each song card, then tap the drum with the right letter or picture.',
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
            foreach (self::ROUNDS as $i => [$task, $target, $choices, $answer]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-'.($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['task' => $task, 'target' => $target, 'choices' => $choices]),
                    'correct_answer' => json_encode(['answer' => $answer]),
                ]);
            }

            $domainId = DB::table('developmental_domains')->where('code', 'reading_language')->value('id');
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

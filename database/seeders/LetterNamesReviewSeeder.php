<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Language Review" digital activity for Language, Q2 Week 4 Friday; run with: php artisan db:seed --class=LetterNamesReviewSeeder
class LetterNamesReviewSeeder extends Seeder
{
    private const CODE = 'LG-Q2W4-03';

    private const CHECKLIST_ITEM = 'Identifies taught letter names and their uppercase and lowercase forms';

    // [task, target, choices, answer]: "listen" taps the heard letter, "match" taps the other form of the shown letter,
    // "picture" taps the picture that starts with the shown letter (answer is the picture key).
    private const ROUNDS = [
        ['listen', 'B', ['B', 'D', 'P'], 'B'],
        ['match', 'G', ['q', 'g', 'j'], 'g'],
        ['picture', 'D', ['fish', 'duck', 'cat'], 'duck'],
        ['listen', 'm', ['n', 'w', 'm'], 'm'],
        ['match', 'h', ['H', 'N', 'K'], 'H'],
        ['picture', 'S', ['ball', 'tree', 'sun'], 'sun'],
        ['listen', 'V', ['Y', 'V', 'W'], 'V'],
        ['match', 'R', ['r', 'n', 'p'], 'r'],
        ['picture', 'L', ['lion', 'goat', 'house'], 'lion'],
        ['listen', 'w', ['v', 'u', 'w'], 'w'],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'language', 'lessons.quarter_number' => 2, 'lessons.week_number' => 4, 'lessons.day_of_week' => 5, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Language lesson for Quarter 2, Week 4, Friday first (LanguageQuarterTwoLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Language Review',
                'description' => 'Complete digital listening, matching, and picture-choice tasks.',
                'instructions' => 'Listen to each order, then tap the cookie with the right letter or picture.',
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

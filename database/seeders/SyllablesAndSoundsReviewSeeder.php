<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Language Review" digital activity for Language, Q3 Week 4 Friday; run with: php artisan db:seed --class=SyllablesAndSoundsReviewSeeder
class SyllablesAndSoundsReviewSeeder extends Seeder
{
    private const CODE = 'LG-Q3W4-03';

    private const CHECKLIST_ITEM = 'Reviews taught syllables, first letters of pictures, and beginning sounds of words';

    // [task, target, choices, answer]: "syllable" taps the heard syllable, "first-letter" taps the letter the pictured word starts with,
    // "first-sound" taps the picture that starts with the same sound as the heard word (answer is the picture key).
    private const ROUNDS = [
        ['syllable', 'ma', ['ma', 'sa', 'la'], 'ma'],
        ['first-letter', 'ball', ['d', 'b', 'p'], 'b'],
        ['first-sound', 'sun', ['sock', 'tree', 'cat'], 'sock'],
        ['syllable', 'bo', ['to', 'bo', 'lo'], 'bo'],
        ['first-letter', 'lion', ['t', 'f', 'l'], 'l'],
        ['first-sound', 'moon', ['fish', 'mango', 'dog'], 'mango'],
        ['syllable', 'at', ['am', 'al', 'at'], 'at'],
        ['first-letter', 'tiger', ['t', 'f', 'l'], 't'],
        ['first-sound', 'ball', ['cat', 'sun', 'banana'], 'banana'],
        ['syllable', 'us', ['um', 'ub', 'us'], 'us'],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'language', 'lessons.quarter_number' => 3, 'lessons.week_number' => 4, 'lessons.day_of_week' => 5, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Language lesson for Quarter 3, Week 4, Friday first (LanguageQuarterThreeLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Syllables and Sounds Review',
                'description' => 'Complete digital listening, matching, and picture-choice tasks.',
                'instructions' => 'Play the board game: on each space, listen or look, then tap the right syllable, letter or picture.',
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

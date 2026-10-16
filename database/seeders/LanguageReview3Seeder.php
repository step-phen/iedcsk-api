<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Language Review" digital activity for Language, Q3 Week 7 Wednesday; run with: php artisan db:seed --class=LanguageReview3Seeder
class LanguageReview3Seeder extends Seeder
{
    private const CODE = 'LG-Q3W7-02';

    private const CHECKLIST_ITEM = 'Reviews third-quarter syllables, syllable order, and beginning and ending sounds';

    // [task, target, choices, answer]: "syllable" taps the heard syllable, "sequence" taps the heard two syllables in order,
    // "first-sound"/"last-sound" tap the picture that starts/ends like the heard word (answer is the picture key),
    // "first-letter"/"last-letter" tap the letter for the first/last sound of the shown word.
    private const ROUNDS = [
        ['syllable', 'ti', ['te', 'ti', 'to'], 'ti'],
        ['first-sound', 'moon', ['mango', 'dog', 'fish'], 'mango'],
        ['last-sound', 'cat', ['sun', 'hat', 'pig'], 'hat'],
        ['first-letter', 'dog', ['b', 'd', 'p'], 'd'],
        ['sequence', 'ma-lu', ['lu-ma', 'ma-lu', 'ma-la'], 'ma-lu'],
        ['syllable', 'ob', ['ob', 'ab', 'ub'], 'ob'],
        ['last-letter', 'sun', ['m', 's', 'n'], 'n'],
        ['first-sound', 'sock', ['tree', 'sun', 'cat'], 'sun'],
        ['last-sound', 'bus', ['house', 'bed', 'cup'], 'house'],
        ['sequence', 'et-is', ['is-et', 'et-is', 'et-as'], 'et-is'],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'language', 'lessons.quarter_number' => 3, 'lessons.week_number' => 7, 'lessons.day_of_week' => 3, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Language lesson for Quarter 3, Week 7, Wednesday first (LanguageQuarterThreeLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Language Review',
                'description' => 'Complete digital listening, matching, and picture-choice tasks.',
                'instructions' => 'Listen to each question, then tap the balloon with the right syllable, picture, or letter.',
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

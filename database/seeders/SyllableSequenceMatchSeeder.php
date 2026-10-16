<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Syllable Sequence Match" digital activity for Language, Q3 Week 7 Monday; run with: php artisan db:seed --class=SyllableSequenceMatchSeeder
class SyllableSequenceMatchSeeder extends Seeder
{
    private const CODE = 'LG-Q3W7-01';

    private const CHECKLIST_ITEM = 'Identifies the order of two heard syllables';

    // [the two syllables heard in order, the three written sequences offered]; the answer is the sequence joined with a dash.
    private const ROUNDS = [
        [['ma', 'so'], ['so-ma', 'ma-so', 'ma-se']],
        [['be', 'lu'], ['be-lu', 'lu-be', 'be-la']],
        [['ti', 'mo'], ['to-mi', 'mo-ti', 'ti-mo']],
        [['at', 'em'], ['em-at', 'at-em', 'at-om']],
        [['us', 'ib'], ['ib-us', 'us-eb', 'us-ib']],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'language', 'lessons.quarter_number' => 3, 'lessons.week_number' => 7, 'lessons.day_of_week' => 1, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Language lesson for Quarter 3, Week 7, Monday first (LanguageQuarterThreeLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Syllable Sequence Match',
                'description' => 'Listen to the syllables, then choose the matching sequence.',
                'instructions' => 'Listen to the two syllables, then tap the domino that shows them in the same order.',
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
            foreach (self::ROUNDS as $i => [$sequence, $choices]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-'.($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['sequence' => $sequence, 'choices' => $choices]),
                    'correct_answer' => json_encode(['answer' => implode('-', $sequence)]),
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

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Story Steps B" digital activity for Language, Q4 Week 2 Wednesday; run with: php artisan db:seed --class=StoryStepsBSeeder
class StoryStepsBSeeder extends Seeder
{
    private const CODE = 'LG-Q4W2-02';

    private const CHECKLIST_ITEM = 'Identifies the main events in a familiar story';

    // [question data, answer]; ask is which part of the story the learner finds: first, next or last.
    private const ROUNDS = [
        [['story' => 'fishing', 'ask' => 'next', 'choices' => ['cook', 'cast', 'fish']], 'fish'],
        [['story' => 'sandcastle', 'ask' => 'last', 'choices' => ['wave', 'castle', 'pail']], 'wave'],
        [['story' => 'icecream', 'ask' => 'first', 'choices' => ['sun', 'melt', 'buy']], 'buy'],
        [['story' => 'puppy', 'ask' => 'next', 'choices' => ['nap', 'food', 'hungry']], 'food'],
        [['story' => 'kite', 'ask' => 'last', 'choices' => ['fly', 'make', 'wind']], 'fly'],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'language', 'lessons.quarter_number' => 4, 'lessons.week_number' => 2, 'lessons.day_of_week' => 3, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Language lesson for Quarter 4, Week 2, Wednesday first (LanguageQuarterFourLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Story Steps B',
                'description' => 'Tap the picture that shows what happened first, next or last.',
                'instructions' => 'Listen to the story, then tap the picture that answers the question.',
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
            foreach (self::ROUNDS as $i => [$data, $answer]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-'.($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode($data),
                    'correct_answer' => json_encode(['answer' => $answer]),
                ]);
            }

            $domainId = DB::table('developmental_domains')->where('code', 'reading_language')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kindergarten', 'quarter_number' => 4, 'item' => self::CHECKLIST_ITEM];
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

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Language Skills Review 4" digital activity for Language, Q4 Week 4 Friday; run with: php artisan db:seed --class=LanguageSkillsReview4Seeder
class LanguageSkillsReview4Seeder extends Seeder
{
    private const CODE = 'LG-Q4W4-03';

    private const CHECKLIST_ITEM = 'Reviews descriptions, stories, rhymes and directions heard';

    // [question data, answer]; story and direction answers are the pictures in tapping order joined by "-".
    private const ROUNDS = [
        [['kind' => 'describe', 'target' => 'rabbit', 'choices' => ['frog', 'rabbit', 'duck']], 'rabbit'],
        [['kind' => 'rhyme', 'word' => 'star', 'choices' => ['car', 'pig', 'egg']], 'car'],
        [['kind' => 'story', 'story' => 'egg', 'choices' => ['chick', 'egg', 'hatch']], 'egg-hatch-chick'],
        [['kind' => 'direction', 'steps' => ['ball'], 'choices' => ['book', 'ball', 'cup', 'apple']], 'ball'],
        [['kind' => 'describe', 'target' => 'umbrella', 'choices' => ['umbrella', 'hat', 'clock']], 'umbrella'],
        [['kind' => 'rhyme', 'word' => 'mouse', 'choices' => ['sun', 'house', 'bed']], 'house'],
        [['kind' => 'story', 'story' => 'kite', 'choices' => ['fly', 'make', 'wind']], 'make-wind-fly'],
        [['kind' => 'direction', 'steps' => ['apple', 'banana'], 'choices' => ['banana', 'cup', 'apple', 'book']], 'apple-banana'],
        [['kind' => 'describe', 'target' => 'grandma', 'choices' => ['baby', 'girl', 'grandma']], 'grandma'],
        [['kind' => 'direction', 'steps' => ['dog', 'cat'], 'choices' => ['cat', 'duck', 'dog', 'pig']], 'dog-cat'],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'language', 'lessons.quarter_number' => 4, 'lessons.week_number' => 4, 'lessons.day_of_week' => 5, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Language lesson for Quarter 4, Week 4, Friday first (LanguageQuarterFourLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Language Skills Review 4',
                'description' => 'Listen, then tap the train cars for each description, rhyme, story or direction.',
                'instructions' => 'Listen to each task, then tap the right train car, or tap the cars in order for stories and directions.',
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

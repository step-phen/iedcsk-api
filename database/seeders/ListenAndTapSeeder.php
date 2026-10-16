<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Listen and Tap" digital activity for Language, Q4 Week 3 Monday; run with: php artisan db:seed --class=ListenAndTapSeeder
class ListenAndTapSeeder extends Seeder
{
    private const CODE = 'LG-Q4W3-01';

    private const CHECKLIST_ITEM = 'Follows one- and two-step spoken directions';

    // [question data, answer]; tap rounds list the animals to tap in order, a move round names one animal and its place; the answer is the animals joined by "-".
    private const ROUNDS = [
        [['kind' => 'tap', 'steps' => ['cow'], 'animals' => ['pig', 'cow', 'duck', 'sheep']], 'cow'],
        [['kind' => 'move', 'steps' => ['duck'], 'place' => 'pond', 'animals' => ['hen', 'horse', 'duck', 'cow']], 'duck'],
        [['kind' => 'tap', 'steps' => ['hen', 'pig'], 'animals' => ['pig', 'sheep', 'hen', 'goat']], 'hen-pig'],
        [['kind' => 'move', 'steps' => ['sheep'], 'place' => 'barn', 'animals' => ['cow', 'sheep', 'pig', 'hen']], 'sheep'],
        [['kind' => 'tap', 'steps' => ['horse', 'duck'], 'animals' => ['duck', 'goat', 'cow', 'horse']], 'horse-duck'],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'language', 'lessons.quarter_number' => 4, 'lessons.week_number' => 3, 'lessons.day_of_week' => 1, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Language lesson for Quarter 4, Week 3, Monday first (LanguageQuarterFourLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Listen and Tap',
                'description' => 'Tap or move the farm animals the way you are told.',
                'instructions' => 'Listen to the direction, then tap the animals or drag one where it belongs.',
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

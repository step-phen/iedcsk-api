<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Needs and Feelings" digital activity for Language, Q4 Week 5 Friday; run with: php artisan db:seed --class=NeedsAndFeelingsSeeder
class NeedsAndFeelingsSeeder extends Seeder
{
    private const CODE = 'LG-Q4W5-03';

    private const CHECKLIST_ITEM = 'Chooses a suitable expression of a need or feeling for a familiar situation';

    // [question data, answer]; each scene id is also the need or feeling that fits it, and the game holds the scenes and sentences.
    private const ROUNDS = [
        [['scene' => 'hungry', 'choices' => ['hungry', 'sleepy', 'happy']], 'hungry'],
        [['scene' => 'hurt', 'choices' => ['scared', 'hurt', 'happy']], 'hurt'],
        [['scene' => 'happy', 'choices' => ['sad', 'cold', 'happy']], 'happy'],
        [['scene' => 'thirsty', 'choices' => ['thirsty', 'hurt', 'sleepy']], 'thirsty'],
        [['scene' => 'scared', 'choices' => ['happy', 'scared', 'hungry']], 'scared'],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'language', 'lessons.quarter_number' => 4, 'lessons.week_number' => 5, 'lessons.day_of_week' => 5, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Language lesson for Quarter 4, Week 5, Friday first (LanguageQuarterFourLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Needs and Feelings',
                'description' => 'Look at the scene, then choose what the child would say.',
                'instructions' => 'Look at the scene, listen to each sentence, then tap what the child needs or feels.',
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

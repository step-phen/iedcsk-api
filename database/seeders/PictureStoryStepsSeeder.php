<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Picture Story Steps" digital activity for Language, Q4 Week 5 Wednesday; run with: php artisan db:seed --class=PictureStoryStepsSeeder
class PictureStoryStepsSeeder extends Seeder
{
    private const CODE = 'LG-Q4W5-02';

    private const CHECKLIST_ITEM = 'Arranges pictures of a familiar event in first, next and last order';

    // [question data, answer]; the event id names its three pictures in order, the cards come shuffled and the answer is that order.
    private const ROUNDS = [
        [['event' => 'handwash', 'cards' => ['dry', 'soap', 'rinse']], 'soap-rinse-dry'],
        [['event' => 'meal', 'cards' => ['eat', 'wash', 'cook']], 'cook-eat-wash'],
        [['event' => 'bedtime', 'cards' => ['sleep', 'bath', 'read']], 'bath-read-sleep'],
        [['event' => 'birthday', 'cards' => ['blow', 'cake', 'sing']], 'sing-blow-cake'],
        [['event' => 'letter', 'cards' => ['mail', 'write', 'envelope']], 'write-envelope-mail'],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'language', 'lessons.quarter_number' => 4, 'lessons.week_number' => 5, 'lessons.day_of_week' => 3, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Language lesson for Quarter 4, Week 5, Wednesday first (LanguageQuarterFourLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Picture Story Steps',
                'description' => 'Drag three pictures into first, next and last order.',
                'instructions' => 'Listen to the event, then hang the three pictures on the clothesline in first, next and last order.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('practical_activity_details')->where('activity_id', $activityId)->delete();

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'drag_sort',
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

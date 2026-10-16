<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Letter Detective" digital activity for Language, Q2 Week 7 Monday; run with: php artisan db:seed --class=LetterDetectiveSeeder
class LetterDetectiveSeeder extends Seeder
{
    private const CODE = 'LG-Q2W7-01';

    private const CHECKLIST_ITEM = 'Finds a named uppercase or lowercase letter in a mixed group of letters';

    // [letter named in the clue, the eight sticky notes]; its other case and look-alikes are on the board too, and it appears only once.
    private const ROUNDS = [
        ['b', ['B', 'd', 'b', 'D', 'p', 'P', 'q', 'h']],
        ['M', ['n', 'M', 'W', 'm', 'N', 'w', 'H', 'V']],
        ['g', ['G', 'q', 'j', 'g', 'J', 'Q', 'y', 'p']],
        ['T', ['t', 'F', 'T', 'l', 'L', 'f', 'J', 'Y']],
        ['u', ['U', 'n', 'v', 'u', 'V', 'N', 'w', 'h']],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'language', 'lessons.quarter_number' => 2, 'lessons.week_number' => 7, 'lessons.day_of_week' => 1, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Language lesson for Quarter 2, Week 7, Monday first (LanguageQuarterTwoLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Letter Detective',
                'description' => 'Tap the uppercase or lowercase letter named in the instruction.',
                'instructions' => 'Read and hear the clue, then tap the sticky note with that big or small letter.',
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
            foreach (self::ROUNDS as $i => [$target, $choices]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-'.($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['target' => $target, 'choices' => $choices]),
                    'correct_answer' => json_encode(['answer' => $target]),
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

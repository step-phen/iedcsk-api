<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Reading Review" digital activity for Reading, Q2 Week 7 Wednesday; run with: php artisan db:seed --class=ReadingReview2Seeder
class ReadingReview2Seeder extends Seeder
{
    protected const CODE = 'RD-Q2W7-02';

    protected const NAME = 'Reading Review';

    protected const DAY = 3;

    protected const DESCRIPTION = 'Complete matching, listening, and picture-sorting tasks using the consonants taught from Bb to Pp.';

    protected const INSTRUCTIONS = 'Complete matching, listening, and picture-sorting tasks using taught consonants.';

    protected const CHECKLIST_ITEM = 'Reviews the letter forms and sounds of the consonants taught from Bb to Pp';

    // [task type, target, choices in the order shown, answer]; types: partner (big letter, small choices), sound (letter sound, letter choices), picture (letter, picture choices), sort (picture, letter choices), letter (letter name, letter choices).
    protected const TASKS = [
        ['partner', 'B', ['d', 'b', 'p'], 'b'],
        ['partner', 'M', ['n', 'm', 'h'], 'm'],
        ['sound', 'F', ['F', 'L', 'H'], 'F'],
        ['sound', 'N', ['M', 'J', 'N'], 'N'],
        ['picture', 'D', ['dog', 'fish', 'hat'], 'dog'],
        ['picture', 'P', ['bee', 'pig', 'moon'], 'pig'],
        ['picture', 'K', ['goat', 'lemon', 'kite'], 'kite'],
        ['sort', 'goat', ['J', 'G', 'H'], 'G'],
        ['sort', 'lemon', ['L', 'N', 'F'], 'L'],
        ['sort', 'cat', ['C', 'B', 'D'], 'C'],
    ];

    public function run(): void
    {
        $days = [1 => 'Monday', 3 => 'Wednesday', 5 => 'Friday'];
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'reading', 'lessons.quarter_number' => 2, 'lessons.week_number' => 7, 'lessons.day_of_week' => static::DAY, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Reading lesson for Quarter 2, Week 7, '.$days[static::DAY].' first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => static::CODE], [
                'lesson_id' => $lessonId,
                'name' => static::NAME,
                'description' => static::DESCRIPTION,
                'instructions' => static::INSTRUCTIONS,
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', static::CODE)->value('id');

            DB::table('practical_activity_details')->where('activity_id', $activityId)->delete();

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(static::TASKS),
            ]);

            DB::table('activity_questions')->where('activity_id', $activityId)->delete();
            foreach (static::TASKS as $i => [$type, $target, $choices, $answer]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-'.($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['type' => $type, 'target' => $target, 'choices' => $choices]),
                    'correct_answer' => json_encode(['answer' => $answer]),
                ]);
            }

            $domainId = DB::table('developmental_domains')->where('code', 'reading_language')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kindergarten', 'quarter_number' => 2, 'item' => static::CHECKLIST_ITEM];
            $existing = DB::table('developmental_checklist_items')->where('activity_id', $activityId)->value('id')
                ?? DB::table('developmental_checklist_items')->where($item)->value('id');
            if ($existing) {
                DB::table('developmental_checklist_items')->where('id', $existing)->update(['activity_id' => $activityId, 'item' => static::CHECKLIST_ITEM, 'updated_at' => now()]);
            } else {
                DB::table('developmental_checklist_items')->insert($item + [
                    'activity_id' => $activityId,
                    'display_order' => (DB::table('developmental_checklist_items')->where(['domain_id' => $domainId, 'quarter_number' => 2])->max('display_order') ?? 0) + 1,
                ]);
            }
        });
    }
}

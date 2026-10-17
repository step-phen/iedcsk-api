<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Shared by the Reading Q3/Q4 and Language Q4 games; each subclass names its activity, lesson day and rounds.
abstract class ReadingQuarterThreeGameSeeder extends Seeder
{
    protected const CODE = '';

    protected const NAME = '';

    protected const DESCRIPTION = '';

    protected const INSTRUCTIONS = '';

    protected const CHECKLIST_ITEM = '';

    protected const GAME_TYPE = 'tap_choice';

    protected const MODULE = 'reading';

    protected const QUARTER = 3;

    protected const WEEK = 0;

    // 1 = Monday ... 5 = Friday.
    protected const DAY = 0;

    // [question_data the game reads, the answer string it must submit].
    protected const ROUNDS = [];

    public function run(): void
    {
        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday'];
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => static::MODULE, 'lessons.quarter_number' => static::QUARTER, 'lessons.week_number' => static::WEEK, 'lessons.day_of_week' => static::DAY, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the '.ucfirst(static::MODULE).' lesson for Quarter '.static::QUARTER.', Week '.static::WEEK.', '.$days[static::DAY].' first.');
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
                'game_type' => static::GAME_TYPE,
                'configuration' => '{}',
                'max_score' => count(static::ROUNDS),
            ]);

            DB::table('activity_questions')->where('activity_id', $activityId)->delete();
            foreach (static::ROUNDS as $i => [$data, $answer]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-'.($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode($data),
                    'correct_answer' => json_encode(['answer' => $answer]),
                ]);
            }

            $domainId = DB::table('developmental_domains')->where('code', 'reading_language')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kindergarten', 'quarter_number' => static::QUARTER, 'item' => static::CHECKLIST_ITEM];
            $existing = DB::table('developmental_checklist_items')->where('activity_id', $activityId)->value('id')
                ?? DB::table('developmental_checklist_items')->where($item)->value('id');
            if ($existing) {
                DB::table('developmental_checklist_items')->where('id', $existing)->update(['activity_id' => $activityId, 'item' => static::CHECKLIST_ITEM, 'updated_at' => now()]);
            } else {
                DB::table('developmental_checklist_items')->insert($item + [
                    'activity_id' => $activityId,
                    'display_order' => (DB::table('developmental_checklist_items')->where(['domain_id' => $domainId, 'quarter_number' => static::QUARTER])->max('display_order') ?? 0) + 1,
                ]);
            }
        });
    }
}

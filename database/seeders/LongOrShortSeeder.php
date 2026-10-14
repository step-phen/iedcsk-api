<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Long or Short?" digital activity for Math, Q3 Week 2 Thursday; run with: php artisan db:seed --class=LongOrShortSeeder
class LongOrShortSeeder extends Seeder
{
    private const CODE = 'MR-Q3W2-02';

    private const CHECKLIST_ITEM = 'Compares objects with a reference and groups them as longer or shorter';

    // Length of the measuring stick every object is compared against, in blocks.
    private const REFERENCE = 4;

    // [item, length in blocks] — never equal to REFERENCE, so every object is clearly longer or shorter.
    private const ROUNDS = [
        ['pencil', 6],
        ['ribbon', 2],
        ['caterpillar', 6],
        ['train', 3],
        ['pencil', 2],
    ];

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'math', 'lessons.quarter_number' => 3, 'lessons.week_number' => 2, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Math lesson for Quarter 3, Week 2, Thursday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Long or Short?',
                'description' => 'Compare each object with the reference, then drag it into the Longer or Shorter group.',
                'instructions' => 'Compare the object with the measuring stick, then drag it into the Longer box or the Shorter box.',
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
            foreach (self::ROUNDS as $i => [$item, $length]) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-' . ($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['item' => $item, 'length' => $length, 'reference' => self::REFERENCE]),
                    'correct_answer' => json_encode(['answer' => $length > self::REFERENCE ? 'longer' : 'shorter']),
                ]);
            }

            $domainId = DB::table('developmental_domains')->where('code', 'math_readiness')->value('id');
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

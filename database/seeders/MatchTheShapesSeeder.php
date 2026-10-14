<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Match the Shapes" game for Reading, Q1 Week 1 Friday; run with: php artisan db:seed --class=MatchTheShapesSeeder
class MatchTheShapesSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'RD-Q1W1-03';

    private const CHECKLIST_ITEM = 'Matches basic shapes';

    // [shape, its slot in the outline row]; pieces keep this order in the tray, outlines are mixed up so the child has to match.
    private const PIECES = [
        ['circle', 2],
        ['square', 4],
        ['triangle', 0],
        ['rectangle', 3],
        ['star', 1],
    ];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Reading Q1 Week 1 Friday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'reading', 'lessons.quarter_number' => 1, 'lessons.week_number' => 1, 'lessons.day_of_week' => 5, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Reading lesson for Quarter 1, Week 1, Friday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Match the Shapes',
                'description' => 'Drag each colored shape up to the outline it fits.',
                'instructions' => 'Drag each colored shape up to the outline it fits.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'drag_match',
                'configuration' => '{}',
                'max_score' => count(self::PIECES),
            ]);

            // The answer is the piece's own shape: the game sends the outline each piece was first dropped on.
            foreach (self::PIECES as $i => [$shape, $outlineIndex]) {
                DB::table('activity_questions')->updateOrInsert(['activity_id' => $activityId, 'question_key' => $shape], [
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['shape' => $shape, 'outlineIndex' => $outlineIndex]),
                    'correct_answer' => json_encode(['answer' => $shape]),
                ]);
            }

            // The skill this game practises, on the Developmental Checklist under Reading and Language for Quarter 1.
            $domainId = DB::table('developmental_domains')->where('code', 'reading_language')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kindergarten', 'quarter_number' => 1, 'item' => self::CHECKLIST_ITEM];
            // Matched by activity first, so renaming the item updates its row instead of adding a second one.
            $existing = DB::table('developmental_checklist_items')->where('activity_id', $activityId)->value('id')
                ?? DB::table('developmental_checklist_items')->where($item)->value('id');
            if ($existing) {
                DB::table('developmental_checklist_items')->where('id', $existing)->update(['activity_id' => $activityId, 'item' => self::CHECKLIST_ITEM, 'updated_at' => now()]);
            } else {
                DB::table('developmental_checklist_items')->insert($item + [
                    'activity_id' => $activityId,
                    'display_order' => DB::table('developmental_checklist_items')->where(['domain_id' => $domainId, 'quarter_number' => 1])->max('display_order') + 1,
                ]);
            }
        });
    }
}

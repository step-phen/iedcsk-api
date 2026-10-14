<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Read My Word Cards" practical activity for Reading, Q3 Week 6 Friday; run with: php artisan db:seed --class=ReadMyWordCardsSeeder
class ReadMyWordCardsSeeder extends Seeder
{
    private const CODE = 'RD-Q3W6-03';

    private const CHECKLIST_ITEM = 'Blends letter sounds and reads familiar short-a, short-e and short-i CVC words aloud';

    public function run(): void
    {
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'reading', 'lessons.quarter_number' => 3, 'lessons.week_number' => 6, 'lessons.day_of_week' => 5, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Reading lesson for Quarter 3, Week 6, Friday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Read My Word Cards',
                'description' => 'Blend and read taught CVC word cards aloud.',
                'instructions' => 'Watch the video demonstration, then blend and read taught word cards. The teacher observes accuracy.',
                'activity_format' => 'practical',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->where('activity_id', $activityId)->delete();
            DB::table('activity_questions')->where('activity_id', $activityId)->delete();

            DB::table('practical_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'teacher_guidelines' => 'Show word cards such as cat, hat, bed, hen, pig and pin one at a time. Very Good: blends the sounds and reads every card correctly on their own. Good: reads most cards, or needs a reminder to blend one or two. Needs Help: says the sounds but cannot blend them into words, or needs the teacher to read the cards first.',
            ]);

            $domainId = DB::table('developmental_domains')->where('code', 'reading_language')->value('id');
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

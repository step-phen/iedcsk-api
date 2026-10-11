<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Pass and Move" practical activity for MAPEH Gross Motor, Q4 Week 6 Thursday; run with: php artisan db:seed --class=PassAndMoveSeeder
class PassAndMoveSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'MP-Q4W6-04';

    private const CHECKLIST_ITEM = 'Passes a soft ball to a partner and moves to another marker';

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing MAPEH Gross Motor lesson for Quarter 4, Week 6, Thursday.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->where(['modules.code' => 'mapeh', 'developmental_domains.code' => 'gross_motor', 'lessons.quarter_number' => 4, 'lessons.week_number' => 6, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the MAPEH Gross Motor lesson for Quarter 4, Week 6, Thursday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Pass and Move',
                'description' => 'Pass a soft ball to a partner, then move to another marker.',
                'instructions' => 'Pass the soft ball to your partner, then move to the next marker and wait for the next pass.',
                'activity_format' => 'practical',
                'sequence_order' => 2,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('practical_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'teacher_guidelines' => 'Very Good: passes to the partner and moves to the next marker without a reminder. Good: passes and moves but needs a reminder for one step. Needs Help: has trouble passing to a partner or needs help moving between markers.',
            ]);

            // The skill this activity practises, on the Developmental Checklist under Gross Motor Development for Quarter 4.
            $domainId = DB::table('developmental_domains')->where('code', 'gross_motor')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kinder 1', 'quarter_number' => 4, 'item' => self::CHECKLIST_ITEM];
            // Matched by activity first, so renaming the item updates its row instead of adding a second one.
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

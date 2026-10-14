<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Quarter 2 Performance Assessment" practical activity for MAPEH Gross Motor, Q2 Week 7 Thursday; run with: php artisan db:seed --class=QuarterTwoPerformanceAssessmentSeeder
class QuarterTwoPerformanceAssessmentSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'MP-Q2W7-04';

    private const CHECKLIST_ITEM = 'Demonstrates movement skills learned during Quarter 2';

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing MAPEH Gross Motor lesson for Quarter 2, Week 7, Thursday.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->where(['modules.code' => 'mapeh', 'developmental_domains.code' => 'gross_motor', 'lessons.quarter_number' => 2, 'lessons.week_number' => 7, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the MAPEH Gross Motor lesson for Quarter 2, Week 7, Thursday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Quarter 2 Performance Assessment',
                'description' => 'Demonstrate the movement skills learned while the teacher observes and rates each skill.',
                'instructions' => 'One at a time, show each movement the teacher calls out: hopping, skipping, crawling, low climbing, and changing directions.',
                'activity_format' => 'practical',
                'sequence_order' => 2,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('practical_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'teacher_guidelines' => 'Very Good: demonstrates every movement with control and confidence. Good: demonstrates most movements, or needs a prompt for one or two. Needs Help: struggles with most movements or needs help through the assessment.',
            ]);

            // The skill this activity practises, on the Developmental Checklist under Gross Motor Development for Quarter 2.
            $domainId = DB::table('developmental_domains')->where('code', 'gross_motor')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kindergarten', 'quarter_number' => 2, 'item' => self::CHECKLIST_ITEM];
            // Matched by activity first, so renaming the item updates its row instead of adding a second one.
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

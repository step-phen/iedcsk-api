<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Quarter 2 Art Performance Assessment" practical activity for MAPEH Arts and Crafts, Q2 Week 7 Thursday; run with: php artisan db:seed --class=QuarterTwoArtPerformanceAssessmentSeeder
class QuarterTwoArtPerformanceAssessmentSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'MP-Q2W7-05';

    private const CHECKLIST_ITEM = 'Creates a simple paper collage while the teacher observes tool use and process';

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing MAPEH Arts and Crafts lesson for Quarter 2, Week 7, Thursday.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->where(['modules.code' => 'mapeh', 'developmental_domains.code' => 'arts_crafts', 'lessons.quarter_number' => 2, 'lessons.week_number' => 7, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the MAPEH Arts and Crafts lesson for Quarter 2, Week 7, Thursday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Quarter 2 Art Performance Assessment',
                'description' => 'Create a simple paper collage while the teacher observes tool use and the making process.',
                'instructions' => 'Make a simple paper collage using cutting, tearing, arranging, and pasting, while the teacher watches.',
                'activity_format' => 'practical',
                'sequence_order' => 2,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('practical_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'teacher_guidelines' => 'Very Good: uses the tools safely and completes a clear collage with confidence. Good: completes most of the collage, or needs a prompt for one step. Needs Help: struggles with most of the steps or needs help through the collage.',
            ]);

            // The skill this activity practises, on the Developmental Checklist under Arts and Crafts for Quarter 2.
            $domainId = DB::table('developmental_domains')->where('code', 'arts_crafts')->value('id');
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

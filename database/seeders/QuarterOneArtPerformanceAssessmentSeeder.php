<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Quarter 1 Art Performance Assessment" practical activity for MAPEH Arts and Crafts, Q1 Week 7 Thursday; run with: php artisan db:seed --class=QuarterOneArtPerformanceAssessmentSeeder
class QuarterOneArtPerformanceAssessmentSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'MP-Q1W7-05';

    private const CHECKLIST_ITEM = 'Creates a simple drawing using taught lines, shapes, and colors';

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing MAPEH Arts and Crafts lesson for Quarter 1, Week 7, Thursday.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->where(['modules.code' => 'mapeh', 'developmental_domains.code' => 'arts_crafts', 'lessons.quarter_number' => 1, 'lessons.week_number' => 7, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the MAPEH Arts and Crafts lesson for Quarter 1, Week 7, Thursday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Quarter 1 Art Performance Assessment',
                'description' => 'Create a simple drawing using taught lines, shapes, and colors while the teacher observes the process.',
                'instructions' => 'Draw a simple picture using the lines, shapes, and colors you learned this quarter, while the teacher watches.',
                'activity_format' => 'practical',
                'sequence_order' => 2,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('practical_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'teacher_guidelines' => 'Very Good: uses lines, shapes, and colors confidently and completes the drawing. Good: uses most of the skills, or needs a prompt for one. Needs Help: struggles with most of the skills or needs help through the drawing.',
            ]);

            // The skill this activity practises, on the Developmental Checklist under Arts and Crafts for Quarter 1.
            $domainId = DB::table('developmental_domains')->where('code', 'arts_crafts')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kindergarten', 'quarter_number' => 1, 'item' => self::CHECKLIST_ITEM];
            // Matched by activity first, so renaming the item updates its row instead of adding a second one.
            $existing = DB::table('developmental_checklist_items')->where('activity_id', $activityId)->value('id')
                ?? DB::table('developmental_checklist_items')->where($item)->value('id');
            if ($existing) {
                DB::table('developmental_checklist_items')->where('id', $existing)->update(['activity_id' => $activityId, 'item' => self::CHECKLIST_ITEM, 'updated_at' => now()]);
            } else {
                DB::table('developmental_checklist_items')->insert($item + [
                    'activity_id' => $activityId,
                    'display_order' => (DB::table('developmental_checklist_items')->where(['domain_id' => $domainId, 'quarter_number' => 1])->max('display_order') ?? 0) + 1,
                ]);
            }
        });
    }
}

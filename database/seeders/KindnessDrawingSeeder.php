<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Kindness Drawing" practical activity for MAPEH Health Habits (Attitude), Q4 Week 1 Thursday; run with: php artisan db:seed --class=KindnessDrawingSeeder
class KindnessDrawingSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'MP-Q4W1-06';

    private const CHECKLIST_ITEM = 'Draws and tells about one kind action they can do';

    public function run(): void
    {
        // Attaches to the MAPEH Health Habits lesson for Quarter 4, Week 1, Thursday from HealthHabitsQuarterFourWeeksOneTwoLessonsSeeder.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->where(['modules.code' => 'mapeh', 'developmental_domains.code' => 'health_habits', 'lessons.quarter_number' => 4, 'lessons.week_number' => 1, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the MAPEH Health Habits lesson for Quarter 4, Week 1, Thursday first (run HealthHabitsQuarterFourWeeksOneTwoLessonsSeeder).');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Kindness Drawing',
                'description' => 'Draw one kind action you can do.',
                'instructions' => 'Think of one kind thing you can do for someone, then draw it and tell your teacher about it.',
                'activity_format' => 'practical',
                'sequence_order' => 2,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('practical_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'teacher_guidelines' => 'Very Good: draws a clear kind action and explains it on their own. Good: draws a kind action but needs a prompt to explain it, or needs an idea suggested. Needs Help: struggles to think of or draw a kind action even with help.',
            ]);

            // The skill this activity practises, on the Developmental Checklist under Health, Works, Habits and Attitude for Quarter 4.
            $domainId = DB::table('developmental_domains')->where('code', 'health_habits')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kindergarten', 'quarter_number' => 4, 'item' => self::CHECKLIST_ITEM];
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

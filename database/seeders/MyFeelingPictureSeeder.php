<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "My Feeling Picture" practical activity for MAPEH Arts and Crafts, Q4 Week 5 Tuesday; run with: php artisan db:seed --class=MyFeelingPictureSeeder
class MyFeelingPictureSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'MP-Q4W5-02';

    private const CHECKLIST_ITEM = 'Draws a picture that shows how one feels';

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing MAPEH Arts and Crafts lesson for Quarter 4, Week 5, Tuesday.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->where(['modules.code' => 'mapeh', 'developmental_domains.code' => 'arts_crafts', 'lessons.quarter_number' => 4, 'lessons.week_number' => 5, 'lessons.day_of_week' => 2, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the MAPEH Arts and Crafts lesson for Quarter 4, Week 5, Tuesday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'My Feeling Picture',
                'description' => 'Draw a picture that shows how you feel.',
                'instructions' => 'Think about how you feel today, then draw a picture that shows it.',
                'activity_format' => 'practical',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('practical_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'teacher_guidelines' => 'Very Good: draws freely and connects the drawing to a feeling when asked. Good: draws something, or needs a prompt to connect it to a feeling. Needs Help: struggles to start drawing or needs ideas suggested.',
            ]);

            // The skill this activity practises, on the Developmental Checklist under Arts and Crafts for Quarter 4.
            $domainId = DB::table('developmental_domains')->where('code', 'arts_crafts')->value('id');
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

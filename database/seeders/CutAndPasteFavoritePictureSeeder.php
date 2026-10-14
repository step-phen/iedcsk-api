<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Cut and Paste" practical activity for MAPEH Arts and Crafts, Q2 Week 4 Tuesday; run with: php artisan db:seed --class=CutAndPasteFavoritePictureSeeder
class CutAndPasteFavoritePictureSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'MP-Q2W4-02';

    private const CHECKLIST_ITEM = 'Uses cut paper pieces to make a favorite picture';

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing MAPEH Arts and Crafts lesson for Quarter 2, Week 4, Tuesday.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->where(['modules.code' => 'mapeh', 'developmental_domains.code' => 'arts_crafts', 'lessons.quarter_number' => 2, 'lessons.week_number' => 4, 'lessons.day_of_week' => 2, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the MAPEH Arts and Crafts lesson for Quarter 2, Week 4, Tuesday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Cut and Paste',
                'description' => 'Use cut paper pieces to make a favorite picture.',
                'instructions' => 'Cut out paper pieces, then paste them together to make a picture of something you like.',
                'activity_format' => 'practical',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('practical_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'teacher_guidelines' => 'Very Good: plans and pastes a clear favorite picture with control. Good: completes a picture, or pasting is uneven. Needs Help: struggles to plan the picture or needs help cutting and pasting.',
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

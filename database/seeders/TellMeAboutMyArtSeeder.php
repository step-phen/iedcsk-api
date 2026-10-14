<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Tell Me About My Art" practical activity for MAPEH Arts and Crafts, Q4 Week 5 Thursday; run with: php artisan db:seed --class=TellMeAboutMyArtSeeder
class TellMeAboutMyArtSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'MP-Q4W5-05';

    private const CHECKLIST_ITEM = 'Adds colors and explains a picture to the teacher';

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing MAPEH Arts and Crafts lesson for Quarter 4, Week 5, Thursday.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->where(['modules.code' => 'mapeh', 'developmental_domains.code' => 'arts_crafts', 'lessons.quarter_number' => 4, 'lessons.week_number' => 5, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the MAPEH Arts and Crafts lesson for Quarter 4, Week 5, Thursday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Tell Me About My Art',
                'description' => 'Add colors and explain your picture to the teacher.',
                'instructions' => 'Finish your feeling picture by adding colors, then explain it to the teacher.',
                'activity_format' => 'practical',
                'sequence_order' => 2,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('practical_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'teacher_guidelines' => 'Very Good: adds colors thoughtfully and explains the picture clearly. Good: adds colors but needs prompting to explain it. Needs Help: leaves the picture mostly uncolored or cannot describe it.',
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

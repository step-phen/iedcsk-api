<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Move Through the Stations" practical activity for MAPEH Gross Motor, Q3 Week 6 Thursday; run with: php artisan db:seed --class=MoveThroughTheStationsSeeder
class MoveThroughTheStationsSeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'MP-Q3W6-04';

    private const CHECKLIST_ITEM = 'Combines throwing, catching, and kicking at short stations';

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing MAPEH Gross Motor lesson for Quarter 3, Week 6, Thursday.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->where(['modules.code' => 'mapeh', 'developmental_domains.code' => 'gross_motor', 'lessons.quarter_number' => 3, 'lessons.week_number' => 6, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the MAPEH Gross Motor lesson for Quarter 3, Week 6, Thursday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Move Through the Stations',
                'description' => 'Complete short throwing, catching, and kicking stations.',
                'instructions' => 'Go through each station in order: throw at the first, catch at the second, and kick at the third.',
                'activity_format' => 'practical',
                'sequence_order' => 2,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('practical_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'teacher_guidelines' => 'Very Good: completes all three stations with the right skill and control. Good: completes most stations, or needs a reminder for one. Needs Help: skips stations or needs help through most of them.',
            ]);

            // The skill this activity practises, on the Developmental Checklist under Gross Motor Development for Quarter 3.
            $domainId = DB::table('developmental_domains')->where('code', 'gross_motor')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kindergarten', 'quarter_number' => 3, 'item' => self::CHECKLIST_ITEM];
            // Matched by activity first, so renaming the item updates its row instead of adding a second one.
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

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Fun Movement Day" practical activity for MAPEH Gross Motor, Q4 Week 4 Thursday; run with: php artisan db:seed --class=FunMovementDaySeeder
class FunMovementDaySeeder extends Seeder
{
    // Module, quarter, week, then the activity's number in that week.
    private const CODE = 'MP-Q4W4-04';

    private const CHECKLIST_ITEM = 'Joins favorite movement games and activities';

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing MAPEH Gross Motor lesson for Quarter 4, Week 4, Thursday.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->where(['modules.code' => 'mapeh', 'developmental_domains.code' => 'gross_motor', 'lessons.quarter_number' => 4, 'lessons.week_number' => 4, 'lessons.day_of_week' => 4, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the MAPEH Gross Motor lesson for Quarter 4, Week 4, Thursday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Fun Movement Day',
                'description' => 'Play favorite movement games and activities.',
                'instructions' => 'Join in and play the movement games the class has enjoyed most this year.',
                'activity_format' => 'practical',
                'sequence_order' => 2,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('practical_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'teacher_guidelines' => 'Very Good: joins in every game with energy and follows the rules. Good: joins in most games, or needs a reminder of the rules. Needs Help: hangs back from games or needs encouragement to join.',
            ]);

            // The skill this activity practises, on the Developmental Checklist under Gross Motor Development for Quarter 4.
            $domainId = DB::table('developmental_domains')->where('code', 'gross_motor')->value('id');
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

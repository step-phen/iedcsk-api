<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// MAPEH Health Habits (Attitude) lessons for Q4 Weeks 3, 4 and 5; run before their activity seeders with: php artisan db:seed --class=HealthHabitsQuarterFourWeeksThreeFourFiveLessonsSeeder
class HealthHabitsQuarterFourWeeksThreeFourFiveLessonsSeeder extends Seeder
{
    // [week, day of week (2 = Tuesday, 4 = Thursday), sequence order, title, description]
    private const LESSONS = [
        [3, 2, 1, 'Being Patient', 'Learners practice waiting and taking turns during familiar activities.'],
        [3, 4, 2, 'Being Patient', 'Learners practice waiting and taking turns during familiar activities.'],
        [4, 2, 1, 'Being Positive and Helpful', 'Learners recognize helpful actions and practice responding positively to simple tasks.'],
        [4, 4, 2, 'Being Positive and Helpful', 'Learners recognize helpful actions and practice responding positively to simple tasks.'],
        [5, 2, 1, 'Trying Again with Support', 'Learners practice asking for help and trying again when a familiar task is difficult.'],
        [5, 4, 2, 'Trying Again with Support', 'Learners practice asking for help and trying again when a familiar task is difficult.'],
    ];

    public function run(): void
    {
        $moduleDomainId = DB::table('module_domains')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->where(['modules.code' => 'mapeh', 'developmental_domains.code' => 'health_habits'])
            ->value('module_domains.id');
        if (! $moduleDomainId) {
            throw new RuntimeException('The MAPEH module is not linked to the health_habits domain.');
        }

        DB::transaction(function () use ($moduleDomainId) {
            foreach (self::LESSONS as [$week, $day, $order, $title, $description]) {
                DB::table('lessons')->updateOrInsert(
                    ['module_domain_id' => $moduleDomainId, 'quarter_number' => 4, 'week_number' => $week, 'day_of_week' => $day],
                    ['title' => $title, 'description' => $description, 'sequence_order' => $order, 'is_active' => true, 'updated_at' => now()],
                );
            }
        });
    }
}

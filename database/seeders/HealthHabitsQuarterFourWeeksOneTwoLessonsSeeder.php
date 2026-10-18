<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// MAPEH Health Habits (Attitude) lessons for Q4 Weeks 1 and 2; run before their activity seeders with: php artisan db:seed --class=HealthHabitsQuarterFourWeeksOneTwoLessonsSeeder
class HealthHabitsQuarterFourWeeksOneTwoLessonsSeeder extends Seeder
{
    // [week, day of week (2 = Tuesday, 4 = Thursday), sequence order, title, description]
    private const LESSONS = [
        [1, 2, 1, 'Being Kind', 'Learners recognize kind actions and practice showing kindness to classmates.'],
        [1, 4, 2, 'Being Kind', 'Learners recognize kind actions and practice showing kindness to classmates.'],
        [2, 2, 1, 'Being Respectful', 'Learners practice listening, sharing, and treating others respectfully.'],
        [2, 4, 2, 'Being Respectful', 'Learners practice listening, sharing, and treating others respectfully.'],
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

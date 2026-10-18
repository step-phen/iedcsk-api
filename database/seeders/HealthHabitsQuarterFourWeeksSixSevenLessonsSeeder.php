<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// MAPEH Health Habits (Attitude) lessons for Q4 Weeks 6 and 7; run before their activity seeders with: php artisan db:seed --class=HealthHabitsQuarterFourWeeksSixSevenLessonsSeeder
class HealthHabitsQuarterFourWeeksSixSevenLessonsSeeder extends Seeder
{
    // [week, day of week (2 = Tuesday, 4 = Thursday), sequence order, title, description]
    private const LESSONS = [
        [6, 2, 1, 'Practicing Kindness and Cooperation', 'Learners apply kindness, respect, patience, and helpfulness during partner activities.'],
        [6, 4, 2, 'Practicing Kindness and Cooperation', 'Learners apply kindness, respect, patience, and helpfulness during partner activities.'],
        [7, 2, 1, 'Reviewing and Assessing Positive Attitudes', 'Learners revisit and demonstrate the kind, respectful, patient, and helpful behaviors practiced during the quarter.'],
        [7, 4, 2, 'Reviewing and Assessing Positive Attitudes', 'Learners revisit and demonstrate the kind, respectful, patient, and helpful behaviors practiced during the quarter.'],
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

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Math lessons for Q4 Weeks 4 and 5; run before their activity seeders with: php artisan db:seed --class=MathQuarterFourWeeksFourFiveLessonsSeeder
class MathQuarterFourWeeksFourFiveLessonsSeeder extends Seeder
{
    // [week, day of week (2 = Tuesday, 4 = Thursday), sequence order, title, description]
    private const LESSONS = [
        [4, 2, 1, 'Applying Counting and Shape Recognition', 'Learners apply previously taught counting and shape skills in mixed picture tasks.'],
        [4, 4, 2, 'Reviewing Math Skills Through Stations', 'Learners review counting, addition, taking away, shapes, patterns, and comparisons using familiar materials.'],
        [5, 2, 1, 'Solving Simple Joining Stories', 'Learners connect a short joining story with combining pictured groups totaling up to 10.'],
        [5, 4, 2, 'Solving Simple Take-Away Stories', 'Learners connect a short take-away story with removing objects from a group of up to 10.'],
    ];

    public function run(): void
    {
        $moduleDomainId = DB::table('module_domains')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->where(['modules.code' => 'math', 'developmental_domains.code' => 'math_readiness'])
            ->value('module_domains.id');
        if (! $moduleDomainId) {
            throw new RuntimeException('The Math module is not linked to the math_readiness domain.');
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

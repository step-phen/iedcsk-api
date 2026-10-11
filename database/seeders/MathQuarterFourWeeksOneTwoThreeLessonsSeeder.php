<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Math lessons for Q4 Weeks 1, 2 and 3; run before their activity seeders with: php artisan db:seed --class=MathQuarterFourWeeksOneTwoThreeLessonsSeeder
class MathQuarterFourWeeksOneTwoThreeLessonsSeeder extends Seeder
{
    // [week, day of week (2 = Tuesday, 4 = Thursday), sequence order, title, description]
    private const LESSONS = [
        [1, 2, 1, 'Adding Two Groups of Objects', 'Learners combine two small groups and count the total, up to 10.'],
        [1, 4, 2, 'Modeling Addition with Objects', 'Learners use counters to represent joining two groups with a total no greater than 10.'],
        [2, 2, 1, 'Taking Away Objects', 'Learners remove objects from a group of up to 10 and count what remains, including none or zero.'],
        [2, 4, 2, 'Modeling Subtraction with Objects', 'Learners use counters to represent taking away from a group of up to 10.'],
        [3, 2, 1, 'Completing Number Sequences', 'Learners identify the next numeral in a forward sequence from 1 to 10.'],
        [3, 4, 2, 'Arranging Numbers in Order', 'Learners connect the spoken count with numerals ordered from 1 to 10.'],
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

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Math lessons for Q4 Weeks 6 and 7; run before their activity seeders with: php artisan db:seed --class=MathQuarterFourWeeksSixSevenLessonsSeeder
class MathQuarterFourWeeksSixSevenLessonsSeeder extends Seeder
{
    // [week, day of week (2 = Tuesday, 4 = Thursday), sequence order, title, description]
    private const LESSONS = [
        [6, 2, 1, 'Finding Missing Numbers in a Sequence', 'Learners identify a missing numeral within a forward counting sequence from 1 to 10.'],
        [6, 4, 2, 'Distinguishing Joining and Taking Away', 'Learners identify whether a familiar object story requires adding objects or removing them.'],
        [7, 2, 1, 'Reviewing Fourth-Quarter Math Skills', 'Learners review addition and subtraction within 10, number sequences, and familiar counting and shape skills.'],
        [7, 4, 2, 'Assessing Fourth-Quarter Math Skills', 'Learners demonstrate joining, taking away, number sequencing, and familiar counting skills.'],
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

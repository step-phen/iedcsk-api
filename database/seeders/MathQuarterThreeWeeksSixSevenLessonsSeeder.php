<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Math lessons for Q3 Weeks 6 and 7; run before their activity seeders with: php artisan db:seed --class=MathQuarterThreeWeeksSixSevenLessonsSeeder
class MathQuarterThreeWeeksSixSevenLessonsSeeder extends Seeder
{
    // [week, day of week (2 = Tuesday, 4 = Thursday), sequence order, title, description]
    private const LESSONS = [
        [6, 2, 1, 'Applying Size and Length Comparisons', 'Learners distinguish comparisons of overall size from comparisons of length.'],
        [6, 4, 2, 'Following Position Instructions', 'Learners use near, far, inside, and outside to position familiar objects.'],
        [7, 2, 1, 'Reviewing Third-Quarter Math Skills', 'Learners review size and length comparisons, ordering three objects, and taught position words.'],
        [7, 4, 2, 'Assessing Third-Quarter Math Skills', 'Learners demonstrate size and length comparison, ordering, and taught position concepts.'],
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
                    ['module_domain_id' => $moduleDomainId, 'quarter_number' => 3, 'week_number' => $week, 'day_of_week' => $day],
                    ['title' => $title, 'description' => $description, 'sequence_order' => $order, 'is_active' => true, 'updated_at' => now()],
                );
            }
        });
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Writing lessons for Q4; run before their activity seeders with: php artisan db:seed --class=WritingQuarterFourLessonsSeeder
class WritingQuarterFourLessonsSeeder extends Seeder
{
    // [week, day of week (1 = Monday, 3 = Wednesday, 5 = Friday), sequence order, title, description]
    private const LESSONS = [
        [1, 1, 1, 'Tracing Lowercase Letter g', 'Learners combine taught strokes to trace lowercase g with increasing control.'],
        [1, 3, 2, 'Tracing Lowercase Letter p', 'Learners combine taught strokes to trace lowercase p with increasing control.'],
        [1, 5, 3, 'Tracing Lowercase Letter j', 'Learners combine taught strokes to trace lowercase j with increasing control.'],
    ];

    public function run(): void
    {
        $moduleDomainId = DB::table('module_domains')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->where(['modules.code' => 'writing', 'developmental_domains.code' => 'writing_readiness'])
            ->value('module_domains.id');
        if (! $moduleDomainId) {
            throw new RuntimeException('The Writing module is not linked to the writing_readiness domain.');
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

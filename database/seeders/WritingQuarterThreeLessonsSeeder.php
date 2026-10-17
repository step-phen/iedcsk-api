<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Writing lessons for Q3; run before their activity seeders with: php artisan db:seed --class=WritingQuarterThreeLessonsSeeder
class WritingQuarterThreeLessonsSeeder extends Seeder
{
    // [week, day of week (1 = Monday, 3 = Wednesday, 5 = Friday), sequence order, title, description]
    private const LESSONS = [
        [1, 1, 1, 'Tracing Capital Letter D', 'Learners combine taught strokes to trace capital D with increasing control.'],
        [1, 3, 2, 'Tracing Capital Letter O', 'Learners combine taught strokes to trace capital O with increasing control.'],
        [1, 5, 3, 'Tracing Capital Letter P', 'Learners combine taught strokes to trace capital P with increasing control.'],
        [2, 1, 4, 'Tracing Capital Letter U', 'Learners combine taught strokes to trace capital U with increasing control.'],
        [2, 3, 5, 'Tracing Capital Letter G', 'Learners combine taught strokes to trace capital G with increasing control.'],
        [2, 5, 6, 'Tracing Capital Letter J', 'Learners combine taught strokes to trace capital J with increasing control.'],
        [3, 1, 7, 'Tracing Capital Letter R', 'Learners combine taught strokes to trace capital R with increasing control.'],
        [3, 3, 8, 'Tracing Capital Letter S', 'Learners combine taught strokes to trace capital S with increasing control.'],
        [3, 5, 9, 'Tracing Capital Letter Q', 'Learners combine taught strokes to trace capital Q with increasing control.'],
        [4, 1, 10, 'Tracing Lowercase Letter b', 'Learners combine taught strokes to trace lowercase b with increasing control.'],
        [4, 3, 11, 'Tracing Lowercase Letter d', 'Learners combine taught strokes to trace lowercase d with increasing control.'],
        [4, 5, 12, 'Reviewing New Capital and Lowercase Letters', 'Learners review D, O, P, U, G, J, R, S, Q, b, and d while maintaining comfortable writing posture.'],
        [5, 1, 13, 'Tracing Lowercase Letter h', 'Learners combine taught strokes to trace lowercase h with increasing control.'],
        [5, 3, 14, 'Tracing Lowercase Letter t', 'Learners combine taught strokes to trace lowercase t with increasing control.'],
        [5, 5, 15, 'Tracing Lowercase Letter l', 'Learners combine taught strokes to trace lowercase l with increasing control.'],
        [6, 1, 16, 'Tracing Lowercase Letter i', 'Learners combine taught strokes to trace lowercase i with increasing control.'],
        [6, 3, 17, 'Tracing Lowercase Letter f', 'Learners combine taught strokes to trace lowercase f with increasing control.'],
        [6, 5, 18, 'Tracing Lowercase Letter m', 'Learners combine taught strokes to trace lowercase m with increasing control.'],
        [7, 1, 19, 'Tracing Lowercase Letter n', 'Learners combine taught strokes to trace lowercase n with increasing control.'],
        [7, 3, 20, 'Reviewing Third-Quarter Letter Writing', 'Learners review all capital letters and the lowercase letters introduced this quarter.'],
        [7, 5, 21, 'Assessing Third-Quarter Letter Writing', 'Learners demonstrate the capital and lowercase letter-writing skills practiced this quarter.'],
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
                    ['module_domain_id' => $moduleDomainId, 'quarter_number' => 3, 'week_number' => $week, 'day_of_week' => $day],
                    ['title' => $title, 'description' => $description, 'sequence_order' => $order, 'is_active' => true, 'updated_at' => now()],
                );
            }
        });
    }
}

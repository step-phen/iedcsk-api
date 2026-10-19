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
        [2, 1, 4, 'Tracing Lowercase Letter e', 'Learners combine taught strokes to trace lowercase e with increasing control.'],
        [2, 3, 5, 'Tracing Lowercase Letter c', 'Learners combine taught strokes to trace lowercase c with increasing control.'],
        [2, 5, 6, 'Tracing Lowercase Letter a', 'Learners combine taught strokes to trace lowercase a with increasing control.'],
        [3, 1, 7, 'Tracing Lowercase Letter q', 'Learners combine taught strokes to trace lowercase q with increasing control.'],
        [3, 3, 8, 'Tracing Lowercase Letter o', 'Learners combine taught strokes to trace lowercase o with increasing control.'],
        [3, 5, 9, 'Tracing Lowercase Letter s', 'Learners combine taught strokes to trace lowercase s with increasing control.'],
        [4, 1, 10, 'Tracing Familiar Numerals', 'Learners use taught strokes to trace familiar numerals from 1 to 10.'],
        [4, 3, 11, 'Tracing and Copying My Name', 'Learners follow a personalized name model and begin copying their name with a capital first letter.'],
        [4, 5, 12, 'Reviewing Letters, Numerals, and Name Writing', 'Learners review taught lowercase letters, numerals 1-10, and tracing or copying their name.'],
        [5, 1, 13, 'Tracing Lowercase Letter r', 'Learners combine taught strokes to trace lowercase r with increasing control.'],
        [5, 3, 14, 'Tracing Lowercase Letter k', 'Learners combine taught strokes to trace lowercase k with increasing control.'],
        [5, 5, 15, 'Tracing Lowercase Letter u', 'Learners combine taught strokes to trace lowercase u with increasing control.'],
        [6, 1, 16, 'Tracing Lowercase Letter v', 'Learners combine taught strokes to trace lowercase v with increasing control.'],
        [6, 3, 17, 'Tracing Lowercase Letter w', 'Learners combine taught strokes to trace lowercase w with increasing control.'],
        [6, 5, 18, 'Tracing Lowercase Letter x', 'Learners combine taught strokes to trace lowercase x with increasing control.'],
        [7, 1, 19, 'Tracing Lowercase Letters y and z', 'Learners combine taught slants and straight strokes to trace lowercase y and z.'],
        [7, 3, 20, 'Reviewing Fourth-Quarter Writing Skills', 'Learners review capital and lowercase letters, familiar numerals, and name writing using taught models.'],
        [7, 5, 21, 'Assessing Fourth-Quarter Writing Skills', 'Learners demonstrate taught letter and numeral formation and increasing independence in writing their name.'],
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

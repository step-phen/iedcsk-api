<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Writing lessons for Q2; run before their activity seeders with: php artisan db:seed --class=WritingQuarterTwoLessonsSeeder
class WritingQuarterTwoLessonsSeeder extends Seeder
{
    // [week, day of week (1 = Monday, 3 = Wednesday, 5 = Friday), sequence order, title, description]
    private const LESSONS = [
        [1, 1, 1, 'Exploring Capital Letter Shapes', 'Learners notice how familiar strokes form capital letters and follow modeled letter movements.'],
        [1, 3, 2, 'Tracing Capital Letter T', 'Learners combine taught strokes to trace capital T with increasing control.'],
        [1, 5, 3, 'Tracing Capital Letter L', 'Learners combine taught strokes to trace capital L with increasing control.'],
        [2, 1, 4, 'Tracing Capital Letter I', 'Learners combine taught strokes to trace capital I with increasing control.'],
        [2, 3, 5, 'Tracing Capital Letter H', 'Learners combine taught strokes to trace capital H with increasing control.'],
        [2, 5, 6, 'Tracing Capital Letter F', 'Learners combine taught strokes to trace capital F with increasing control.'],
        [3, 1, 7, 'Tracing Capital Letter E', 'Learners combine taught strokes to trace capital E with increasing control.'],
        [3, 3, 8, 'Tracing Capital Letter V', 'Learners combine taught strokes to trace capital V with increasing control.'],
        [3, 5, 9, 'Tracing Capital Letter X', 'Learners combine taught strokes to trace capital X with increasing control.'],
        [4, 1, 10, 'Tracing Capital Letter W', 'Learners combine taught strokes to trace capital W with increasing control.'],
        [4, 3, 11, 'Tracing Capital Letter B', 'Learners combine taught strokes to trace capital B with increasing control.'],
        [4, 5, 12, 'Reviewing Taught Capital Letters', 'Learners review the capital letters T, L, I, H, F, E, V, X, W, and B and practice writing posture.'],
        [5, 1, 13, 'Tracing Capital Letter C', 'Learners combine taught strokes to trace capital C with increasing control.'],
        [5, 3, 14, 'Tracing Capital Letter A', 'Learners combine taught strokes to trace capital A with increasing control.'],
        [5, 5, 15, 'Tracing Capital Letter M', 'Learners combine taught strokes to trace capital M with increasing control.'],
        [6, 1, 16, 'Tracing Capital Letter N', 'Learners combine taught strokes to trace capital N with increasing control.'],
        [6, 3, 17, 'Tracing Capital Letter K', 'Learners combine taught strokes to trace capital K with increasing control.'],
        [6, 5, 18, 'Tracing Capital Letter Y', 'Learners combine taught strokes to trace capital Y with increasing control.'],
        [7, 1, 19, 'Tracing Capital Letter Z', 'Learners combine taught strokes to trace capital Z with increasing control.'],
        [7, 3, 20, 'Reviewing Second-Quarter Letter Writing', 'Learners review tracing and copying the capital letters taught this quarter.'],
        [7, 5, 21, 'Assessing Second-Quarter Letter Writing', 'Learners demonstrate posture and formation of capital letters taught this quarter.'],
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
                    ['module_domain_id' => $moduleDomainId, 'quarter_number' => 2, 'week_number' => $week, 'day_of_week' => $day],
                    ['title' => $title, 'description' => $description, 'sequence_order' => $order, 'is_active' => true, 'updated_at' => now()],
                );
            }
        });
    }
}

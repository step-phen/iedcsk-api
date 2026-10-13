<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Language lessons for Q2; run before their activity seeders with: php artisan db:seed --class=LanguageQuarterTwoLessonsSeeder
class LanguageQuarterTwoLessonsSeeder extends Seeder
{
    // [week, day of week (1 = Monday, 3 = Wednesday, 5 = Friday), sequence order, title, description]
    private const LESSONS = [
        [1, 1, 1, 'Recognizing Vowel Uu', 'Learners recognize Uu after hearing letter names.'],
        [1, 3, 2, 'Recognizing Consonants Bb and Cc', 'Learners recognize Bb, Cc after hearing letter names.'],
        [1, 5, 3, 'Recognizing Consonants Dd and Ff', 'Learners recognize Dd, Ff after hearing letter names.'],
    ];

    public function run(): void
    {
        $moduleDomainId = DB::table('module_domains')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->where(['modules.code' => 'language', 'developmental_domains.code' => 'reading_language'])
            ->value('module_domains.id');
        if (! $moduleDomainId) {
            throw new RuntimeException('The Language module is not linked to the reading_language domain.');
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

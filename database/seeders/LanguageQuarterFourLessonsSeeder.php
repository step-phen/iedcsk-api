<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Language lessons for Q4; run before their activity seeders with: php artisan db:seed --class=LanguageQuarterFourLessonsSeeder
class LanguageQuarterFourLessonsSeeder extends Seeder
{
    // [week, day of week (1 = Monday, 3 = Wednesday, 5 = Friday), sequence order, title, description]
    private const LESSONS = [
        [1, 1, 1, 'Identifying Middle Sounds', 'Learners identify the middle vowel sound in familiar spoken three-sound words.'],
        [1, 3, 2, 'Describing a Familiar Object', 'Learners select words that describe familiar objects.'],
        [1, 5, 3, 'Recognizing Family and Pet Vocabulary', 'Learners connect spoken family and pet words with pictures.'],
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
                    ['module_domain_id' => $moduleDomainId, 'quarter_number' => 4, 'week_number' => $week, 'day_of_week' => $day],
                    ['title' => $title, 'description' => $description, 'sequence_order' => $order, 'is_active' => true, 'updated_at' => now()],
                );
            }
        });
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Reading lessons for Q4; run before their activity seeders with: php artisan db:seed --class=ReadingQuarterFourLessonsSeeder
class ReadingQuarterFourLessonsSeeder extends Seeder
{
    // [week, day of week (1 = Monday, 3 = Wednesday, 5 = Friday), sequence order, title, description]
    private const LESSONS = [
        [1, 1, 1, 'Blending CVC Words with Short o', 'Learners blend three taught letter sounds to read short-o consonant-vowel-consonant words such as dog and pot.'],
        [1, 3, 2, 'Blending CVC Words with Short u', 'Learners blend three taught letter sounds to read short-u consonant-vowel-consonant words such as sun and cup.'],
        [1, 5, 3, 'Reading Simple Phrases and Sentences', 'Learners read short phrases and sentences using taught CVC words and previously introduced common words.'],
        [2, 1, 1, 'Understanding a Short Decodable Story', 'Learners read a short story using taught words and identify its main events with picture support.'],
        [2, 3, 2, 'Recognizing Rhyming Words', 'Learners listen for matching ending sounds in familiar spoken words.'],
        [2, 5, 3, 'Identifying Parts of a Book', 'Learners identify the front cover, title, and pages of a familiar book.'],
    ];

    public function run(): void
    {
        $moduleDomainId = DB::table('module_domains')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->where(['modules.code' => 'reading', 'developmental_domains.code' => 'reading_language'])
            ->value('module_domains.id');
        if (! $moduleDomainId) {
            throw new RuntimeException('The Reading module is not linked to the reading_language domain.');
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

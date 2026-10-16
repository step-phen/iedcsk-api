<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Language lessons for Q3; run before their activity seeders with: php artisan db:seed --class=LanguageQuarterThreeLessonsSeeder
class LanguageQuarterThreeLessonsSeeder extends Seeder
{
    // [week, day of week (1 = Monday, 3 = Wednesday, 5 = Friday), sequence order, title, description]
    private const LESSONS = [
        [1, 1, 1, 'Recognizing Consonant-Vowel Syllables with a', 'Learners match modeled syllables to their written forms.'],
        [1, 3, 2, 'Recognizing Consonant-Vowel Syllables with o', 'Learners match modeled syllables to their written forms.'],
        [1, 5, 3, 'Recognizing Consonant-Vowel Syllables with e', 'Learners match modeled syllables to their written forms.'],
        [2, 1, 1, 'Recognizing Consonant-Vowel Syllables with i', 'Learners match modeled syllables to their written forms.'],
        [2, 3, 2, 'Recognizing Consonant-Vowel Syllables with u', 'Learners match modeled syllables to their written forms.'],
        [2, 5, 3, 'Recognizing Vowel-Consonant Syllables with a', 'Learners match modeled syllables to their written forms.'],
        [3, 1, 1, 'Recognizing Vowel-Consonant Syllables with o', 'Learners match modeled syllables to their written forms.'],
        [3, 3, 2, 'Recognizing Vowel-Consonant Syllables with e', 'Learners match modeled syllables to their written forms.'],
        [3, 5, 3, 'Recognizing Vowel-Consonant Syllables with i', 'Learners match modeled syllables to their written forms.'],
        [4, 1, 1, 'Recognizing Vowel-Consonant Syllables with u', 'Learners match modeled syllables to their written forms.'],
        [4, 3, 2, 'Identifying Beginning Sounds', 'Learners listen for the first sound in familiar spoken words.'],
        [4, 5, 3, 'Reviewing Syllables and Beginning Sounds', 'Learners identify syllables and word sounds.'],
        [5, 1, 1, 'Identifying Ending Sounds', 'Learners listen for the last sound in familiar spoken words.'],
        [5, 3, 2, 'Recognizing Mixed Consonant-Vowel Syllables', 'Learners match modeled syllables to their written forms.'],
        [5, 5, 3, 'Recognizing Mixed Vowel-Consonant Syllables', 'Learners match modeled syllables to their written forms.'],
        [6, 1, 1, 'Grouping Pictures by Beginning Sound', 'Learners group familiar pictures whose spoken names begin with the same sound.'],
        [6, 3, 2, 'Matching Words with the Same Ending Sound', 'Learners recognize familiar spoken words that share a final sound.'],
        [6, 5, 3, 'Identifying Beginning and Ending Sounds', 'Learners recognize first and last sounds in spoken words.'],
        [7, 1, 1, 'Recognizing Syllable Sequences', 'Learners identify the order of heard syllables.'],
        [7, 3, 2, 'Reviewing Third-Quarter Language Skills', 'Learners identify syllables and word sounds.'],
        [7, 5, 3, 'Assessing Third-Quarter Language Skills', 'Learners identify syllables and word sounds.'],
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
                    ['module_domain_id' => $moduleDomainId, 'quarter_number' => 3, 'week_number' => $week, 'day_of_week' => $day],
                    ['title' => $title, 'description' => $description, 'sequence_order' => $order, 'is_active' => true, 'updated_at' => now()],
                );
            }
        });
    }
}

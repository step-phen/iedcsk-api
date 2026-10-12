<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Reading lessons for Q2; run before their activity seeders with: php artisan db:seed --class=ReadingQuarterTwoLessonsSeeder
class ReadingQuarterTwoLessonsSeeder extends Seeder
{
    // [week, day of week (1 = Monday, 3 = Wednesday, 5 = Friday), sequence order, title, description]
    private const LESSONS = [
        [1, 1, 1, 'Recognizing the Consonant Bb', 'Learners recognize uppercase and lowercase Bb and connect them with the /b/ sound in familiar words.'],
        [1, 3, 2, 'Recognizing the Consonant Cc', 'Learners recognize uppercase and lowercase Cc and connect them with the hard /k/ sound in familiar words.'],
        [1, 5, 3, 'Recognizing the Consonant Dd', 'Learners recognize uppercase and lowercase Dd and connect them with the /d/ sound in familiar words.'],
        [2, 1, 1, 'Recognizing the Consonant Ff', 'Learners recognize uppercase and lowercase Ff and connect them with the /f/ sound in familiar words.'],
        [2, 3, 2, 'Recognizing the Consonant Gg', 'Learners recognize uppercase and lowercase Gg and connect them with the hard /g/ sound in familiar words.'],
        [2, 5, 3, 'Recognizing the Consonant Hh', 'Learners recognize uppercase and lowercase Hh and connect them with the /h/ sound in familiar words.'],
        [3, 1, 1, 'Recognizing the Consonant Jj', 'Learners recognize uppercase and lowercase Jj and connect them with the /j/ sound in familiar words.'],
        [3, 3, 2, 'Recognizing the Consonant Kk', 'Learners recognize uppercase and lowercase Kk and connect them with the /k/ sound in familiar words.'],
        [3, 5, 3, 'Recognizing the Consonant Ll', 'Learners recognize uppercase and lowercase Ll and connect them with the /l/ sound in familiar words.'],
        [4, 1, 1, 'Recognizing the Consonant Mm', 'Learners recognize uppercase and lowercase Mm and connect them with the /m/ sound in familiar words.'],
        [4, 3, 2, 'Recognizing the Consonant Nn', 'Learners recognize uppercase and lowercase Nn and connect them with the /n/ sound in familiar words.'],
        [4, 5, 3, 'Reviewing Taught Consonant Sounds', 'Learners review the consonant sounds introduced from Bb to Nn.'],
        [5, 1, 1, 'Recognizing the Consonant Pp', 'Learners recognize uppercase and lowercase Pp and connect them with the /p/ sound in familiar words.'],
        [5, 3, 2, 'Matching Consonant Letter Forms', 'Learners recognize and match the uppercase and lowercase consonants taught this quarter.'],
        [5, 5, 3, 'Identifying Consonants by Sound', 'Learners connect the taught consonant sounds with their corresponding letters.'],
        [6, 1, 1, 'Grouping Pictures by Beginning Consonant', 'Learners group familiar pictures by the taught beginning consonant sounds.'],
        [6, 3, 2, 'Distinguishing Similar Consonant Sounds', 'Learners listen carefully to distinguish taught beginning sounds in familiar words.'],
        [6, 5, 3, 'Matching Pictures to Beginning Letters', 'Learners connect familiar picture names with their taught beginning consonants.'],
        [7, 1, 1, 'Applying Taught Consonant Sounds', 'Learners identify taught consonant sounds in a mixed set of familiar picture names.'],
        [7, 3, 2, 'Reviewing Second-Quarter Reading Skills', 'Learners review the letter forms and consonant sounds taught from Bb to Pp.'],
        [7, 5, 3, 'Assessing Second-Quarter Reading Skills', 'Learners demonstrate recognition of the letter forms and consonant sounds taught this quarter.'],
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
                    ['module_domain_id' => $moduleDomainId, 'quarter_number' => 2, 'week_number' => $week, 'day_of_week' => $day],
                    ['title' => $title, 'description' => $description, 'sequence_order' => $order, 'is_active' => true, 'updated_at' => now()],
                );
            }
        });
    }
}

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
        [2, 1, 1, 'Recognizing Consonants Gg and Hh', 'Learners recognize Gg, Hh after hearing letter names.'],
        [2, 3, 2, 'Recognizing Consonants Jj and Kk', 'Learners recognize Jj, Kk after hearing letter names.'],
        [2, 5, 3, 'Recognizing Consonants Ll and Mm', 'Learners recognize Ll, Mm after hearing letter names.'],
        [3, 1, 1, 'Recognizing Consonants Nn and Pp', 'Learners recognize Nn, Pp after hearing letter names.'],
        [3, 3, 2, 'Recognizing Consonants Qq and Rr', 'Learners recognize Qq, Rr after hearing letter names.'],
        [3, 5, 3, 'Recognizing Consonants Ss and Tt', 'Learners recognize Ss, Tt after hearing letter names.'],
        [4, 1, 1, 'Recognizing Consonants Vv and Ww', 'Learners recognize Vv, Ww after hearing letter names.'],
        [4, 3, 2, 'Recognizing Consonants Xx and Yy', 'Learners recognize Xx, Yy after hearing letter names.'],
        [4, 5, 3, 'Reviewing Taught Letter Names', 'Learners identify taught letter names and forms.'],
        [5, 1, 1, 'Recognizing Consonants Zz', 'Learners recognize Zz after hearing letter names.'],
        [5, 3, 2, 'Matching Uppercase and Lowercase Consonants', 'Learners connect taught uppercase consonants with their lowercase forms.'],
        [5, 5, 3, 'Recognizing Consonants in Mixed Order', 'Learners recognize taught letters after hearing letter names.'],
        [6, 1, 1, 'Recognizing All Five Vowels', 'Learners recognize Aa, Ee, Ii, Oo, Uu after hearing letter names.'],
        [6, 3, 2, 'Finding a Named Consonant', 'Learners identify a taught consonant after hearing its letter name.'],
        [6, 5, 3, 'Recognizing Letters in Familiar Labels', 'Learners find taught letters in pictured labels.'],
        [7, 1, 1, 'Identifying Mixed Uppercase and Lowercase Letters', 'Learners recognize taught letter forms in mixed groups.'],
        [7, 3, 2, 'Reviewing Second-Quarter Language Skills', 'Learners identify taught letter names and forms.'],
        [7, 5, 3, 'Assessing Second-Quarter Language Skills', 'Learners identify taught letter names and forms.'],
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

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Reading lessons for Q3; run before their activity seeders with: php artisan db:seed --class=ReadingQuarterThreeLessonsSeeder
class ReadingQuarterThreeLessonsSeeder extends Seeder
{
    // [week, day of week (1 = Monday, 3 = Wednesday, 5 = Friday), sequence order, title, description]
    private const LESSONS = [
        [1, 1, 1, 'Recognizing Qq and the qu Sound', 'Learners recognize Qq and notice that qu represents /kw/ in familiar words such as queen.'],
        [1, 3, 2, 'Recognizing the Consonant Rr', 'Learners recognize uppercase and lowercase Rr and connect them with the /r/ sound in familiar words.'],
        [1, 5, 3, 'Recognizing the Consonant Ss', 'Learners recognize uppercase and lowercase Ss and connect them with the /s/ sound in familiar words.'],
        [2, 1, 1, 'Recognizing the Consonant Tt', 'Learners recognize uppercase and lowercase Tt and connect them with the /t/ sound in familiar words.'],
        [2, 3, 2, 'Recognizing the Consonant Vv', 'Learners recognize uppercase and lowercase Vv and connect them with the /v/ sound in familiar words.'],
        [2, 5, 3, 'Recognizing the Consonant Ww', 'Learners recognize uppercase and lowercase Ww and connect them with the /w/ sound in familiar words.'],
        [3, 1, 1, 'Recognizing Xx and the Ending /ks/ Sound', 'Learners recognize Xx and hear its /ks/ sound at the end of familiar words such as box and fox.'],
        [3, 3, 2, 'Recognizing the Consonant Yy', 'Learners recognize uppercase and lowercase Yy and connect them with the /y/ sound in familiar words.'],
        [3, 5, 3, 'Recognizing the Consonant Zz', 'Learners recognize uppercase and lowercase Zz and connect them with the /z/ sound in familiar words.'],
        [4, 1, 1, 'Blending CVC Words with Short a', 'Learners blend three taught letter sounds to read short-a consonant-vowel-consonant words such as cat and map.'],
        [4, 3, 2, 'Blending CVC Words with Short e', 'Learners blend three taught letter sounds to read short-e consonant-vowel-consonant words such as hen and net.'],
        [4, 5, 3, 'Reviewing Consonants and CVC Blending', 'Learners review Qq-Zz, the ending Xx sound, and taught short-a and short-e CVC words.'],
        [5, 1, 1, 'Blending CVC Words with Short i', 'Learners blend three taught letter sounds to read short-i consonant-vowel-consonant words such as pin and sit.'],
        [5, 3, 2, 'Finding Middle Vowels in CVC Words', 'Learners identify the short-a, short-e, or short-i sound in taught CVC words.'],
        [5, 5, 3, 'Building Short-a, Short-e, and Short-i Words', 'Learners arrange taught letters to form familiar CVC words and connect them to pictures.'],
        [6, 1, 1, 'Reading Mixed Short-a, Short-e, and Short-i Words', 'Learners blend and distinguish familiar CVC words containing taught short vowels.'],
        [6, 3, 2, 'Matching Beginning and Ending Sounds', 'Learners recognize the first and last consonant sounds in taught CVC words.'],
        [6, 5, 3, 'Reading CVC Words Aloud', 'Learners blend taught letter sounds and say familiar short-a, short-e, and short-i CVC words.'],
        [7, 1, 1, 'Applying Consonant Sounds in Words', 'Learners connect taught consonants and short vowels with familiar CVC words.'],
        [7, 3, 2, 'Reviewing Third-Quarter Reading Skills', 'Learners review Qq-Zz, taught consonant sounds, and short-a, short-e, and short-i CVC blending.'],
        [7, 5, 3, 'Assessing Third-Quarter Reading Skills', 'Learners demonstrate taught consonant recognition and short-a, short-e, and short-i CVC blending.'],
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
                    ['module_domain_id' => $moduleDomainId, 'quarter_number' => 3, 'week_number' => $week, 'day_of_week' => $day],
                    ['title' => $title, 'description' => $description, 'sequence_order' => $order, 'is_active' => true, 'updated_at' => now()],
                );
            }
        });
    }
}

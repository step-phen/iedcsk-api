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
        [3, 1, 1, 'Applying Consonant Sounds', 'Learners use previously taught consonant sounds when recognizing familiar picture names and words.'],
        [3, 3, 2, 'Reading CVC Words with All Five Short Vowels', 'Learners blend familiar CVC words containing short a, e, i, o, and u.'],
        [3, 5, 3, 'Reading a Short Text Aloud', 'Learners apply taught word-reading skills to a brief decodable text.'],
        [4, 1, 1, 'Finding Familiar Words in a Text', 'Learners locate taught words in a short phrase, sentence, or passage.'],
        [4, 3, 2, 'Answering Questions About a Short Story', 'Learners identify who, what, and where details in a short story using picture choices.'],
        [4, 5, 3, 'Reviewing Reading and Comprehension Skills', 'Learners review taught CVC words, short phrases, rhymes, book parts, and story details.'],
        [5, 1, 1, 'Sequencing Story Events', 'Learners identify the beginning, middle, and end of a familiar short story.'],
        [5, 3, 2, 'Matching Sentences to Pictures', 'Learners connect short sentences containing taught words with their meaning.'],
        [5, 5, 3, 'Retelling a Familiar Read Story', 'Learners recall and share the main events of a short story using picture support.'],
        [6, 1, 1, 'Sorting Words by Short Vowel Sound', 'Learners distinguish the middle vowel sounds in familiar CVC words.'],
        [6, 3, 2, 'Reading and Responding to a Sentence', 'Learners read a simple sentence and respond to its meaning through a familiar action.'],
        [6, 5, 3, 'Identifying Story Characters and Setting', 'Learners identify who is in a short story and where the events happen.'],
        [7, 1, 1, 'Applying Reading Skills to a New Short Text', 'Learners use taught words and sounds to read an unfamiliar brief decodable text.'],
        [7, 3, 2, 'Reviewing Fourth-Quarter Reading Skills', 'Learners review taught short vowels, CVC words, sentences, rhymes, book parts, and story comprehension.'],
        [7, 5, 3, 'Assessing Fourth-Quarter Reading Skills', 'Learners demonstrate taught word-reading and comprehension skills using a brief decodable text.'],
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
                    ['title' => $title, 'description' => $description, 'sequence_order' => $order, 'updated_at' => now()],
                );
            }
        });
    }
}

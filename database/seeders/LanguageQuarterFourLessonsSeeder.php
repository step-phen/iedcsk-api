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
        [2, 1, 1, 'Sequencing a Short Story', 'Learners recall the order of events in a heard story.'],
        [2, 3, 2, 'Understanding Story Events', 'Learners identify main events in a familiar story.'],
        [2, 5, 3, 'Recognizing Rhyming Words', 'Learners identify rhyming words in a familiar rhyme.'],
        [3, 1, 1, 'Following Spoken Directions', 'Learners follow one- and two-step on-screen instructions.'],
        [3, 3, 2, 'Answering Picture Questions', 'Learners identify answers to who, what, and where questions.'],
        [3, 5, 3, 'Describing Objects', 'Learners identify objects from familiar descriptive words.'],
        [4, 1, 1, 'Describing People and Things', 'Learners match simple descriptions to pictures.'],
        [4, 3, 2, 'Identifying Sounds and Syllables', 'Learners recognize taught syllables and word sounds.'],
        [4, 5, 3, 'Reviewing Listening and Vocabulary', 'Learners review descriptions, stories, rhymes, and directions.'],
        [5, 1, 1, 'Recognizing Actions in Pictures', 'Learners match spoken action sentences to pictures.'],
        [5, 3, 2, 'Sequencing Familiar Events', 'Learners arrange familiar events in order.'],
        [5, 5, 3, 'Recognizing Needs and Feelings', 'Learners identify suitable expressions for familiar situations.'],
        [6, 1, 1, 'Choosing a Relevant Question', 'Learners recognize suitable who, what, or where questions.'],
        [6, 3, 2, 'Choosing a Conversation Response', 'Learners identify relevant replies in short conversations.'],
        [6, 5, 3, 'Matching Sentences to Story Events', 'Learners connect spoken sentences with story pictures.'],
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
                    ['title' => $title, 'description' => $description, 'sequence_order' => $order, 'updated_at' => now()],
                );
            }
        });
    }
}

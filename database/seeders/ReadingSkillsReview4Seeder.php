<?php

namespace Database\Seeders;

// "Reading Skills Review 4" digital activity for Reading, Q4 Week 4 Friday; run with: php artisan db:seed --class=ReadingSkillsReview4Seeder
class ReadingSkillsReview4Seeder extends ReadingQuarterFourGameSeeder
{
    protected const CODE = 'RD-Q4W4-03';

    protected const NAME = 'Reading Skills Review 4';

    protected const DESCRIPTION = 'Complete familiar word-reading and picture-based comprehension tasks.';

    protected const INSTRUCTIONS = 'Listen to each question, then tap the answer.';

    protected const CHECKLIST_ITEM = 'Reviews CVC words, phrases, rhymes, book parts, and story details';

    protected const WEEK = 4;

    protected const DAY = 5;

    // Task types take turns so each Q4 skill is checked twice.
    protected const ROUNDS = [
        [['type' => 'word', 'text' => 'cup', 'choices' => ['cap', 'cup', 'cat']], 'cup'],
        [['type' => 'phrase', 'text' => 'a dog in a box', 'choices' => ['cat-box', 'dog-bed', 'dog-box']], 'dog-box'],
        [['type' => 'rhyme', 'text' => 'cat', 'choices' => ['hat', 'dog', 'sun']], 'hat'],
        [['type' => 'book', 'text' => 'title', 'choices' => ['cover', 'pages', 'title']], 'title'],
        [['type' => 'story', 'text' => 'Ken has a hen. The hen is in a pen.', 'question' => 'Who has a hen?', 'choices' => ['pam', 'dad', 'ken']], 'ken'],
        [['type' => 'word', 'text' => 'jet', 'choices' => ['jet', 'net', 'web']], 'jet'],
        [['type' => 'phrase', 'text' => 'a bug on a log', 'choices' => ['bug-log', 'hen-log', 'bug-bed']], 'bug-log'],
        [['type' => 'rhyme', 'text' => 'sun', 'choices' => ['cup', 'pen', 'run']], 'run'],
        [['type' => 'book', 'text' => 'cover', 'choices' => ['title', 'cover', 'pages']], 'cover'],
        [['type' => 'story', 'text' => 'Sam got a big red bus. He sat on the bus.', 'question' => 'What did Sam get?', 'choices' => ['bus', 'van', 'cap']], 'bus'],
    ];
}

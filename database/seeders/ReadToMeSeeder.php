<?php

namespace Database\Seeders;

// "Read to Me" digital activity for Reading, Q4 Week 3 Friday; run with: php artisan db:seed --class=ReadToMeSeeder
class ReadToMeSeeder extends ReadingQuarterFourGameSeeder
{
    protected const CODE = 'RD-Q4W3-03';

    protected const NAME = 'Read to Me';

    protected const DESCRIPTION = 'Watch the demonstration, then read a short text using taught words.';

    protected const INSTRUCTIONS = 'Watch Owl read, then read each line of the story and tap the word that fills the blank.';

    protected const CHECKLIST_ITEM = 'Reads a short decodable text using taught words';

    protected const WEEK = 3;

    protected const DAY = 5;

    // The five lines make one story; "___" marks the blank and the choices differ by one sound so each must be read.
    protected const ROUNDS = [
        [['text' => 'Pam has a pet ___.', 'picture' => 'dog', 'choices' => ['dig', 'dog', 'dot']], 'dog'],
        [['text' => 'The dog can ___.', 'picture' => 'run', 'choices' => ['run', 'rug', 'rag']], 'run'],
        [['text' => 'The dog naps on the ___.', 'picture' => 'bed', 'choices' => ['bad', 'bud', 'bed']], 'bed'],
        [['text' => 'Pam has a red ___.', 'picture' => 'cap', 'choices' => ['cup', 'cap', 'cop']], 'cap'],
        [['text' => 'The sun is ___.', 'picture' => 'hot', 'choices' => ['hit', 'hut', 'hot']], 'hot'],
    ];
}

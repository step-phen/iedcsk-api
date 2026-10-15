<?php

namespace Database\Seeders;

// "CVC Picture Hunt" digital activity for Reading, Q3 Week 7 Monday; run with: php artisan db:seed --class=CvcPictureHuntSeeder
class CvcPictureHuntSeeder extends ReadingQuarterThreeGameSeeder
{
    protected const CODE = 'RD-Q3W7-01';

    protected const NAME = 'CVC Picture Hunt';

    protected const DESCRIPTION = 'Read each taught word, then select the picture that matches it.';

    protected const INSTRUCTIONS = 'Read the printed CVC word, then find and tap its picture among four.';

    protected const CHECKLIST_ITEM = 'Connects taught consonants and short vowels to read CVC words and match them to pictures';

    protected const WEEK = 7;

    protected const DAY = 1;

    // Most of the other pictures share a letter or the vowel with the word, so every sound has to be read.
    protected const ROUNDS = [
        [['word' => 'cap', 'choices' => ['cat', 'map', 'cap', 'can']], 'cap'],
        [['word' => 'hen', 'choices' => ['hen', 'pen', 'hat', 'ten']], 'hen'],
        [['word' => 'pig', 'choices' => ['pin', 'leg', 'van', 'pig']], 'pig'],
        [['word' => 'web', 'choices' => ['bed', 'web', 'net', 'van']], 'web'],
        [['word' => 'lip', 'choices' => ['leg', 'six', 'lip', 'map']], 'lip'],
    ];
}

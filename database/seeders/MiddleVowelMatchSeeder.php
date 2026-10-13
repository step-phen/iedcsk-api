<?php

namespace Database\Seeders;

// "Middle Vowel Match" digital activity for Reading, Q3 Week 5 Wednesday; run with: php artisan db:seed --class=MiddleVowelMatchSeeder
class MiddleVowelMatchSeeder extends ReadingQuarterThreeGameSeeder
{
    protected const CODE = 'RD-Q3W5-02';

    protected const NAME = 'Middle Vowel Match';

    protected const DESCRIPTION = 'Hear a CVC word, then tap its middle vowel: a, e or i.';

    protected const INSTRUCTIONS = 'Listen to a taught CVC word, then choose its middle vowel.';

    protected const CHECKLIST_ITEM = 'Identifies the middle short-a, short-e or short-i sound in CVC words';

    protected const WEEK = 5;

    protected const DAY = 3;

    // The vowel choices are always a, e and i, so each round only names its word.
    protected const ROUNDS = [
        [['word' => 'cat'], 'a'],
        [['word' => 'bed'], 'e'],
        [['word' => 'pig'], 'i'],
        [['word' => 'map'], 'a'],
        [['word' => 'hen'], 'e'],
    ];
}

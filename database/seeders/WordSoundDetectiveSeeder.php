<?php

namespace Database\Seeders;

// "Word Sound Detective" digital activity for Reading, Q3 Week 6 Wednesday; run with: php artisan db:seed --class=WordSoundDetectiveSeeder
class WordSoundDetectiveSeeder extends ReadingQuarterThreeGameSeeder
{
    protected const CODE = 'RD-Q3W6-02';

    protected const NAME = 'Word Sound Detective';

    protected const DESCRIPTION = 'Hear a CVC word, then tap its beginning or ending consonant.';

    protected const INSTRUCTIONS = 'Listen to the word, then choose its beginning or ending consonant as requested.';

    protected const CHECKLIST_ITEM = 'Identifies the beginning and ending consonant sounds in CVC words';

    protected const WEEK = 6;

    protected const DAY = 3;

    // Ending rounds offer the word's first letter as a choice, so the learner must listen for the last sound.
    protected const ROUNDS = [
        [['word' => 'cat', 'position' => 'beginning', 'choices' => ['m', 'c', 'd']], 'c'],
        [['word' => 'bed', 'position' => 'ending', 'choices' => ['d', 'n', 'g']], 'd'],
        [['word' => 'pig', 'position' => 'beginning', 'choices' => ['h', 'l', 'p']], 'p'],
        [['word' => 'hen', 'position' => 'ending', 'choices' => ['h', 'n', 't']], 'n'],
        [['word' => 'map', 'position' => 'ending', 'choices' => ['g', 'm', 'p']], 'p'],
    ];
}

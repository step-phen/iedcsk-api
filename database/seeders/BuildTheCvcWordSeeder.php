<?php

namespace Database\Seeders;

// "Build the CVC Word" digital activity for Reading, Q3 Week 5 Friday; run with: php artisan db:seed --class=BuildTheCvcWordSeeder
class BuildTheCvcWordSeeder extends ReadingQuarterThreeGameSeeder
{
    protected const CODE = 'RD-Q3W5-03';

    protected const NAME = 'Build the CVC Word';

    protected const DESCRIPTION = 'Tap the letter blocks in order to spell the pictured word.';

    protected const INSTRUCTIONS = 'Arrange the letters to spell the pictured taught CVC word.';

    protected const CHECKLIST_ITEM = 'Arranges letters to build familiar short-a, short-e and short-i CVC words';

    protected const GAME_TYPE = 'drag_sort';

    protected const WEEK = 5;

    protected const DAY = 5;

    // Letters are listed in the mixed-up order the blocks start in; the answer is the spelled word.
    protected const ROUNDS = [
        [['word' => 'hat', 'letters' => ['t', 'h', 'a']], 'hat'],
        [['word' => 'bed', 'letters' => ['e', 'd', 'b']], 'bed'],
        [['word' => 'pig', 'letters' => ['g', 'p', 'i']], 'pig'],
        [['word' => 'van', 'letters' => ['a', 'n', 'v']], 'van'],
        [['word' => 'hen', 'letters' => ['n', 'h', 'e']], 'hen'],
    ];
}

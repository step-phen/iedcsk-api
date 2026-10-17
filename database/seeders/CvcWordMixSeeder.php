<?php

namespace Database\Seeders;

// "CVC Word Mix" digital activity for Reading, Q4 Week 3 Wednesday; run with: php artisan db:seed --class=CvcWordMixSeeder
class CvcWordMixSeeder extends ReadingQuarterFourGameSeeder
{
    protected const CODE = 'RD-Q4W3-02';

    protected const NAME = 'CVC Word Mix';

    protected const DESCRIPTION = 'Read a taught CVC word, then select its matching picture.';

    protected const INSTRUCTIONS = 'Read the word on the robot, then tap the screen with its picture.';

    protected const CHECKLIST_ITEM = 'Reads CVC words with short a, e, i, o, and u and matches them to pictures';

    protected const WEEK = 3;

    protected const DAY = 3;

    // One round per short vowel; the other pictures share a letter with the word, so every sound must be read.
    protected const ROUNDS = [
        [['word' => 'cat', 'choices' => ['cap', 'cat', 'cup']], 'cat'],
        [['word' => 'bed', 'choices' => ['bed', 'bag', 'bus']], 'bed'],
        [['word' => 'pig', 'choices' => ['pan', 'pen', 'pig']], 'pig'],
        [['word' => 'log', 'choices' => ['leg', 'log', 'lip']], 'log'],
        [['word' => 'nut', 'choices' => ['nut', 'net', 'hut']], 'nut'],
    ];
}

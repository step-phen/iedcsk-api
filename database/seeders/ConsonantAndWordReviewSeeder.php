<?php

namespace Database\Seeders;

// "Consonant and Word Review" digital activity for Reading, Q3 Week 4 Friday; run with: php artisan db:seed --class=ConsonantAndWordReviewSeeder
class ConsonantAndWordReviewSeeder extends ReadingQuarterThreeGameSeeder
{
    protected const CODE = 'RD-Q3W4-03';

    protected const NAME = 'Consonant and Word Review';

    protected const DESCRIPTION = 'Tap the letter a picture begins or ends with, and match short-a and short-e words to pictures.';

    protected const INSTRUCTIONS = 'Identify taught sounds and match short-a and short-e words to pictures.';

    protected const CHECKLIST_ITEM = 'Identifies the sounds of Qq to Zz and reads short-a and short-e CVC words';

    protected const WEEK = 4;

    protected const DAY = 5;

    // Sound rounds and word rounds take turns; a sound round asks for the first letter, or the last one for the ending Xx sound.
    protected const ROUNDS = [
        [['kind' => 'sound', 'picture' => 'queen', 'position' => 'first', 'choices' => ['w', 'q', 'r']], 'q'],
        [['kind' => 'word', 'word' => 'cat', 'choices' => ['cat', 'can', 'bed']], 'cat'],
        [['kind' => 'sound', 'picture' => 'yoyo', 'position' => 'first', 'choices' => ['y', 'r', 'v']], 'y'],
        [['kind' => 'word', 'word' => 'hen', 'choices' => ['hat', 'hen', 'pen']], 'hen'],
        [['kind' => 'sound', 'picture' => 'box', 'position' => 'last', 'choices' => ['t', 's', 'x']], 'x'],
        [['kind' => 'word', 'word' => 'map', 'choices' => ['map', 'bag', 'net']], 'map'],
        [['kind' => 'sound', 'picture' => 'zebra', 'position' => 'first', 'choices' => ['s', 'z', 't']], 'z'],
        [['kind' => 'word', 'word' => 'net', 'choices' => ['jet', 'net', 'van']], 'net'],
        [['kind' => 'sound', 'picture' => 'volcano', 'position' => 'first', 'choices' => ['v', 'w', 'r']], 'v'],
        [['kind' => 'word', 'word' => 'bag', 'choices' => ['bed', 'van', 'bag']], 'bag'],
    ];
}

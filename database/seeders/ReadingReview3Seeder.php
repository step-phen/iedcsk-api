<?php

namespace Database\Seeders;

// "Reading Review" digital activity for Reading, Q3 Week 7 Wednesday; run with: php artisan db:seed --class=ReadingReview3Seeder
class ReadingReview3Seeder extends ReadingQuarterThreeGameSeeder
{
    protected const CODE = 'RD-Q3W7-02';

    protected const NAME = 'Reading Review';

    protected const DESCRIPTION = 'Complete sound-identification, word-building, and word-to-picture tasks using taught content.';

    protected const INSTRUCTIONS = 'Name the beginning or ending letter, fill in the missing letter, and match printed CVC words to pictures.';

    protected const CHECKLIST_ITEM = 'Reviews the sounds of Qq to Zz and builds and reads short-a, short-e and short-i CVC words';

    protected const WEEK = 7;

    protected const DAY = 3;

    // Task types take turns, and no round offers a letter that sounds like the answer (s/z, v/w, q/w, w/y).
    protected const ROUNDS = [
        [['type' => 'first', 'target' => 'queen', 'choices' => ['t', 'q', 'r']], 'q'],
        [['type' => 'missing', 'target' => 'cat', 'blank' => 1, 'choices' => ['a', 'e', 'i']], 'a'],
        [['type' => 'word', 'target' => 'bed', 'choices' => ['bag', 'bed', 'pen']], 'bed'],
        [['type' => 'last', 'target' => 'fox', 'choices' => ['t', 'g', 'x']], 'x'],
        [['type' => 'missing', 'target' => 'hen', 'blank' => 2, 'choices' => ['t', 'n', 'm']], 'n'],
        [['type' => 'word', 'target' => 'six', 'choices' => ['six', 'pin', 'bat']], 'six'],
        [['type' => 'first', 'target' => 'yarn', 'choices' => ['v', 't', 'y']], 'y'],
        [['type' => 'missing', 'target' => 'pig', 'blank' => 0, 'choices' => ['b', 'p', 'd']], 'p'],
        [['type' => 'word', 'target' => 'van', 'choices' => ['net', 'fan', 'van']], 'van'],
        [['type' => 'first', 'target' => 'zebra', 'choices' => ['z', 'v', 't']], 'z'],
    ];
}

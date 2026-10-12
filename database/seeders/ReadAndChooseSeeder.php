<?php

namespace Database\Seeders;

// "Read and Choose" digital activity for Reading, Q3 Week 6 Monday; run with: php artisan db:seed --class=ReadAndChooseSeeder
class ReadAndChooseSeeder extends ReadingQuarterThreeGameSeeder
{
    protected const CODE = 'RD-Q3W6-01';

    protected const NAME = 'Read and Choose';

    protected const DESCRIPTION = 'Read the printed CVC word, then tap its picture.';

    protected const INSTRUCTIONS = 'Blend the printed CVC word, then tap its matching picture.';

    protected const CHECKLIST_ITEM = 'Reads mixed short-a, short-e and short-i CVC words and matches them to pictures';

    protected const WEEK = 6;

    protected const DAY = 1;

    // Each set shares letters or a vowel with the word, so the learner has to read all three sounds.
    protected const ROUNDS = [
        [['word' => 'pig', 'choices' => ['pin', 'pig', 'pen']], 'pig'],
        [['word' => 'hat', 'choices' => ['hen', 'bat', 'hat']], 'hat'],
        [['word' => 'bed', 'choices' => ['bed', 'bag', 'bin']], 'bed'],
        [['word' => 'van', 'choices' => ['six', 'van', 'net']], 'van'],
        [['word' => 'net', 'choices' => ['pen', 'hen', 'net']], 'net'],
    ];
}

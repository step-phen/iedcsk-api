<?php

namespace Database\Seeders;

// "Read and Match" digital activity for Reading, Q4 Week 1 Friday; run with: php artisan db:seed --class=ReadAndMatchSeeder
class ReadAndMatchSeeder extends ReadingQuarterFourGameSeeder
{
    protected const CODE = 'RD-Q4W1-03';

    protected const NAME = 'Read and Match';

    protected const DESCRIPTION = 'Read a short phrase or sentence, then tap the picture that shows it.';

    protected const INSTRUCTIONS = 'Read a short phrase or sentence, then select the matching picture.';

    protected const CHECKLIST_ITEM = 'Reads simple phrases and sentences made of CVC and common words';

    protected const WEEK = 1;

    protected const DAY = 5;

    // A choice is "<who>-<where>"; each decoy changes only one word, so the whole phrase must be read.
    protected const ROUNDS = [
        [['text' => 'a cat in a box', 'choices' => ['cat-box', 'dog-box', 'cat-bed']], 'cat-box'],
        [['text' => 'a bug on a cup', 'choices' => ['bug-hat', 'bug-cup', 'fox-cup']], 'bug-cup'],
        [['text' => 'a hen on a log', 'choices' => ['pig-log', 'hen-bus', 'hen-log']], 'hen-log'],
        [['text' => 'The dog is in the tub.', 'choices' => ['dog-tub', 'cat-tub', 'dog-bed']], 'dog-tub'],
        [['text' => 'The pig is on the bed.', 'choices' => ['hen-bed', 'pig-log', 'pig-bed']], 'pig-bed'],
    ];
}

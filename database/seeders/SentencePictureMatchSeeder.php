<?php

namespace Database\Seeders;

// "Sentence Picture Match" digital activity for Reading, Q4 Week 5 Wednesday; run with: php artisan db:seed --class=SentencePictureMatchSeeder
class SentencePictureMatchSeeder extends ReadingQuarterFourGameSeeder
{
    protected const CODE = 'RD-Q4W5-02';

    protected const NAME = 'Sentence Picture Match';

    protected const DESCRIPTION = 'Read each short sentence, then tap the matching scene.';

    protected const INSTRUCTIONS = 'Read the sentence, then tap the snow globe with the matching scene.';

    protected const CHECKLIST_ITEM = 'Reads a short sentence with taught words and matches it to a picture';

    protected const WEEK = 5;

    protected const DAY = 3;

    // A scene is "<who>-<what>"; each wrong scene changes only one of the two, so the whole sentence must be read.
    protected const ROUNDS = [
        [['text' => 'The cat naps on the bed.', 'choices' => ['dog-bed', 'cat-bed', 'cat-box']], 'cat-bed'],
        [['text' => 'A fox sits on a log.', 'choices' => ['fox-log', 'pig-log', 'fox-tub']], 'fox-log'],
        [['text' => 'The hen sits on an egg.', 'choices' => ['cat-egg', 'hen-hat', 'hen-egg']], 'hen-egg'],
        [['text' => 'Ben runs with his dog.', 'choices' => ['run-cat', 'run-dog', 'nap-dog']], 'run-dog'],
        [['text' => 'The bug is on the cup.', 'choices' => ['bug-cup', 'bee-cup', 'bug-hat']], 'bug-cup'],
    ];
}

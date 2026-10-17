<?php

namespace Database\Seeders;

// "Word Hunt" digital activity for Reading, Q4 Week 4 Monday; run with: php artisan db:seed --class=WordHuntSeeder
class WordHuntSeeder extends ReadingQuarterFourGameSeeder
{
    protected const CODE = 'RD-Q4W4-01';

    protected const NAME = 'Word Hunt';

    protected const DESCRIPTION = 'Find and tap the target word in a short text.';

    protected const INSTRUCTIONS = 'Listen to the word, find it in the sentence, then tap it.';

    protected const CHECKLIST_ITEM = 'Finds a taught word in a short sentence';

    protected const WEEK = 4;

    protected const DAY = 1;

    // Each target appears once, beside a look-alike word (sat/cat, pig/big) so the learner must read it, not guess.
    protected const ROUNDS = [
        [['text' => 'The cat sat on the mat.', 'target' => 'sat'], 'sat'],
        [['text' => 'A big pig is in the mud.', 'target' => 'pig'], 'pig'],
        [['text' => 'Ben has a red hen.', 'target' => 'hen'], 'hen'],
        [['text' => 'The dog dug a pit.', 'target' => 'dug'], 'dug'],
        [['text' => 'Sam can hop on a log.', 'target' => 'hop'], 'hop'],
    ];
}

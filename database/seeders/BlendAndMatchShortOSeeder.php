<?php

namespace Database\Seeders;

// "Blend and Match: Short o" digital activity for Reading, Q4 Week 1 Monday; run with: php artisan db:seed --class=BlendAndMatchShortOSeeder
class BlendAndMatchShortOSeeder extends ReadingQuarterFourGameSeeder
{
    protected const CODE = 'RD-Q4W1-01';

    protected const NAME = 'Blend and Match: Short o';

    protected const DESCRIPTION = 'Blend three letter sounds into a short-o word, then tap its picture.';

    protected const INSTRUCTIONS = 'Listen to the separate sounds, blend the word, then match the printed word to its picture.';

    protected const CHECKLIST_ITEM = 'Blends three letter sounds to read short-o CVC words';

    protected const WEEK = 1;

    protected const DAY = 1;

    // The other pictures are short-o words too, so only blending every sound finds the match.
    protected const ROUNDS = [
        [['word' => 'dog', 'choices' => ['log', 'dog', 'pot']], 'dog'],
        [['word' => 'pot', 'choices' => ['pot', 'box', 'dog']], 'pot'],
        [['word' => 'fox', 'choices' => ['box', 'fox', 'log']], 'fox'],
        [['word' => 'box', 'choices' => ['fox', 'pot', 'box']], 'box'],
        [['word' => 'log', 'choices' => ['dog', 'log', 'fox']], 'log'],
    ];
}

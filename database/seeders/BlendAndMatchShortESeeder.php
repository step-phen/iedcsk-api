<?php

namespace Database\Seeders;

// "Blend and Match: Short e" digital activity for Reading, Q3 Week 4 Wednesday; run with: php artisan db:seed --class=BlendAndMatchShortESeeder
class BlendAndMatchShortESeeder extends ReadingQuarterThreeGameSeeder
{
    protected const CODE = 'RD-Q3W4-02';

    protected const NAME = 'Blend and Match: Short e';

    protected const DESCRIPTION = 'Blend three letter sounds into a short-e word, then tap its picture.';

    protected const INSTRUCTIONS = 'Listen to the separate sounds, blend the word, then match the printed word to its picture.';

    protected const CHECKLIST_ITEM = 'Blends three letter sounds to read short-e CVC words';

    protected const WEEK = 4;

    protected const DAY = 3;

    // The other pictures are short-e words that share letters, so only blending every sound finds the match.
    protected const ROUNDS = [
        [['word' => 'hen', 'choices' => ['hen', 'pen', 'net']], 'hen'],
        [['word' => 'net', 'choices' => ['bed', 'net', 'jet']], 'net'],
        [['word' => 'bed', 'choices' => ['web', 'hen', 'bed']], 'bed'],
        [['word' => 'jet', 'choices' => ['jet', 'net', 'pen']], 'jet'],
        [['word' => 'pen', 'choices' => ['ten', 'pen', 'web']], 'pen'],
    ];
}

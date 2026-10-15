<?php

namespace Database\Seeders;

// "Blend and Match: Short u" digital activity for Reading, Q4 Week 1 Wednesday; run with: php artisan db:seed --class=BlendAndMatchShortUSeeder
class BlendAndMatchShortUSeeder extends ReadingQuarterFourGameSeeder
{
    protected const CODE = 'RD-Q4W1-02';

    protected const NAME = 'Blend and Match: Short u';

    protected const DESCRIPTION = 'Blend three letter sounds into a short-u word, then tap its picture.';

    protected const INSTRUCTIONS = 'Listen to the separate sounds, blend the word, then match the printed word to its picture.';

    protected const CHECKLIST_ITEM = 'Blends three letter sounds to read short-u CVC words';

    protected const WEEK = 1;

    protected const DAY = 3;

    // The other pictures are short-u words too, so only blending every sound finds the match.
    protected const ROUNDS = [
        [['word' => 'sun', 'choices' => ['sun', 'bus', 'nut']], 'sun'],
        [['word' => 'cup', 'choices' => ['tub', 'cup', 'bug']], 'cup'],
        [['word' => 'bus', 'choices' => ['bug', 'sun', 'bus']], 'bus'],
        [['word' => 'bug', 'choices' => ['bug', 'hut', 'cup']], 'bug'],
        [['word' => 'nut', 'choices' => ['hut', 'nut', 'tub']], 'nut'],
    ];
}

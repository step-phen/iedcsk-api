<?php

namespace Database\Seeders;

// "Blend and Match: Short a" digital activity for Reading, Q3 Week 4 Monday; run with: php artisan db:seed --class=BlendAndMatchShortASeeder
class BlendAndMatchShortASeeder extends ReadingQuarterThreeGameSeeder
{
    protected const CODE = 'RD-Q3W4-01';

    protected const NAME = 'Blend and Match: Short a';

    protected const DESCRIPTION = 'Blend three letter sounds into a short-a word, then drag the word to its picture.';

    protected const INSTRUCTIONS = 'Listen to the separate sounds, blend the word, then match the printed word to its picture.';

    protected const CHECKLIST_ITEM = 'Blends three letter sounds to read short-a CVC words';

    protected const GAME_TYPE = 'drag_match';

    protected const WEEK = 4;

    protected const DAY = 1;

    // The other pictures are short-a words that share letters, so only blending every sound finds the match.
    protected const ROUNDS = [
        [['word' => 'cat', 'choices' => ['cat', 'can', 'cap']], 'cat'],
        [['word' => 'map', 'choices' => ['cap', 'map', 'man']], 'map'],
        [['word' => 'bag', 'choices' => ['bat', 'van', 'bag']], 'bag'],
        [['word' => 'pan', 'choices' => ['pan', 'hat', 'man']], 'pan'],
        [['word' => 'rat', 'choices' => ['rat', 'bat', 'van']], 'rat'],
    ];
}

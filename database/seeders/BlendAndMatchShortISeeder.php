<?php

namespace Database\Seeders;

// "Blend and Match: Short i" digital activity for Reading, Q3 Week 5 Monday; run with: php artisan db:seed --class=BlendAndMatchShortISeeder
class BlendAndMatchShortISeeder extends ReadingQuarterThreeGameSeeder
{
    protected const CODE = 'RD-Q3W5-01';

    protected const NAME = 'Blend and Match: Short i';

    protected const DESCRIPTION = 'Blend three letter sounds into a short-i word, then tap its picture.';

    protected const INSTRUCTIONS = 'Listen to the separate sounds, blend the word, then match the printed word to its picture.';

    protected const CHECKLIST_ITEM = 'Blends three letter sounds to read short-i CVC words';

    protected const WEEK = 5;

    protected const DAY = 1;

    // The other pictures are short-i words too, so only blending every sound finds the match.
    protected const ROUNDS = [
        [['word' => 'pig', 'choices' => ['pin', 'pig', 'lip']], 'pig'],
        [['word' => 'pin', 'choices' => ['bin', 'six', 'pin']], 'pin'],
        [['word' => 'lip', 'choices' => ['lip', 'kid', 'pig']], 'lip'],
        [['word' => 'kid', 'choices' => ['pig', 'kid', 'bin']], 'kid'],
        [['word' => 'bin', 'choices' => ['pin', 'lip', 'bin']], 'bin'],
    ];
}

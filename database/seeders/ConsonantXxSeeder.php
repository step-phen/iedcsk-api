<?php

namespace Database\Seeders;

// "Consonant Xx" digital activity for Reading, Q3 Week 3 Monday; run with: php artisan db:seed --class=ConsonantXxSeeder
class ConsonantXxSeeder extends ReadingQuarterThreeGameSeeder
{
    protected const CODE = 'RD-Q3W3-01';

    protected const NAME = 'Consonant Xx';

    protected const DESCRIPTION = 'Tap the picture whose name ends with the /ks/ sound.';

    protected const INSTRUCTIONS = 'Listen to the picture names, then tap the picture whose name ends with /ks/.';

    protected const CHECKLIST_ITEM = 'Recognizes Xx and identifies pictures that end with the /ks/ sound';

    protected const WEEK = 3;

    protected const DAY = 1;

    // No other picture ends in /k/, /s/ or /z/, since those sound close to /ks/.
    protected const ROUNDS = [
        [['choices' => ['box', 'dog', 'hat']], 'box'],
        [['choices' => ['cat', 'fox', 'sun']], 'fox'],
        [['choices' => ['pig', 'bed', 'six']], 'six'],
        [['choices' => ['ox', 'hen', 'ball']], 'ox'],
        [['choices' => ['moon', 'ax', 'cat']], 'ax'],
    ];
}

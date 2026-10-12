<?php

namespace Database\Seeders;

// "Consonant Ww" digital activity for Reading, Q3 Week 2 Friday; run with: php artisan db:seed --class=ConsonantWwSeeder
class ConsonantWwSeeder extends ConsonantGameSeeder
{
    protected const LETTER = 'Ww';

    protected const SOUND = '/w/';

    protected const QUARTER = 3;

    protected const CODE = 'RD-Q3W2-03';

    protected const WEEK = 2;

    protected const DAY = 5;

    // No V, R or Q words among the others, since they sound close to /w/.
    protected const ROUNDS = [
        ['watch', ['watch', 'sun', 'pig']],
        ['whale', ['dog', 'whale', 'moon']],
        ['watermelon', ['cake', 'hat', 'watermelon']],
        ['window', ['window', 'ball', 'sun']],
        ['worm', ['pig', 'worm', 'cake']],
    ];
}

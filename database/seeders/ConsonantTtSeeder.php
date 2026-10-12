<?php

namespace Database\Seeders;

// "Consonant Tt" digital activity for Reading, Q3 Week 2 Monday; run with: php artisan db:seed --class=ConsonantTtSeeder
class ConsonantTtSeeder extends ConsonantGameSeeder
{
    protected const LETTER = 'Tt';

    protected const SOUND = '/t/';

    protected const QUARTER = 3;

    protected const CODE = 'RD-Q3W2-01';

    protected const WEEK = 2;

    protected const DAY = 1;

    // No D words among the others, since /d/ is easy to mix up with /t/.
    protected const ROUNDS = [
        ['tiger', ['tiger', 'sun', 'fish']],
        ['tomato', ['moon', 'tomato', 'ball']],
        ['turtle', ['pig', 'leaf', 'turtle']],
        ['toothbrush', ['toothbrush', 'cake', 'sun']],
        ['teddy', ['fish', 'teddy', 'moon']],
    ];
}

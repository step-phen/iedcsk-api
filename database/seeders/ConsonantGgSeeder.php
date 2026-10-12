<?php

namespace Database\Seeders;

// "Consonant Gg" digital activity for Reading, Q2 Week 2 Wednesday; run with: php artisan db:seed --class=ConsonantGgSeeder
class ConsonantGgSeeder extends ConsonantGameSeeder
{
    protected const LETTER = 'Gg';

    protected const SOUND = 'hard /g/';

    protected const CODE = 'RD-Q2W2-02';

    protected const WEEK = 2;

    protected const DAY = 3;

    // Only hard-G words, and no C or K words among the others, since /k/ is easy to mix up with /g/.
    protected const ROUNDS = [
        ['gift', ['gift', 'sun', 'ball']],
        ['grapes', ['fish', 'grapes', 'owl']],
        ['guitar', ['pig', 'sun', 'guitar']],
        ['glasses', ['glasses', 'ball', 'owl']],
        ['gate', ['fish', 'gate', 'pig']],
    ];
}

<?php

namespace Database\Seeders;

// "Consonant Hh" digital activity for Reading, Q2 Week 2 Friday; run with: php artisan db:seed --class=ConsonantHhSeeder
class ConsonantHhSeeder extends ConsonantGameSeeder
{
    protected const LETTER = 'Hh';

    protected const SOUND = '/h/';

    protected const CODE = 'RD-Q2W2-03';

    protected const WEEK = 2;

    protected const DAY = 5;

    // No vowel words among the others, since a dropped /h/ makes "hen" sound like a vowel word.
    protected const ROUNDS = [
        ['hat', ['dog', 'hat', 'sun']],
        ['hen', ['hen', 'cup', 'ball']],
        ['horse', ['pig', 'sun', 'horse']],
        ['house', ['house', 'dog', 'cup']],
        ['heart', ['ball', 'heart', 'pig']],
    ];
}

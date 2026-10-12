<?php

namespace Database\Seeders;

// "Consonant Bb" digital activity for Reading, Q2 Week 1 Monday; run with: php artisan db:seed --class=ConsonantBbSeeder
class ConsonantBbSeeder extends ConsonantGameSeeder
{
    protected const LETTER = 'Bb';

    protected const SOUND = '/b/';

    protected const CODE = 'RD-Q2W1-01';

    protected const WEEK = 1;

    protected const DAY = 1;

    protected const ROUNDS = [
        ['ball', ['ball', 'sun', 'fish']],
        ['banana', ['moon', 'banana', 'hat']],
        ['basket', ['star', 'fish', 'basket']],
        ['balloon', ['balloon', 'hat', 'moon']],
        ['butterfly', ['sun', 'butterfly', 'star']],
    ];
}

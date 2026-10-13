<?php

namespace Database\Seeders;

// "Consonant Nn" digital activity for Reading, Q2 Week 4 Wednesday; run with: php artisan db:seed --class=ConsonantNnSeeder
class ConsonantNnSeeder extends ConsonantGameSeeder
{
    protected const LETTER = 'Nn';

    protected const SOUND = '/n/';

    protected const CODE = 'RD-Q2W4-02';

    protected const WEEK = 4;

    protected const DAY = 3;

    // No M words among the others, since /m/ is easy to mix up with /n/.
    protected const ROUNDS = [
        ['nose', ['nose', 'sun', 'fish']],
        ['nut', ['dog', 'nut', 'hat']],
        ['net', ['egg', 'sun', 'net']],
        ['noodles', ['noodles', 'fish', 'dog']],
        ['nine', ['hat', 'nine', 'egg']],
    ];
}

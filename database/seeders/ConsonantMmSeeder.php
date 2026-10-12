<?php

namespace Database\Seeders;

// "Consonant Mm" digital activity for Reading, Q2 Week 4 Monday; run with: php artisan db:seed --class=ConsonantMmSeeder
class ConsonantMmSeeder extends ConsonantGameSeeder
{
    protected const LETTER = 'Mm';

    protected const SOUND = '/m/';

    protected const CODE = 'RD-Q2W4-01';

    protected const WEEK = 4;

    protected const DAY = 1;

    // No N words among the others, since /n/ is easy to mix up with /m/.
    protected const ROUNDS = [
        ['moon', ['moon', 'sun', 'fish']],
        ['monkey', ['dog', 'monkey', 'cat']],
        ['mango', ['hat', 'sun', 'mango']],
        ['mouse', ['mouse', 'fish', 'dog']],
        ['milk', ['cat', 'milk', 'hat']],
    ];
}

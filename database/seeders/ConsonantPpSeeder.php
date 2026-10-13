<?php

namespace Database\Seeders;

// "Consonant Pp" digital activity for Reading, Q2 Week 5 Monday; run with: php artisan db:seed --class=ConsonantPpSeeder
class ConsonantPpSeeder extends ConsonantGameSeeder
{
    protected const LETTER = 'Pp';

    protected const SOUND = '/p/';

    protected const CODE = 'RD-Q2W5-01';

    protected const WEEK = 5;

    protected const DAY = 1;

    // No B words among the others, since /b/ is easy to mix up with /p/.
    protected const ROUNDS = [
        ['pig', ['pig', 'sun', 'hat']],
        ['pizza', ['fish', 'pizza', 'egg']],
        ['pear', ['sun', 'dog', 'pear']],
        ['pencil', ['pencil', 'hat', 'fish']],
        ['panda', ['egg', 'panda', 'dog']],
    ];
}

<?php

namespace Database\Seeders;

// "Consonant Ff" digital activity for Reading, Q2 Week 2 Monday; run with: php artisan db:seed --class=ConsonantFfSeeder
class ConsonantFfSeeder extends ConsonantGameSeeder
{
    protected const LETTER = 'Ff';

    protected const SOUND = '/f/';

    protected const CODE = 'RD-Q2W2-01';

    protected const WEEK = 2;

    protected const DAY = 1;

    // No V words among the others, since /v/ is easy to mix up with /f/.
    protected const ROUNDS = [
        ['fish', ['dog', 'fish', 'moon']],
        ['fan', ['fan', 'cup', 'pig']],
        ['flower', ['duck', 'moon', 'flower']],
        ['fork', ['pig', 'fork', 'dog']],
        ['flag', ['flag', 'duck', 'cup']],
    ];
}

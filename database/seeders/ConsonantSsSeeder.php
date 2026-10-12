<?php

namespace Database\Seeders;

// "Consonant Ss" digital activity for Reading, Q3 Week 1 Friday; run with: php artisan db:seed --class=ConsonantSsSeeder
class ConsonantSsSeeder extends ConsonantGameSeeder
{
    protected const LETTER = 'Ss';

    protected const SOUND = '/s/';

    protected const QUARTER = 3;

    protected const CODE = 'RD-Q3W1-03';

    protected const WEEK = 1;

    protected const DAY = 5;

    // No Z, Sh or F words among the others, since they hiss like /s/.
    protected const ROUNDS = [
        ['sun', ['sun', 'dog', 'moon']],
        ['sock', ['hat', 'sock', 'pig']],
        ['snake', ['ball', 'kite', 'snake']],
        ['star', ['star', 'cake', 'dog']],
        ['seal', ['moon', 'seal', 'hat']],
    ];
}

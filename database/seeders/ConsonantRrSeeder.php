<?php

namespace Database\Seeders;

// "Consonant Rr" digital activity for Reading, Q3 Week 1 Wednesday; run with: php artisan db:seed --class=ConsonantRrSeeder
class ConsonantRrSeeder extends ConsonantGameSeeder
{
    protected const LETTER = 'Rr';

    protected const SOUND = '/r/';

    protected const QUARTER = 3;

    protected const CODE = 'RD-Q3W1-02';

    protected const WEEK = 1;

    protected const DAY = 3;

    // No L or W words among the others, since young ears mix them up with /r/.
    protected const ROUNDS = [
        ['rabbit', ['rabbit', 'sun', 'fish']],
        ['rainbow', ['cat', 'rainbow', 'hat']],
        ['ring', ['ball', 'dog', 'ring']],
        ['robot', ['robot', 'pig', 'cat']],
        ['rose', ['fish', 'rose', 'ball']],
    ];
}

<?php

namespace Database\Seeders;

// "Consonant Qq" digital activity for Reading, Q3 Week 1 Monday; run with: php artisan db:seed --class=ConsonantQqSeeder
class ConsonantQqSeeder extends ConsonantGameSeeder
{
    protected const LETTER = 'Qq';

    protected const SOUND = '/kw/';

    protected const QUARTER = 3;

    protected const CODE = 'RD-Q3W1-01';

    protected const WEEK = 1;

    protected const DAY = 1;

    // No C, K or W words among the others, since they sound close to /kw/.
    protected const ROUNDS = [
        ['queen', ['queen', 'sun', 'fish']],
        ['question', ['dog', 'question', 'moon']],
        ['quarter', ['hat', 'ball', 'quarter']],
        ['quiet', ['quiet', 'pig', 'sun']],
        ['quiz', ['fish', 'quiz', 'dog']],
    ];
}

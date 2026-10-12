<?php

namespace Database\Seeders;

// "Consonant Vv" digital activity for Reading, Q3 Week 2 Wednesday; run with: php artisan db:seed --class=ConsonantVvSeeder
class ConsonantVvSeeder extends ConsonantGameSeeder
{
    protected const LETTER = 'Vv';

    protected const SOUND = '/v/';

    protected const QUARTER = 3;

    protected const CODE = 'RD-Q3W2-02';

    protected const WEEK = 2;

    protected const DAY = 3;

    // No F or B words among the others, since /f/ and /b/ are easy to mix up with /v/.
    protected const ROUNDS = [
        ['violin', ['violin', 'sun', 'dog']],
        ['vase', ['moon', 'vase', 'hat']],
        ['volcano', ['pig', 'cake', 'volcano']],
        ['vest', ['vest', 'dog', 'moon']],
        ['vegetables', ['hat', 'vegetables', 'sun']],
    ];
}

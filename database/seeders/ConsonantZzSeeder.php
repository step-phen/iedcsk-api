<?php

namespace Database\Seeders;

// "Consonant Zz" digital activity for Reading, Q3 Week 3 Friday; run with: php artisan db:seed --class=ConsonantZzSeeder
class ConsonantZzSeeder extends ConsonantGameSeeder
{
    protected const LETTER = 'Zz';

    protected const SOUND = '/z/';

    protected const QUARTER = 3;

    protected const CODE = 'RD-Q3W3-03';

    protected const WEEK = 3;

    protected const DAY = 5;

    // No S or J words among the others, since they sound close to /z/.
    protected const ROUNDS = [
        ['zebra', ['zebra', 'dog', 'moon']],
        ['zero', ['cat', 'zero', 'hat']],
        ['zipper', ['ball', 'pig', 'zipper']],
        ['zucchini', ['zucchini', 'fish', 'cake']],
        ['zigzag', ['hen', 'zigzag', 'dog']],
    ];
}

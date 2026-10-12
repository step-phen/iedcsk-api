<?php

namespace Database\Seeders;

// "Consonant Cc" digital activity for Reading, Q2 Week 1 Wednesday; run with: php artisan db:seed --class=ConsonantCcSeeder
class ConsonantCcSeeder extends ConsonantGameSeeder
{
    protected const LETTER = 'Cc';

    protected const SOUND = 'hard /k/';

    protected const CODE = 'RD-Q2W1-02';

    protected const WEEK = 1;

    protected const DAY = 3;

    // Only hard-C words, and no K words among the others, so the /k/ sound points to one picture.
    protected const ROUNDS = [
        ['cat', ['pig', 'cat', 'egg']],
        ['cup', ['cup', 'hen', 'sheep']],
        ['car', ['horse', 'pig', 'car']],
        ['crayons', ['egg', 'crayons', 'hen']],
        ['cow', ['cow', 'sheep', 'horse']],
    ];
}

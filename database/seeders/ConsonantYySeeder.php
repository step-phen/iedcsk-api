<?php

namespace Database\Seeders;

// "Consonant Yy" digital activity for Reading, Q3 Week 3 Wednesday; run with: php artisan db:seed --class=ConsonantYySeeder
class ConsonantYySeeder extends ConsonantGameSeeder
{
    protected const LETTER = 'Yy';

    protected const SOUND = '/y/';

    protected const QUARTER = 3;

    protected const CODE = 'RD-Q3W3-02';

    protected const WEEK = 3;

    protected const DAY = 3;

    // No J, W or vowel words among the others, since they sound close to /y/.
    protected const ROUNDS = [
        ['yarn', ['yarn', 'dog', 'sun']],
        ['yawn', ['cat', 'yawn', 'moon']],
        ['yolk', ['hat', 'ball', 'yolk']],
        ['yam', ['yam', 'pig', 'fish']],
        ['yacht', ['sun', 'yacht', 'cake']],
    ];
}

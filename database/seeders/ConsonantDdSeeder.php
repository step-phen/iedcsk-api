<?php

namespace Database\Seeders;

// "Consonant Dd" digital activity for Reading, Q2 Week 1 Friday; run with: php artisan db:seed --class=ConsonantDdSeeder
class ConsonantDdSeeder extends ConsonantGameSeeder
{
    protected const LETTER = 'Dd';

    protected const SOUND = '/d/';

    protected const CODE = 'RD-Q2W1-03';

    protected const WEEK = 1;

    protected const DAY = 5;

    // No T words among the others, since /t/ is easy to mix up with /d/.
    protected const ROUNDS = [
        ['dog', ['monkey', 'owl', 'dog']],
        ['duck', ['duck', 'mouse', 'sun']],
        ['drum', ['owl', 'drum', 'hen']],
        ['door', ['mouse', 'door', 'hen']],
        ['donut', ['donut', 'sun', 'monkey']],
    ];
}

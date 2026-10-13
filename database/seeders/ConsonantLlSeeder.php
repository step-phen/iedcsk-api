<?php

namespace Database\Seeders;

// "Consonant Ll" digital activity for Reading, Q2 Week 3 Friday; run with: php artisan db:seed --class=ConsonantLlSeeder
class ConsonantLlSeeder extends ConsonantGameSeeder
{
    protected const LETTER = 'Ll';

    protected const SOUND = '/l/';

    protected const CODE = 'RD-Q2W3-03';

    protected const WEEK = 3;

    protected const DAY = 5;

    // No R words among the others, since /r/ is easy to mix up with /l/.
    protected const ROUNDS = [
        ['ladybug', ['sun', 'ladybug', 'fish']],
        ['leaf', ['leaf', 'dog', 'hat']],
        ['lemon', ['cup', 'sun', 'lemon']],
        ['lollipop', ['lollipop', 'fish', 'cup']],
        ['lamp', ['hat', 'lamp', 'dog']],
    ];
}

<?php

namespace Database\Seeders;

// "Consonant Kk" digital activity for Reading, Q2 Week 3 Wednesday; run with: php artisan db:seed --class=ConsonantKkSeeder
class ConsonantKkSeeder extends ConsonantGameSeeder
{
    protected const LETTER = 'Kk';

    protected const SOUND = '/k/';

    protected const CODE = 'RD-Q2W3-02';

    protected const WEEK = 3;

    protected const DAY = 3;

    // No C or G words among the others, since /k/ is easy to mix up with them.
    protected const ROUNDS = [
        ['key', ['key', 'sun', 'dog']],
        ['kite', ['moon', 'kite', 'hen']],
        ['kettle', ['pig', 'sun', 'kettle']],
        ['king', ['king', 'dog', 'moon']],
        ['koala', ['hen', 'koala', 'pig']],
    ];
}

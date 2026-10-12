<?php

namespace Database\Seeders;

// "Consonant Jj" digital activity for Reading, Q2 Week 3 Monday; run with: php artisan db:seed --class=ConsonantJjSeeder
class ConsonantJjSeeder extends ConsonantGameSeeder
{
    protected const LETTER = 'Jj';

    protected const SOUND = '/j/';

    protected const CODE = 'RD-Q2W3-01';

    protected const WEEK = 3;

    protected const DAY = 1;

    // No CH, Y or soft-G words among the others, since they are easy to mix up with /j/.
    protected const ROUNDS = [
        ['jar', ['sun', 'jar', 'fish']],
        ['juice', ['juice', 'cow', 'moon']],
        ['jacket', ['pig', 'sun', 'jacket']],
        ['jellyfish', ['fish', 'jellyfish', 'pig']],
        ['jump-rope', ['jump-rope', 'moon', 'cow']],
    ];
}

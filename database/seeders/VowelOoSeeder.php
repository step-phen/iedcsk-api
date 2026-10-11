<?php

namespace Database\Seeders;

// "Vowel Oo" game for Reading, Q1 Week 3 Friday; run with: php artisan db:seed --class=VowelOoSeeder
class VowelOoSeeder extends VowelGameSeeder
{
    protected const LETTER = 'Oo';

    protected const CODE = 'RD-Q1W3-03';

    protected const WEEK = 3;

    protected const DAY = 5;

    protected const ROUNDS = [
        ['octopus', ['dog', 'octopus', 'ball', 'sun']],
        ['orange', ['fish', 'car', 'orange', 'hat']],
        ['owl', ['owl', 'cat', 'moon', 'dog']],
        ['onion', ['ball', 'sun', 'fish', 'onion']],
        ['olive', ['car', 'olive', 'hat', 'cat']],
    ];
}

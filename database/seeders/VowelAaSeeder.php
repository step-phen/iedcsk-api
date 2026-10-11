<?php

namespace Database\Seeders;

// "Vowel Aa" game for Reading, Q1 Week 2 Friday; run with: php artisan db:seed --class=VowelAaSeeder
class VowelAaSeeder extends VowelGameSeeder
{
    protected const LETTER = 'Aa';

    protected const CODE = 'RD-Q1W2-03';

    protected const WEEK = 2;

    protected const DAY = 5;

    protected const ROUNDS = [
        ['apple', ['dog', 'apple', 'ball', 'sun']],
        ['ant', ['fish', 'car', 'ant', 'hat']],
        ['arrow', ['arrow', 'cat', 'moon', 'dog']],
        ['axe', ['ball', 'sun', 'fish', 'axe']],
        ['astronaut', ['car', 'astronaut', 'hat', 'cat']],
    ];
}

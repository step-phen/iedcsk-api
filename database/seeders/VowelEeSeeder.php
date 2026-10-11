<?php

namespace Database\Seeders;

// "Vowel Ee" game for Reading, Q1 Week 3 Monday; run with: php artisan db:seed --class=VowelEeSeeder
class VowelEeSeeder extends VowelGameSeeder
{
    protected const LETTER = 'Ee';

    protected const CODE = 'RD-Q1W3-01';

    protected const WEEK = 3;

    protected const DAY = 1;

    protected const ROUNDS = [
        ['egg', ['dog', 'egg', 'ball', 'sun']],
        ['elephant', ['fish', 'car', 'elephant', 'hat']],
        ['envelope', ['envelope', 'cat', 'moon', 'dog']],
        ['eagle', ['ball', 'sun', 'fish', 'eagle']],
        ['eggplant', ['car', 'eggplant', 'hat', 'cat']],
    ];
}

<?php

namespace Database\Seeders;

// "Vowel Uu" game for Reading, Q1 Week 4 Monday; run with: php artisan db:seed --class=VowelUuSeeder
class VowelUuSeeder extends VowelGameSeeder
{
    protected const LETTER = 'Uu';

    protected const CODE = 'RD-Q1W4-01';

    protected const WEEK = 4;

    protected const DAY = 1;

    protected const ROUNDS = [
        ['umbrella', ['dog', 'umbrella', 'ball', 'sun']],
        ['unicorn', ['fish', 'car', 'unicorn', 'hat']],
        ['up', ['up', 'cat', 'moon', 'dog']],
        ['ufo', ['ball', 'sun', 'fish', 'ufo']],
        ['utensils', ['car', 'utensils', 'hat', 'cat']],
    ];
}

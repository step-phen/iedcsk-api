<?php

namespace Database\Seeders;

// "Vowel Ii" game for Reading, Q1 Week 3 Wednesday; run with: php artisan db:seed --class=VowelIiSeeder
class VowelIiSeeder extends VowelGameSeeder
{
    protected const LETTER = 'Ii';

    protected const CODE = 'RD-Q1W3-02';

    protected const WEEK = 3;

    protected const DAY = 3;

    protected const ROUNDS = [
        ['ice-cream', ['dog', 'ice-cream', 'ball', 'sun']],
        ['insect', ['fish', 'car', 'insect', 'hat']],
        ['island', ['island', 'cat', 'moon', 'dog']],
        ['iguana', ['ball', 'sun', 'fish', 'iguana']],
        ['ink', ['car', 'ink', 'hat', 'cat']],
    ];
}

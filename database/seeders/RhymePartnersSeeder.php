<?php

namespace Database\Seeders;

// "Rhyme Partners" digital activity for Reading, Q4 Week 2 Wednesday; run with: php artisan db:seed --class=RhymePartnersSeeder
class RhymePartnersSeeder extends ReadingQuarterFourGameSeeder
{
    protected const CODE = 'RD-Q4W2-02';

    protected const NAME = 'Rhyme Partners';

    protected const DESCRIPTION = 'Listen to the picture names, then tap the word that rhymes.';

    protected const INSTRUCTIONS = 'Listen to the picture names, then match words that rhyme.';

    protected const CHECKLIST_ITEM = 'Recognizes words that rhyme';

    protected const WEEK = 2;

    protected const DAY = 3;

    protected const ROUNDS = [
        [['word' => 'cat', 'choices' => ['dog', 'hat', 'sun']], 'hat'],
        [['word' => 'fox', 'choices' => ['box', 'cup', 'bed']], 'box'],
        [['word' => 'goat', 'choices' => ['car', 'fish', 'boat']], 'boat'],
        [['word' => 'moon', 'choices' => ['spoon', 'hen', 'bus']], 'spoon'],
        [['word' => 'bee', 'choices' => ['cake', 'tree', 'pig']], 'tree'],
    ];
}

<?php

namespace Database\Seeders;

// "Short Vowel Word Sort" digital activity for Reading, Q4 Week 6 Monday; run with: php artisan db:seed --class=ShortVowelWordSortSeeder
class ShortVowelWordSortSeeder extends ReadingQuarterFourGameSeeder
{
    protected const CODE = 'RD-Q4W6-01';

    protected const NAME = 'Short Vowel Word Sort';

    protected const DESCRIPTION = 'Read taught CVC words, then sort them by their short vowel sounds.';

    protected const INSTRUCTIONS = 'Read the word, then tap the locker with its middle vowel.';

    protected const CHECKLIST_ITEM = 'Sorts CVC words by their short vowel sound';

    protected const WEEK = 6;

    protected const DAY = 1;

    // One word per short vowel, in mixed order; every round offers all five vowel lockers.
    protected const ROUNDS = [
        [['word' => 'web', 'choices' => ['a', 'e', 'i', 'o', 'u']], 'e'],
        [['word' => 'rug', 'choices' => ['a', 'e', 'i', 'o', 'u']], 'u'],
        [['word' => 'bag', 'choices' => ['a', 'e', 'i', 'o', 'u']], 'a'],
        [['word' => 'mop', 'choices' => ['a', 'e', 'i', 'o', 'u']], 'o'],
        [['word' => 'lid', 'choices' => ['a', 'e', 'i', 'o', 'u']], 'i'],
    ];
}

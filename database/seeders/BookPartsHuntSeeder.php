<?php

namespace Database\Seeders;

// "Book Parts Hunt" digital activity for Reading, Q4 Week 2 Friday; run with: php artisan db:seed --class=BookPartsHuntSeeder
class BookPartsHuntSeeder extends ReadingQuarterFourGameSeeder
{
    protected const CODE = 'RD-Q4W2-03';

    protected const NAME = 'Book Parts Hunt';

    protected const DESCRIPTION = 'Tap the front cover, title, or pages of the book shown.';

    protected const INSTRUCTIONS = 'Tap the requested part of the book shown.';

    protected const CHECKLIST_ITEM = 'Identifies the front cover, title, and pages of a book';

    protected const WEEK = 2;

    protected const DAY = 5;

    // A different book each round; the answer is the part to tap: front-cover, title or pages.
    protected const ROUNDS = [
        [['book' => 'fox', 'part' => 'front-cover'], 'front-cover'],
        [['book' => 'sun', 'part' => 'title'], 'title'],
        [['book' => 'bug', 'part' => 'pages'], 'pages'],
        [['book' => 'hen', 'part' => 'title'], 'title'],
        [['book' => 'cat', 'part' => 'pages'], 'pages'],
    ];
}

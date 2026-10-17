<?php

namespace Database\Seeders;

// "My Question" digital activity for Language, Q4 Week 6 Monday; run with: php artisan db:seed --class=MyQuestionSeeder
class MyQuestionSeeder extends LanguageQuarterFourGameSeeder
{
    protected const CODE = 'LG-Q4W6-01';

    protected const NAME = 'My Question';

    protected const DESCRIPTION = 'View the picture, then choose a suitable spoken question.';

    protected const INSTRUCTIONS = 'Look at the picture, listen to each question, then tap the question that fits the picture.';

    protected const CHECKLIST_ITEM = 'Chooses a suitable who, what, or where question about a picture';

    protected const WEEK = 6;

    protected const DAY = 1;

    // The game holds the pictures and question sentences; each answer is the question that the picture can answer.
    protected const ROUNDS = [
        [['scene' => 'kick', 'choices' => ['who-bus', 'what-kick', 'where-fish']], 'what-kick'],
        [['scene' => 'cat-box', 'choices' => ['where-cat', 'what-cow', 'who-sleep']], 'where-cat'],
        [['scene' => 'bake', 'choices' => ['where-moon', 'what-baby', 'who-bake']], 'who-bake'],
        [['scene' => 'bird-nest', 'choices' => ['who-bus', 'where-bird', 'what-cow']], 'where-bird'],
        [['scene' => 'kite', 'choices' => ['who-sleep', 'where-fish', 'what-kite']], 'what-kite'],
    ];
}

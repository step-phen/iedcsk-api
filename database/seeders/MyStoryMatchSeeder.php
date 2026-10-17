<?php

namespace Database\Seeders;

// "My Story Match" digital activity for Language, Q4 Week 6 Friday; run with: php artisan db:seed --class=MyStoryMatchSeeder
class MyStoryMatchSeeder extends LanguageQuarterFourGameSeeder
{
    protected const CODE = 'LG-Q4W6-03';

    protected const NAME = 'My Story Match';

    protected const DESCRIPTION = 'Listen to the story, then match sentences to its event pictures.';

    protected const INSTRUCTIONS = 'Listen to the story, hear each sentence, then tap the picture that shows it.';

    protected const CHECKLIST_ITEM = 'Matches spoken story sentences to event pictures';

    protected const WEEK = 6;

    protected const DAY = 5;

    // The game holds the story, its sentences and pictures; each answer is the event the sentence tells.
    protected const ROUNDS = [
        [['event' => 'plant', 'choices' => ['plant', 'flower', 'sun']], 'plant'],
        [['event' => 'water', 'choices' => ['give', 'water', 'plant']], 'water'],
        [['event' => 'sun', 'choices' => ['water', 'flower', 'sun']], 'sun'],
        [['event' => 'flower', 'choices' => ['flower', 'give', 'plant']], 'flower'],
        [['event' => 'give', 'choices' => ['sun', 'water', 'give']], 'give'],
    ];
}

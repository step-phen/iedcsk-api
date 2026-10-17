<?php

namespace Database\Seeders;

// "Language Review" digital activity for Language, Q4 Week 7 Wednesday; run with: php artisan db:seed --class=LanguageReview4Seeder
class LanguageReview4Seeder extends LanguageQuarterFourGameSeeder
{
    protected const CODE = 'LG-Q4W7-02';

    protected const NAME = 'Language Review';

    protected const DESCRIPTION = 'Complete audio-choice, matching, and sequencing tasks.';

    protected const INSTRUCTIONS = 'Listen to each task, then tap the right picture, or tap the pictures in order.';

    protected const CHECKLIST_ITEM = 'Reviews taught sounds, vocabulary, stories, and directions';

    protected const WEEK = 7;

    protected const DAY = 3;

    // "listen" is an audio choice, "match" pairs a description with a picture, and "order" answers with the pictures in order joined by ">".
    protected const ROUNDS = [
        [['type' => 'listen', 'prompt' => 'Which one rhymes with hat?', 'choices' => ['sun', 'cat', 'pig']], 'cat'],
        [['type' => 'listen', 'prompt' => 'Which one rhymes with log?', 'choices' => ['dog', 'bed', 'cup']], 'dog'],
        [['type' => 'listen', 'prompt' => 'Tap the grandmother.', 'choices' => ['baby', 'dad', 'grandma']], 'grandma'],
        [['type' => 'listen', 'prompt' => 'Tap the thing you wear on your head.', 'choices' => ['shoe', 'cap', 'glove']], 'cap'],
        [['type' => 'match', 'prompt' => 'It is big and gray. It has a long trunk.', 'choices' => ['elephant', 'mouse', 'frog']], 'elephant'],
        [['type' => 'match', 'prompt' => 'The boy is swimming in the pool.', 'choices' => ['run', 'swim', 'sleep']], 'swim'],
        [['type' => 'match', 'prompt' => 'It is round and red, and you can eat it.', 'choices' => ['ball', 'car', 'apple']], 'apple'],
        [['type' => 'order', 'prompt' => 'Tap what happens first, next, and last.', 'choices' => ['chick', 'hen', 'egg']], 'egg>chick>hen'],
        [['type' => 'order', 'prompt' => 'Tap what happens first, next, and last.', 'choices' => ['flower', 'seed', 'sprout']], 'seed>sprout>flower'],
        [['type' => 'order', 'prompt' => 'Tap what happens first, next, and last.', 'choices' => ['dress', 'school', 'wake']], 'wake>dress>school'],
    ];
}

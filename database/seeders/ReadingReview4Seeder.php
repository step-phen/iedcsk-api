<?php

namespace Database\Seeders;

// "Reading Review" digital activity for Reading, Q4 Week 7 Wednesday; run with: php artisan db:seed --class=ReadingReview4Seeder
class ReadingReview4Seeder extends ReadingQuarterFourGameSeeder
{
    protected const CODE = 'RD-Q4W7-02';

    protected const NAME = 'Reading Review';

    protected const DESCRIPTION = 'Complete familiar word-reading, short vowel, sentence-matching, rhyme, and story-picture tasks.';

    protected const INSTRUCTIONS = 'Read or listen to each task, then tap the right cone.';

    protected const CHECKLIST_ITEM = 'Reviews taught CVC words, short vowels, sentences, rhymes, and story details';

    protected const WEEK = 7;

    protected const DAY = 3;

    private const STORY = 'Pam has a pet hen. The hen sits in a box. It has an egg.';

    // Sentence choices are "<who>-<where>" scenes; vowel choices are letters; the rest are picture ids.
    protected const ROUNDS = [
        [['type' => 'word', 'text' => 'hen', 'choices' => ['pen', 'hen', 'ten']], 'hen'],
        [['type' => 'word', 'text' => 'fox', 'choices' => ['box', 'dog', 'fox']], 'fox'],
        [['type' => 'vowel', 'text' => 'sun', 'choices' => ['a', 'u', 'i']], 'u'],
        [['type' => 'vowel', 'text' => 'pig', 'choices' => ['i', 'e', 'o']], 'i'],
        [['type' => 'sentence', 'text' => 'The cat is on the bed.', 'choices' => ['cat-box', 'dog-bed', 'cat-bed']], 'cat-bed'],
        [['type' => 'sentence', 'text' => 'The bug is on a cup.', 'choices' => ['bug-cup', 'fox-cup', 'bug-hat']], 'bug-cup'],
        [['type' => 'rhyme', 'text' => 'cat', 'choices' => ['sun', 'hat', 'pig']], 'hat'],
        [['type' => 'rhyme', 'text' => 'log', 'choices' => ['bed', 'cup', 'dog']], 'dog'],
        [['type' => 'story', 'text' => self::STORY, 'question' => 'What does Pam have?', 'choices' => ['hen', 'pig', 'fox']], 'hen'],
        [['type' => 'story', 'text' => self::STORY, 'question' => 'Where does the hen sit?', 'choices' => ['bed', 'box', 'tub']], 'box'],
    ];
}

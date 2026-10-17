<?php

namespace Database\Seeders;

// "Reading Final Assessment" digital activity for Reading, Q4 Week 7 Friday; run with: php artisan db:seed --class=ReadingFinalAssessment4Seeder
class ReadingFinalAssessment4Seeder extends ReadingQuarterFourGameSeeder
{
    protected const CODE = 'RD-Q4W7-03';

    protected const NAME = 'Reading Final Assessment';

    protected const DESCRIPTION = 'Read taught words and a short passage, then answer simple questions.';

    protected const INSTRUCTIONS = 'Read each word or the short passage, then tap the right picture.';

    protected const CHECKLIST_ITEM = 'Reads taught CVC words and a short passage and answers simple questions';

    protected const WEEK = 7;

    protected const DAY = 5;

    private const PASSAGE = ['Meg has a hen.', 'The hen sits in a box.', 'Meg gets a cup.', 'She puts an egg in the cup.', 'Meg and the hen sit in the sun.'];

    // Ten words, two per short vowel, then five questions on one passage.
    protected const ROUNDS = [
        [['type' => 'word', 'text' => 'cat', 'choices' => ['hat', 'cat', 'bat']], 'cat'],
        [['type' => 'word', 'text' => 'bag', 'choices' => ['bag', 'bug', 'bed']], 'bag'],
        [['type' => 'word', 'text' => 'hen', 'choices' => ['pen', 'ten', 'hen']], 'hen'],
        [['type' => 'word', 'text' => 'bed', 'choices' => ['bug', 'bed', 'box']], 'bed'],
        [['type' => 'word', 'text' => 'pig', 'choices' => ['pig', 'pin', 'pen']], 'pig'],
        [['type' => 'word', 'text' => 'six', 'choices' => ['sun', 'fox', 'six']], 'six'],
        [['type' => 'word', 'text' => 'dog', 'choices' => ['log', 'dog', 'pig']], 'dog'],
        [['type' => 'word', 'text' => 'box', 'choices' => ['box', 'fox', 'bus']], 'box'],
        [['type' => 'word', 'text' => 'sun', 'choices' => ['nut', 'net', 'sun']], 'sun'],
        [['type' => 'word', 'text' => 'cup', 'choices' => ['cap', 'cup', 'pup']], 'cup'],
        [['type' => 'passage', 'text' => self::PASSAGE, 'question' => 'Who has a hen?', 'choices' => ['boy', 'girl', 'man']], 'girl'],
        [['type' => 'passage', 'text' => self::PASSAGE, 'question' => 'Where does the hen sit?', 'choices' => ['box', 'bed', 'bus']], 'box'],
        [['type' => 'passage', 'text' => self::PASSAGE, 'question' => 'What does Meg get?', 'choices' => ['cap', 'pen', 'cup']], 'cup'],
        [['type' => 'passage', 'text' => self::PASSAGE, 'question' => 'What goes in the cup?', 'choices' => ['egg', 'nut', 'bug']], 'egg'],
        [['type' => 'passage', 'text' => self::PASSAGE, 'question' => 'Where do Meg and the hen sit?', 'choices' => ['tub', 'van', 'sun']], 'sun'],
    ];
}

<?php

namespace Database\Seeders;

// "My New Reading" digital activity for Reading, Q4 Week 7 Monday; run with: php artisan db:seed --class=MyNewReadingSeeder
class MyNewReadingSeeder extends ReadingQuarterFourGameSeeder
{
    protected const CODE = 'RD-Q4W7-01';

    protected const NAME = 'My New Reading';

    protected const DESCRIPTION = 'Read a new short text made from taught words, then answer simple questions about it.';

    protected const INSTRUCTIONS = 'Read the new story, then tap the picture that answers each question.';

    protected const CHECKLIST_ITEM = 'Reads an unfamiliar short decodable text and answers simple questions about it';

    protected const WEEK = 7;

    protected const DAY = 1;

    private const TEXT = ['Ben has a pet pup.', 'The pup is in a box.', 'Ben gets the pup a red cap.', 'The pup naps on a bed.', 'Ben hugs the pup.'];

    // "line" is the sentence that holds the answer, highlighted once the question is answered.
    protected const ROUNDS = [
        [['text' => self::TEXT, 'line' => 0, 'question' => 'Who has a pet pup?', 'choices' => ['pam', 'ben', 'dad']], 'ben'],
        [['text' => self::TEXT, 'line' => 1, 'question' => 'Where is the pup?', 'choices' => ['bag', 'tub', 'box']], 'box'],
        [['text' => self::TEXT, 'line' => 2, 'question' => 'What does Ben get the pup?', 'choices' => ['cap', 'cup', 'pen']], 'cap'],
        [['text' => self::TEXT, 'line' => 3, 'question' => 'Where does the pup nap?', 'choices' => ['van', 'bed', 'tub']], 'bed'],
        [['text' => self::TEXT, 'line' => 4, 'question' => 'What does Ben do to the pup?', 'choices' => ['hug', 'run', 'sit']], 'hug'],
    ];
}

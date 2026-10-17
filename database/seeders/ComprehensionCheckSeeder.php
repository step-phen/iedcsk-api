<?php

namespace Database\Seeders;

// "Comprehension Check" digital activity for Reading, Q4 Week 4 Wednesday; run with: php artisan db:seed --class=ComprehensionCheckSeeder
class ComprehensionCheckSeeder extends ReadingQuarterFourGameSeeder
{
    protected const CODE = 'RD-Q4W4-02';

    protected const NAME = 'Comprehension Check';

    protected const DESCRIPTION = 'Read the story with optional audio support, then choose answers to simple questions.';

    protected const INSTRUCTIONS = 'Read the story or tap the speaker to hear it, then tap the picture that answers each question.';

    protected const CHECKLIST_ITEM = 'Answers who, what, and where questions about a short story';

    protected const WEEK = 4;

    protected const DAY = 3;

    private const STORY = 'Tom has a red bus. Tom and Dad get on the bus. The bus goes to the farm. Tom sees a pig. The pig is in a tub.';

    // Every round asks about the same story, covering who, what, and where.
    protected const ROUNDS = [
        [['story' => self::STORY, 'question' => 'Who has a red bus?', 'choices' => ['pam', 'tom', 'dad']], 'tom'],
        [['story' => self::STORY, 'question' => 'What does Tom have?', 'choices' => ['van', 'bike', 'bus']], 'bus'],
        [['story' => self::STORY, 'question' => 'Where does the bus go?', 'choices' => ['farm', 'school', 'beach']], 'farm'],
        [['story' => self::STORY, 'question' => 'What does Tom see?', 'choices' => ['hen', 'pig', 'cow']], 'pig'],
        [['story' => self::STORY, 'question' => 'Where is the pig?', 'choices' => ['box', 'bed', 'tub']], 'tub'],
    ];
}

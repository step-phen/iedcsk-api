<?php

namespace Database\Seeders;

// "Who and Where?" digital activity for Reading, Q4 Week 6 Friday; run with: php artisan db:seed --class=WhoAndWhereSeeder
class WhoAndWhereSeeder extends ReadingQuarterFourGameSeeder
{
    protected const CODE = 'RD-Q4W6-03';

    protected const NAME = 'Who and Where?';

    protected const DESCRIPTION = 'Read the short story, then choose its character and setting pictures.';

    protected const INSTRUCTIONS = 'Read the story, then tap who is in it and where it happens.';

    protected const CHECKLIST_ITEM = 'Identifies the character and setting of a short story';

    protected const WEEK = 6;

    protected const DAY = 5;

    // One score per story: both picks must be right, so the answer is "<who>|<where>".
    protected const ROUNDS = [
        [['text' => 'Dan the dog digs in the park. He finds a big stick.', 'who' => ['cat', 'dog', 'pig'], 'where' => ['park', 'beach', 'school']], 'dog|park'],
        [['text' => 'A hen sits on the farm. She has six eggs.', 'who' => ['hen', 'fox', 'bug'], 'where' => ['school', 'farm', 'woods']], 'hen|farm'],
        [['text' => 'Pam swims at the beach. The sun is hot.', 'who' => ['ben', 'dad', 'pam'], 'where' => ['beach', 'park', 'home']], 'pam|beach'],
        [['text' => 'A fox naps in the woods. It is on a log.', 'who' => ['fox', 'dog', 'hen'], 'where' => ['farm', 'woods', 'beach']], 'fox|woods'],
        [['text' => 'Ben sits at his desk at school. He reads a book.', 'who' => ['pam', 'ben', 'cat'], 'where' => ['home', 'park', 'school']], 'ben|school'],
    ];
}

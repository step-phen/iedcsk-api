<?php

namespace Database\Seeders;

// "Language Final Assessment" digital activity for Language, Q4 Week 7 Friday; run with: php artisan db:seed --class=LanguageFinalAssessment4Seeder
class LanguageFinalAssessment4Seeder extends LanguageQuarterFourGameSeeder
{
    protected const CODE = 'LG-Q4W7-03';

    protected const NAME = 'Language Final Assessment';

    protected const DESCRIPTION = 'Answer digital sound, picture, story, and direction tasks.';

    protected const INSTRUCTIONS = 'Listen to each task, then tap the right picture.';

    protected const CHECKLIST_ITEM = 'Demonstrates taught listening and vocabulary skills in sound, picture, story, and direction tasks';

    protected const WEEK = 7;

    protected const DAY = 5;

    private const STORY = ['Tim has a red kite.', 'He goes to the park with Dad.', 'The wind takes the kite up high.', 'Tim and Dad eat lunch under a tree.'];

    // Story rounds carry the story so the game can tell it before the first story question.
    protected const ROUNDS = [
        [['type' => 'sound', 'prompt' => 'Which one rhymes with cake?', 'choices' => ['snake', 'fish', 'cup']], 'snake'],
        [['type' => 'sound', 'prompt' => 'Which one rhymes with bee?', 'choices' => ['dog', 'tree', 'hat']], 'tree'],
        [['type' => 'sound', 'prompt' => 'Which one begins like ball?', 'choices' => ['cat', 'sun', 'bear']], 'bear'],
        [['type' => 'sound', 'prompt' => 'Which one begins like moon?', 'choices' => ['monkey', 'fish', 'pig']], 'monkey'],
        [['type' => 'picture', 'prompt' => 'Tap the baby.', 'choices' => ['grandpa', 'baby', 'dad']], 'baby'],
        [['type' => 'picture', 'prompt' => 'Tap the one that is soft and fluffy.', 'choices' => ['rock', 'cup', 'teddy']], 'teddy'],
        [['type' => 'picture', 'prompt' => 'Tap the happy face.', 'choices' => ['happy', 'sad', 'angry']], 'happy'],
        [['type' => 'picture', 'prompt' => 'Tap the one who is sleeping.', 'choices' => ['run', 'sleep', 'eat']], 'sleep'],
        [['type' => 'story', 'story' => self::STORY, 'prompt' => 'What does Tim have?', 'choices' => ['ball', 'bike', 'kite']], 'kite'],
        [['type' => 'story', 'story' => self::STORY, 'prompt' => 'Where does Tim go?', 'choices' => ['park', 'school', 'store']], 'park'],
        [['type' => 'story', 'story' => self::STORY, 'prompt' => 'Who goes with Tim?', 'choices' => ['grandma', 'dad', 'baby']], 'dad'],
        [['type' => 'story', 'story' => self::STORY, 'prompt' => 'Where do Tim and Dad eat lunch?', 'choices' => ['car', 'bed', 'tree']], 'tree'],
        [['type' => 'direction', 'prompt' => 'Tap the big dog.', 'choices' => ['small-dog', 'big-dog', 'big-cat']], 'big-dog'],
        [['type' => 'direction', 'prompt' => 'Tap the two apples.', 'choices' => ['one-apple', 'two-bananas', 'two-apples']], 'two-apples'],
        [['type' => 'direction', 'prompt' => 'Tap the small star.', 'choices' => ['small-star', 'big-star', 'small-moon']], 'small-star'],
    ];
}

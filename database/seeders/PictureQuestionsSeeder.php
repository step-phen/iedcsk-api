<?php

namespace Database\Seeders;

// "Picture Questions" digital activity for Language, Q4 Week 7 Monday; run with: php artisan db:seed --class=PictureQuestionsSeeder
class PictureQuestionsSeeder extends LanguageQuarterFourGameSeeder
{
    protected const CODE = 'LG-Q4W7-01';

    protected const NAME = 'Picture Questions';

    protected const DESCRIPTION = 'Listen, then choose descriptions and answers that fit the scene.';

    protected const INSTRUCTIONS = 'Look at the scene, listen to each sentence, then tap the one that fits the picture.';

    protected const CHECKLIST_ITEM = 'Chooses a description or an answer that fits a scene';

    protected const WEEK = 7;

    protected const DAY = 1;

    // The game holds the scenes, their questions and the sentences; each answer is the sentence that fits the scene.
    protected const ROUNDS = [
        [['scene' => 'cow', 'choices' => ['cow-bed', 'cow-grass', 'cow-fly']], 'cow-grass'],
        [['scene' => 'duck', 'choices' => ['duck-bed', 'duck-roof', 'duck-pond']], 'duck-pond'],
        [['scene' => 'rain', 'choices' => ['girl-umbrella', 'girl-bike', 'girl-icecream']], 'girl-umbrella'],
        [['scene' => 'grandpa', 'choices' => ['mom-reads', 'grandpa-reads', 'baby-reads']], 'grandpa-reads'],
        [['scene' => 'melon', 'choices' => ['boy-bread', 'boy-egg', 'boy-melon']], 'boy-melon'],
    ];
}

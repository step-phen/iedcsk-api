<?php

namespace Database\Seeders;

// "Story Order" digital activity for Reading, Q4 Week 5 Monday; run with: php artisan db:seed --class=StoryOrderSeeder
class StoryOrderSeeder extends ReadingQuarterFourGameSeeder
{
    protected const CODE = 'RD-Q4W5-01';

    protected const NAME = 'Story Order';

    protected const DESCRIPTION = 'Read the short story, then arrange three event pictures in order.';

    protected const INSTRUCTIONS = 'Read the story, then tap the pictures in order: beginning, middle, and end.';

    protected const CHECKLIST_ITEM = 'Arranges the beginning, middle, and end of a short story in order';

    protected const WEEK = 5;

    protected const DAY = 1;

    // "events" are in story order; "choices" shuffles them, and the answer is the event ids in order joined by commas.
    protected const ROUNDS = [
        [['sentences' => ['Sam plants a seed.', 'A sprout pops up.', 'A big flower grows.'], 'events' => ['seed', 'sprout', 'flower'], 'choices' => ['flower', 'seed', 'sprout']], 'seed,sprout,flower'],
        [['sentences' => ['Mom gets an egg.', 'Mom cooks the egg.', 'Pam eats the egg.'], 'events' => ['egg', 'cook', 'eat'], 'choices' => ['cook', 'eat', 'egg']], 'egg,cook,eat'],
        [['sentences' => ['The sun comes up.', 'Tom plays in the sun.', 'The moon comes out.'], 'events' => ['sunrise', 'play', 'moon'], 'choices' => ['moon', 'sunrise', 'play']], 'sunrise,play,moon'],
        [['sentences' => ['Ben gets an ice cream.', 'The sun is hot.', 'The ice cream melts.'], 'events' => ['icecream', 'hot', 'melt'], 'choices' => ['melt', 'hot', 'icecream']], 'icecream,hot,melt'],
        [['sentences' => ['Kim sees dark clouds.', 'Rain falls on Kim.', 'Kim jumps in a puddle.'], 'events' => ['clouds', 'rain', 'puddle'], 'choices' => ['puddle', 'clouds', 'rain']], 'clouds,rain,puddle'],
    ];
}

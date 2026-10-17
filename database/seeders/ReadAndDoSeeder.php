<?php

namespace Database\Seeders;

// "Read and Do" digital activity for Reading, Q4 Week 6 Wednesday; run with: php artisan db:seed --class=ReadAndDoSeeder
class ReadAndDoSeeder extends ReadingQuarterFourGameSeeder
{
    protected const CODE = 'RD-Q4W6-02';

    protected const NAME = 'Read and Do';

    protected const DESCRIPTION = 'Watch the demonstration, then read a taught-word sentence and perform its action.';

    protected const INSTRUCTIONS = 'Watch the wizard, then read each spell and do it by tapping the right thing.';

    protected const CHECKLIST_ITEM = 'Reads a simple sentence and responds with its action';

    protected const WEEK = 6;

    protected const DAY = 3;

    // "action" picks the animation the learner's tap performs; the other things differ by one sound or one word.
    protected const ROUNDS = [
        [['text' => 'Pat the cat.', 'action' => 'pat', 'choices' => ['hat', 'bat', 'cat']], 'cat'],
        [['text' => 'Tap the big pig.', 'action' => 'tap', 'choices' => ['big-pig', 'small-pig', 'big-dog']], 'big-pig'],
        [['text' => 'Hug the pup.', 'action' => 'hug', 'choices' => ['cup', 'pup', 'pig']], 'pup'],
        [['text' => 'Zip the bag.', 'action' => 'zip', 'choices' => ['bug', 'bed', 'bag']], 'bag'],
        [['text' => 'Hop the bug to the log.', 'action' => 'hop', 'choices' => ['bug', 'hen', 'bus']], 'bug'],
    ];
}

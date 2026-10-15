<?php

namespace Database\Seeders;

// "Story Picture Match" digital activity for Reading, Q4 Week 2 Monday; run with: php artisan db:seed --class=StoryPictureMatchSeeder
class StoryPictureMatchSeeder extends ReadingQuarterFourGameSeeder
{
    protected const CODE = 'RD-Q4W2-01';

    protected const NAME = 'Story Picture Match';

    protected const DESCRIPTION = 'Read a short story, then tap the picture for each event.';

    protected const INSTRUCTIONS = 'Read the short story with optional audio support, then choose pictures that match its events.';

    protected const CHECKLIST_ITEM = 'Identifies the main events of a short decodable story';

    protected const WEEK = 2;

    protected const DAY = 1;

    // The rounds' sentences, in order, make up the story the game shows.
    protected const ROUNDS = [
        [['sentence' => 'Dan has a red cap.', 'choices' => ['cap', 'hat', 'sock']], 'cap'],
        [['sentence' => 'Dan gets on the bus.', 'choices' => ['bike', 'bus', 'boat']], 'bus'],
        [['sentence' => 'Dan sees a big pig.', 'choices' => ['dog', 'hen', 'pig']], 'pig'],
        [['sentence' => 'Dan has a hot dog.', 'choices' => ['hotdog', 'apple', 'cake']], 'hotdog'],
        [['sentence' => 'Dan naps in bed.', 'choices' => ['tub', 'tent', 'bed']], 'bed'],
    ];
}

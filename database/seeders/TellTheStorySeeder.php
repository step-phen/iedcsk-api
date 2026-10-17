<?php

namespace Database\Seeders;

// "Tell the Story" digital activity for Reading, Q4 Week 5 Friday; run with: php artisan db:seed --class=TellTheStorySeeder
class TellTheStorySeeder extends ReadingQuarterFourGameSeeder
{
    protected const CODE = 'RD-Q4W5-03';

    protected const NAME = 'Tell the Story';

    protected const DESCRIPTION = 'Read a familiar short story, then retell its main events.';

    protected const INSTRUCTIONS = 'Read the story, then retell it by tapping the picture of what happened first, next, and last.';

    protected const CHECKLIST_ITEM = 'Retells the main events of a familiar short story using pictures';

    protected const WEEK = 5;

    protected const DAY = 5;

    // The Tom story from Comprehension Check (Week 4), so it is familiar; each round asks for the next event in order.
    private const STORY = ['Tom has a red bus.', 'Tom and Dad get on the bus.', 'The bus goes to the farm.', 'Tom sees a pig.', 'The pig is in a tub.'];

    protected const ROUNDS = [
        [['story' => self::STORY, 'step' => 1, 'choices' => ['farm', 'bus', 'pig']], 'bus'],
        [['story' => self::STORY, 'step' => 2, 'choices' => ['geton', 'tub', 'farm']], 'geton'],
        [['story' => self::STORY, 'step' => 3, 'choices' => ['pig', 'bus', 'farm']], 'farm'],
        [['story' => self::STORY, 'step' => 4, 'choices' => ['tub', 'pig', 'geton']], 'pig'],
        [['story' => self::STORY, 'step' => 5, 'choices' => ['farm', 'pig', 'tub']], 'tub'],
    ];
}

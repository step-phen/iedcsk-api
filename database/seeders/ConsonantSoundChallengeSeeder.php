<?php

namespace Database\Seeders;

// "Consonant Sound Challenge" digital activity for Reading, Q4 Week 3 Monday; run with: php artisan db:seed --class=ConsonantSoundChallengeSeeder
class ConsonantSoundChallengeSeeder extends ReadingQuarterFourGameSeeder
{
    protected const CODE = 'RD-Q4W3-01';

    protected const NAME = 'Consonant Sound Challenge';

    protected const DESCRIPTION = 'Choose the consonant representing the beginning or ending sound requested.';

    protected const INSTRUCTIONS = 'Look at the picture, listen for the beginning or ending sound, then tap the hoop with that consonant.';

    protected const CHECKLIST_ITEM = 'Identifies the consonant for the beginning or ending sound of a familiar word';

    protected const WEEK = 3;

    protected const DAY = 1;

    // Decoy letters sound alike (b/d/p, n/m) so the learner must listen to the exact sound.
    protected const ROUNDS = [
        [['word' => 'bus', 'position' => 'beginning', 'choices' => ['d', 'b', 'p']], 'b'],
        [['word' => 'hen', 'position' => 'ending', 'choices' => ['n', 'm', 't']], 'n'],
        [['word' => 'fox', 'position' => 'beginning', 'choices' => ['v', 's', 'f']], 'f'],
        [['word' => 'web', 'position' => 'ending', 'choices' => ['d', 'b', 'p']], 'b'],
        [['word' => 'pig', 'position' => 'ending', 'choices' => ['k', 'd', 'g']], 'g'],
    ];
}

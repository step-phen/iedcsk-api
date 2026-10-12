<?php

namespace Database\Seeders;

// "Reading Final Exam" digital activity for Reading, Q2 Week 7 Friday; run with: php artisan db:seed --class=ReadingFinalExam2Seeder
class ReadingFinalExam2Seeder extends ReadingReview2Seeder
{
    protected const CODE = 'RD-Q2W7-03';

    protected const NAME = 'Reading Final Exam';

    protected const DAY = 5;

    protected const DESCRIPTION = 'Identify the consonants taught this quarter and match familiar pictures to their beginning sounds.';

    protected const INSTRUCTIONS = 'Identify taught consonants and match familiar pictures to beginning sounds, with spoken instructions.';

    protected const CHECKLIST_ITEM = 'Recognizes the letter forms and beginning sounds of the consonants taught in Quarter 2';

    // No C and K in the same round as picture or sort choices, since both make /k/.
    protected const TASKS = [
        ['letter', 'B', ['D', 'B', 'P'], 'B'],
        ['letter', 'H', ['H', 'N', 'K'], 'H'],
        ['letter', 'J', ['G', 'L', 'J'], 'J'],
        ['partner', 'G', ['g', 'j', 'p'], 'g'],
        ['partner', 'D', ['b', 'd', 'p'], 'd'],
        ['partner', 'L', ['f', 'k', 'l'], 'l'],
        ['picture', 'M', ['moon', 'nose', 'fish'], 'moon'],
        ['picture', 'C', ['goat', 'cat', 'bee'], 'cat'],
        ['picture', 'H', ['dog', 'pig', 'hat'], 'hat'],
        ['picture', 'N', ['nose', 'moon', 'juice'], 'nose'],
        ['sort', 'fish', ['H', 'F', 'P'], 'F'],
        ['sort', 'bee', ['D', 'P', 'B'], 'B'],
        ['sort', 'juice', ['J', 'G', 'D'], 'J'],
        ['sort', 'kite', ['K', 'G', 'L'], 'K'],
        ['sort', 'pig', ['M', 'B', 'P'], 'P'],
    ];
}

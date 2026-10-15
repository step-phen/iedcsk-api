<?php

namespace Database\Seeders;

// "Reading Final Exam" digital activity for Reading, Q3 Week 7 Friday; run with: php artisan db:seed --class=ReadingFinalExam3Seeder
class ReadingFinalExam3Seeder extends ReadingQuarterThreeGameSeeder
{
    protected const CODE = 'RD-Q3W7-03';

    protected const NAME = 'Reading Final Exam';

    protected const DESCRIPTION = 'Identify taught sounds and match taught CVC words with pictures, using spoken task instructions.';

    protected const INSTRUCTIONS = 'Follow each spoken instruction to identify taught consonant sounds and match short-a, short-e and short-i words to pictures.';

    protected const CHECKLIST_ITEM = 'Recognizes the consonants Qq to Zz and reads short-a, short-e and short-i CVC words';

    protected const WEEK = 7;

    protected const DAY = 5;

    // No two letters that sound alike (s/z, v/w, q/w, w/y) share a sound round, so each one has a single clear answer.
    protected const ROUNDS = [
        [['type' => 'hear', 'target' => 'r', 'choices' => ['r', 'v', 't']], 'r'],
        [['type' => 'word', 'target' => 'cat', 'choices' => ['cat', 'hat', 'pig']], 'cat'],
        [['type' => 'first', 'target' => 'tent', 'choices' => ['y', 'r', 't']], 't'],
        [['type' => 'word', 'target' => 'net', 'choices' => ['pen', 'net', 'map']], 'net'],
        [['type' => 'hear', 'target' => 's', 'choices' => ['t', 'w', 's']], 's'],
        [['type' => 'word', 'target' => 'pin', 'choices' => ['pig', 'bin', 'pin']], 'pin'],
        [['type' => 'last', 'target' => 'box', 'choices' => ['x', 't', 'y']], 'x'],
        [['type' => 'word', 'target' => 'bag', 'choices' => ['bag', 'bed', 'van']], 'bag'],
        [['type' => 'first', 'target' => 'watch', 'choices' => ['r', 'w', 't']], 'w'],
        [['type' => 'word', 'target' => 'hen', 'choices' => ['hat', 'ten', 'hen']], 'hen'],
        [['type' => 'hear', 'target' => 'z', 'choices' => ['z', 'r', 'y']], 'z'],
        [['type' => 'word', 'target' => 'lip', 'choices' => ['leg', 'lip', 'six']], 'lip'],
        [['type' => 'first', 'target' => 'van', 'choices' => ['t', 'y', 'v']], 'v'],
        [['type' => 'word', 'target' => 'map', 'choices' => ['map', 'cap', 'web']], 'map'],
        [['type' => 'last', 'target' => 'six', 'choices' => ['n', 'x', 'g']], 'x'],
    ];
}

<?php

namespace Database\Seeders;

// "Conversation Match" digital activity for Language, Q4 Week 6 Wednesday; run with: php artisan db:seed --class=ConversationMatchSeeder
class ConversationMatchSeeder extends LanguageQuarterFourGameSeeder
{
    protected const CODE = 'LG-Q4W6-02';

    protected const NAME = 'Conversation Match';

    protected const DESCRIPTION = 'Listen to a speaker, then select a suitable spoken reply.';

    protected const INSTRUCTIONS = 'Listen to the caller, hear each reply, then tap the reply that makes sense.';

    protected const CHECKLIST_ITEM = 'Chooses a suitable reply in a short conversation';

    protected const WEEK = 6;

    protected const DAY = 3;

    // The game holds who calls, what they say and the reply sentences; each answer is the reply that fits the call.
    protected const ROUNDS = [
        [['call' => 'how-are-you', 'choices' => ['fine', 'cat-bed', 'red-ball']], 'fine'],
        [['call' => 'play', 'choices' => ['blue-shoes', 'lets-play', 'good-night']], 'lets-play'],
        [['call' => 'gift', 'choices' => ['five', 'cat-bed', 'thank-you']], 'thank-you'],
        [['call' => 'eat', 'choices' => ['rice-fish', 'blue-shoes', 'good-night']], 'rice-fish'],
        [['call' => 'bag', 'choices' => ['red-ball', 'bag-chair', 'five']], 'bag-chair'],
    ];
}

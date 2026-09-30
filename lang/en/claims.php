<?php

return [
    'keys' => [
        'method' => '🎯 Received by: :method',
        'back' => '🔙 Back to the prize',
    ],
    'admin' => [
        'pick' => '🎯 How should users receive this prize? Only users who join from now on get the new way.',
        'updated' => '✅ Claim method updated.',
        'unknown' => 'This claim method is not available any more.',
    ],
    'click' => [
        'label' => '👆 One tap',
        'describe' => 'one tap',
    ],
    'dice' => [
        'label' => '🎲 Dice game',
        'describe' => 'dice game: win on :numbers · :tries try(s)',
        'play' => '🎲 Play for: :prize',
        'inProgress' => '🎲 :prize — throw your dice in the reply below.',
        'prompt' => "🎲 Throw the dice for <b>:prize</b>!\r\n\r\nSend a 🎲 as a reply to this message. You win on: :numbers.\r\nYou have :tries try(s).",
        'playing' => 'You are already playing for this gift. Reply to the message below with a 🎲.',
        'notStarted' => 'We could not start the game. Try again.',
        'won' => '🎉 :value — that is a winner!',
        'miss' => '🎲 :value — not a winning number. :left try(s) left, throw again!',
        'lastMiss' => '🎲 :value — not a winning number, and that was your last try.',
        'ambiguous' => 'Reply to the message of the prize you are playing for with your 🎲.',
        'rejected' => [
            'forwarded' => '🚫 Forwarded dice do not count. Throw your own 🎲.',
            'wrongEmoji' => '🚫 Only the 🎲 dice counts.',
            'stale' => '🚫 That dice was thrown before the game started. Throw a new 🎲.',
        ],
        'wizard' => [
            'lockLabel' => 'Setting up the dice game…',
            'summary' => '🎲 Review the dice game',
            'finished' => '🎉 Dice game saved!',
            'fields' => [
                'numbers' => [
                    'label' => 'Winning numbers',
                    'prompt' => '🎲 Which numbers win? Send them from 1 to 6, separated by commas (for example 2,4,6 for even):',
                ],
                'tries' => [
                    'label' => 'Tries',
                    'prompt' => '🔁 How many throws does a user get? (1 to 10)',
                ],
            ],
            'errors' => [
                'numbers' => 'Send numbers from 1 to 6 separated by commas, for example 2,4,6.',
                'tries' => 'Send a whole number from 1 to :max.',
                'everything' => 'Every number would win, so there is no game. Leave at least one out or use the one-tap method.',
            ],
        ],
    ],
];

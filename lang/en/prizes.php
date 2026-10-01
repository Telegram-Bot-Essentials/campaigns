<?php

return [
    'notify' => [
        'title' => '🎁 A welcome gift for you!',
        'claimable' => '🎁 :prize — tap below to claim it.',
        'received' => '✅ :prize is yours!',
        'lost' => '😕 You didn\'t win :prize this time.',
        'failed' => '⚠️ We couldn\'t give you :prize right now. Support has been notified.',
    ],
    'keys' => [
        'claim' => '🎁 Claim: :prize',
        'prizes' => '🎁 Prizes',
        'add' => '➕ Add a prize',
        'enable' => '✅ Enable',
        'disable' => '🚫 Disable',
        'editCap' => '✏️ Edit limit',
        'retry' => '🔁 Retry failed (:count)',
    ],
    'claim' => [
        'notYours' => 'This gift belongs to someone else.',
        'granted' => '🎉 Done! :prize',
        'failed' => '⚠️ We could not give you the gift right now. Support has been notified.',
        'already' => 'This gift was already claimed.',
        'unavailable' => 'This way of getting the gift isn\'t available any more.',
    ],
    'admin' => [
        'empty' => '🎁 <b>:name</b> has no prizes yet. New users who join through it get nothing but the welcome.',
        'list' => '🎁 <b>:name</b> prizes — pick one to manage.',
        'label' => ':badge :prize · :used/:cap',
        'noTypes' => '😕 No prize types are installed.',
        'pickType' => '🎁 What kind of prize?',
        'unknownType' => 'This prize type isn\'t available any more.',
        'updated' => '✅ Prize updated.',
        'retried' => '🔁 Retried the failed prizes.',
        'show' => '🎁 <b>:prize</b>'
            ."\r\n"
            ."\r\n📌 Status: :status"
            ."\r\n🎯 Received by: :method"
            ."\r\n🔢 Limit: :cap (taken: :used)"
            ."\r\n"
            ."\r\n✅ Given: :granted"
            ."\r\n⏳ Waiting to be claimed: :pending"
            ."\r\n⚠️ Failed: :failed"
            ."\r\n😕 Didn't win: :lost",
        'cap' => [
            'label' => 'Prize limit',
            'prompt' => '🔢 How many users can get this prize in total? Send 0 for no limit:',
        ],
    ],
    'wallet' => [
        'label' => '💰 Wallet credit',
        'describe' => ':amount wallet credit',
        'wizard' => [
            'lockLabel' => 'Adding wallet credit prize…',
            'summary' => '💰 Review the prize',
            'finished' => '🎉 Prize added!',
            'fields' => [
                'amount' => [
                    'label' => 'Amount',
                    'prompt' => '💰 How much wallet credit should each new user get?',
                ],
            ],
        ],
    ],
];

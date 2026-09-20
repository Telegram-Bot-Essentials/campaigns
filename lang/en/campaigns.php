<?php

return [
    'main' => [
        'text' => [
            'list' => '📣 Campaigns — pick one to manage.',
            'empty' => '😕 No campaigns yet.',
            'listLabel' => ':badge :name · :joined joined',
            'show' => '📣 Campaign <b>:name</b>'
                ."\r\n"
                ."\r\n🔗 Link:"
                ."\r\n<code>:link</code>"
                ."\r\n"
                ."\r\n📌 Status: :status"
                ."\r\n📅 Expires: :expiresAt"
                ."\r\n"
                ."\r\n📊 Stats"
                ."\r\n:stats"
                ."\r\n"
                ."\r\n⚙️ Choose an action below 👇",
            'confirmDelete' => 'Delete the campaign <b>:name</b>? Users it already brought in stay attributed to it, and its link will say the offer has ended.',
            'qr' => '🧩 QR code for <b>:name</b>'
                ."\r\n"
                ."\r\n<code>:link</code>",
        ],
        'keys' => [
            'create' => '➕ New campaign',
            'enable' => '✅ Enable',
            'disable' => '🚫 Disable',
            'qr' => '🧩 QR code',
            'editName' => '✏️ Edit name',
            'editExpiry' => '✏️ Edit expiry',
            'clearExpiry' => '↩️ Clear expiry',
            'delete' => '🗑️ Delete',
            'backToList' => '🔙 Back to campaigns',
            'backToCampaign' => '🔙 Back to campaign',
        ],
        'answers' => [
            'enabled' => '✅ Campaign enabled.',
            'disabled' => '🚫 Campaign disabled.',
            'updated' => '✅ Campaign updated.',
            'deleted' => '🗑️ Campaign deleted.',
            'qrSent' => '🧩 QR code sent.',
        ],
        'stats' => [
            'joined' => '👥 Joined: :count',
            'misses' => '🚫 Turned away: :count (:breakdown)',
            'paidUsers' => '💳 Paying users: :count',
            'revenue' => '💰 Revenue: :amount',
        ],
        'reasons' => [
            'unknown' => 'unknown link',
            'deleted' => 'deleted',
            'disabled' => 'disabled',
            'expired' => 'expired',
            'existing_user' => 'existing users',
        ],
        'never' => 'Never',
        'enabled' => '✅ Enabled',
        'disabled' => '🚫 Disabled',
        'expired' => '⌛ Expired',
    ],

    'wizard' => [
        'lockLabel' => 'Creating campaign…',
        'waitingPage' => '⌛ Waiting for page number.',
        'enterPage' => '🔢 Enter page number:',
        'pageLoaded' => '📄 Page :page loaded.',
        'finished' => '🎉 Campaign created!',
        'summary' => '📣 Review the new campaign',
        'fields' => [
            'name' => [
                'label' => 'Name',
                'prompt' => '📣 Send a name for the campaign (only you see it):',
                'editPrompt' => '✏️ Send the new campaign name:',
            ],
            'days' => [
                'label' => 'Expiry',
                'prompt' => '📅 Enter how many days from now the link should stop working, or tap Skip for never:',
            ],
            'expiry' => [
                'label' => 'Expiry',
                'editPrompt' => '📅 Enter how many days from now the link should stop working:',
            ],
        ],
    ],

    'reply' => [
        'keys' => [
            'campaigns' => [
                'text' => '📣 Campaigns',
                'response' => '📋 Campaign manager opened successfully.',
            ],
        ],
    ],
];

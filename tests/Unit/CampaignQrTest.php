<?php

use TelegramBotEssentials\Campaigns\Services\CampaignQr;

it('renders a campaign QR as PNG, whichever major of the library is installed', function () {
    expect(substr(CampaignQr::png('https://example.com'), 0, 4))->toBe("\x89PNG");
});

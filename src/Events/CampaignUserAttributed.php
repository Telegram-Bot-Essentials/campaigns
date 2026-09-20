<?php

namespace TelegramBotEssentials\Campaigns\Events;

use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Campaigns\Models\CampaignAttribution;
use TelegramBotEssentials\Essence\Events\BotEvent;
use TelegramBotEssentials\Essence\Support\WebhookContext;

/**
 * A brand-new user was just attributed to a campaign. This is the seam
 * for prizes: a package that wants to reward the join listens for this
 * event. Only new users are ever attributed, and the attribution row is
 * unique per user, so a listener fires at most once per user.
 */
class CampaignUserAttributed extends BotEvent
{
    public function __construct(
        WebhookContext $context,
        public readonly Campaign $campaign,
        public readonly CampaignAttribution $attribution,
    ) {
        parent::__construct($context);
    }
}

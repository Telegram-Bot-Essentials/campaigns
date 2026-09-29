<?php

namespace TelegramBotEssentials\Campaigns\Services;

use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Campaigns\Models\CampaignPrize;
use TelegramBotEssentials\Essence\Exceptions\LogicException;

class CampaignPrizes
{
    /**
     * Attaches a configured prize to a campaign. A prize type's config form
     * calls this once the admin confirms.
     *
     * @param  array<string, mixed>  $config
     */
    public static function attach(Campaign $campaign, string $type, array $config, ?int $maxGrants = null): CampaignPrize
    {
        if (prizeTypes()->get($type) === null) {
            throw new LogicException(sprintf('Prize type "%s" is not registered.', $type));
        }

        return CampaignPrize::create([
            'bot_id' => $campaign->bot_id,
            'campaign_id' => $campaign->id,
            'type' => $type,
            'config' => $config,
            'max_grants' => $maxGrants,
        ]);
    }
}

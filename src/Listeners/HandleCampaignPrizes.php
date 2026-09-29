<?php

namespace TelegramBotEssentials\Campaigns\Listeners;

use TelegramBotEssentials\Campaigns\Events\CampaignUserAttributed;
use TelegramBotEssentials\Campaigns\Services\PrizeGranting;
use Throwable;

class HandleCampaignPrizes
{
    public function __construct(private readonly PrizeGranting $granting) {}

    public function handle(CampaignUserAttributed $event): void
    {
        try {
            $this->granting->issue($event->attribution);
        } catch (Throwable $e) {
            // The user has joined; prizes are a bonus that must never break /start.
            tbeLog('campaigns')->error('Could not issue prizes for attribution #{attribution_id}: '.$e->getMessage(), [
                'attribution_id' => $event->attribution->id,
                'exception' => $e,
            ]);
        }
    }
}

<?php

namespace TelegramBotEssentials\Campaigns\Listeners;

use Illuminate\Database\UniqueConstraintViolationException;
use TelegramBotEssentials\Campaigns\Enums\MissReason;
use TelegramBotEssentials\Campaigns\Events\CampaignUserAttributed;
use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Campaigns\Models\CampaignAttribution;
use TelegramBotEssentials\Campaigns\Models\CampaignMiss;
use TelegramBotEssentials\Essence\Events\BotDeepLinkReceived;

class HandleCampaignDeepLink
{
    public function handle(BotDeepLinkReceived $event): void
    {
        if (! str_starts_with($event->payload, Campaign::PAYLOAD_PREFIX)) {
            return;
        }

        $code = substr($event->payload, strlen(Campaign::PAYLOAD_PREFIX));
        $campaign = Campaign::withTrashed()->where('code', $code)->first();

        if ($campaign === null) {
            $this->recordMiss($event->payload, null, MissReason::Unknown);

            return;
        }

        $reason = $this->missReason($campaign);

        if ($reason !== null) {
            $this->recordMiss($event->payload, $campaign, $reason);

            return;
        }

        $attribution = $this->attribute($campaign);

        if ($attribution === null) {
            return;
        }

        tbeLog('campaigns')->info('User attributed to campaign', [
            'campaign_id' => $campaign->id,
            'attribution_id' => $attribution->id,
        ]);

        botEventBus()->fire(new CampaignUserAttributed($event->context, $campaign, $attribution));
    }

    /**
     * Checks run from the most to the least fundamental problem with the
     * link itself, so a returning user opening an expired link is told the
     * link is dead rather than that they are not new.
     */
    private function missReason(Campaign $campaign): ?MissReason
    {
        return match (true) {
            $campaign->trashed() => MissReason::Deleted,
            ! $campaign->active => MissReason::Disabled,
            $campaign->isExpired() => MissReason::Expired,
            // Only a user created by this very /start request is new.
            ! wHook()->user()->wasRecentlyCreated => MissReason::ExistingUser,
            default => null,
        };
    }

    private function attribute(Campaign $campaign): ?CampaignAttribution
    {
        try {
            return CampaignAttribution::create([
                'bot_id' => wHook()->bot()->id,
                'bot_user_id' => wHook()->user()->id,
                'campaign_id' => $campaign->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            // unique(bot_id, bot_user_id): already attributed. First touch wins.
            return null;
        }
    }

    private function recordMiss(string $payload, ?Campaign $campaign, MissReason $reason): void
    {
        try {
            CampaignMiss::firstOrCreate(
                [
                    'bot_user_id' => wHook()->user()->id,
                    'payload' => $payload,
                    'reason' => $reason,
                ],
                [
                    'bot_id' => wHook()->bot()->id,
                    'campaign_id' => $campaign?->id,
                ],
            );
        } catch (UniqueConstraintViolationException) {
            // The same user hit the same dead link twice at once; one row is enough.
        }

        // The user is told every time, even when the miss row already existed.
        wHook()->api()->sendMessage([
            'chat_id' => wHook()->user()->telegramUser->peer_id,
            'text' => __('tbe-campaigns::campaign.misses.'.$reason->value),
            'reply_markup' => wHook()->user()->getKeyboard(),
        ]);
    }
}

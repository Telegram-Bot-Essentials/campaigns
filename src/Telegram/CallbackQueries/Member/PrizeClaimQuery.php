<?php

namespace TelegramBotEssentials\Campaigns\Telegram\CallbackQueries\Member;

use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramBotEssentials\Campaigns\Enums\PrizeGrantStatus;
use TelegramBotEssentials\Campaigns\Models\CampaignPrizeGrant;
use TelegramBotEssentials\Campaigns\Services\PrizeGranting;
use TelegramBotEssentials\Essence\Enums\Roles;
use TelegramBotEssentials\Essence\Telegram\CallbackQueries\CallbackQuery;

class PrizeClaimQuery extends CallbackQuery
{
    public const TYPE = 'CAMPAIGN_PRIZE';

    protected string $type = self::TYPE;

    protected int $perm = Roles::MEMBER->value;

    public function claim(CampaignPrizeGrant $grant): void
    {
        // A button forwarded to someone else must not hand them the prize.
        if ($grant->bot_user_id !== wHook()->user()->id) {
            $this->alert(__('tbe-campaigns::prizes.claim.notYours'));

            return;
        }

        $status = app(PrizeGranting::class)->claim($grant);

        $this->alert(match ($status) {
            PrizeGrantStatus::Granted => __('tbe-campaigns::prizes.claim.granted', ['prize' => $grant->prize->describe()]),
            PrizeGrantStatus::Failed => __('tbe-campaigns::prizes.claim.failed'),
            default => __('tbe-campaigns::prizes.claim.already'),
        });
    }

    private function alert(string $text): void
    {
        try {
            wHook()->api()->answerCallbackQuery([
                'callback_query_id' => wHook()->update()->callbackQuery?->id,
                'text' => $text,
                'show_alert' => true,
            ]);
        } catch (TelegramSDKException) {
        }
    }
}

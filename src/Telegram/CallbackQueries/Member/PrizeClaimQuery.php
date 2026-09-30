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

        $prize = $grant->prize->describe();

        // The message settles on the outcome and loses its button. Anything
        // else (another tap is mid-claim) leaves it as it is.
        $outcome = match ($status) {
            PrizeGrantStatus::Granted => __('tbe-campaigns::prizes.notify.received', ['prize' => $prize]),
            PrizeGrantStatus::Failed => __('tbe-campaigns::prizes.notify.failed', ['prize' => $prize]),
            default => null,
        };

        if ($outcome !== null) {
            $this->settle($outcome);
        } else {
            $this->alert(__('tbe-campaigns::prizes.claim.already'));
        }
    }

    /** Replaces the claim message's text and drops its inline keyboard. */
    private function settle(string $text): void
    {
        $message = wHook()->update()->callbackQuery?->message;

        try {
            wHook()->api()->editMessageText([
                'chat_id' => $message?->chat->id,
                'message_id' => $message?->messageId,
                'text' => __('tbe-campaigns::prizes.notify.title')."\r\n\r\n".$text,
            ]);
            wHook()->api()->answerCallbackQuery(['callback_query_id' => wHook()->update()->callbackQuery?->id]);
        } catch (TelegramSDKException) {
        }
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

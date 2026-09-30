<?php

namespace TelegramBotEssentials\Campaigns\Services;

use Telegram\Bot\Keyboard\Keyboard;
use TelegramBotEssentials\Campaigns\Enums\PrizeGrantStatus;
use TelegramBotEssentials\Campaigns\Models\CampaignPrizeGrant;
use TelegramBotEssentials\Campaigns\Telegram\CallbackQueries\Member\PrizeClaimQuery;
use Throwable;

/**
 * What the user reads about a grant: the message announcing it, and the same
 * message rewritten once its outcome is known. The announcement's id is kept
 * on the grant, so the outcome can be written from anywhere (a button tap, a
 * dice thrown as a message) without a callback query at hand.
 */
class PrizeMessages
{
    /** One message per prize, with the button its claim method asks for. */
    public function announce(CampaignPrizeGrant $grant): void
    {
        $prize = $grant->prize->describe();
        $label = claimMethods()->get($grant->method)?->buttonLabel($prize)
            ?? __('tbe-campaigns::prizes.keys.claim', ['prize' => $prize]);

        try {
            $sent = wHook()->api()->sendMessage([
                'chat_id' => $this->chatId($grant),
                'text' => __('tbe-campaigns::prizes.notify.title')."\r\n\r\n".__('tbe-campaigns::prizes.notify.claimable', ['prize' => $prize]),
                'reply_markup' => Keyboard::make()->inline()->row([
                    Keyboard::inlineButton([
                        'text' => $label,
                        'callback_data' => encodeCallback(PrizeClaimQuery::TYPE, 'claim', [$grant->id]),
                    ]),
                ]),
            ]);

            $grant->forceFill(['message_id' => $sent->messageId])->save();
        } catch (Throwable $e) {
            // The grant is recorded; a failed notification must not undo the join.
            tbeLog('campaigns')->error('Could not tell the user about prize grant #{grant_id}: '.$e->getMessage(), [
                'grant_id' => $grant->id,
                'exception' => $e,
            ]);
        }
    }

    /** Rewrites the announcement to say how it ended and drops its button. */
    public function settle(CampaignPrizeGrant $grant, PrizeGrantStatus $status): void
    {
        $prize = $grant->prize->describe();

        $outcome = match ($status) {
            PrizeGrantStatus::Granted => __('tbe-campaigns::prizes.notify.received', ['prize' => $prize]),
            PrizeGrantStatus::Failed => __('tbe-campaigns::prizes.notify.failed', ['prize' => $prize]),
            PrizeGrantStatus::Lost => __('tbe-campaigns::prizes.notify.lost', ['prize' => $prize]),
            default => null,
        };

        if ($outcome !== null) {
            $this->rewrite($grant, $outcome);
        }
    }

    /** Rewrites the announcement with $text and drops its button. */
    public function rewrite(CampaignPrizeGrant $grant, string $text): void
    {
        if ($grant->message_id === null) {
            return;
        }

        try {
            wHook()->api()->editMessageText([
                'chat_id' => $this->chatId($grant),
                'message_id' => $grant->message_id,
                'text' => __('tbe-campaigns::prizes.notify.title')."\r\n\r\n".$text,
            ]);
        } catch (Throwable $e) {
            tbeLog('campaigns')->warning('Could not update the message of prize grant #{grant_id}: '.$e->getMessage(), [
                'grant_id' => $grant->id,
            ]);
        }
    }

    private function chatId(CampaignPrizeGrant $grant): int
    {
        $peerId = $grant->botUser->telegramUser->peer_id;

        return is_numeric($peerId) ? (int) $peerId : 0;
    }
}

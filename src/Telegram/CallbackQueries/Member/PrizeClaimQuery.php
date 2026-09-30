<?php

namespace TelegramBotEssentials\Campaigns\Telegram\CallbackQueries\Member;

use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramBotEssentials\Campaigns\Models\CampaignPrizeGrant;
use TelegramBotEssentials\Essence\Enums\Roles;
use TelegramBotEssentials\Essence\Telegram\CallbackQueries\CallbackQuery;

class PrizeClaimQuery extends CallbackQuery
{
    public const TYPE = 'CAMPAIGN_PRIZE';

    protected string $type = self::TYPE;

    protected int $perm = Roles::MEMBER->value;

    /** The button under a prize message: hands over to the grant's claim method. */
    public function claim(CampaignPrizeGrant $grant): void
    {
        // A button forwarded to someone else must not hand them the prize.
        if ($grant->bot_user_id !== wHook()->user()->id) {
            $this->alert(__('tbe-campaigns::prizes.claim.notYours'));

            return;
        }

        $method = claimMethods()->get($grant->method);

        if ($method === null) {
            $this->alert(__('tbe-campaigns::prizes.claim.unavailable'));

            return;
        }

        // A grant issued before the message id was kept learns it from the tap.
        if ($grant->message_id === null) {
            $grant->forceFill(['message_id' => wHook()->update()->callbackQuery?->message?->messageId])->save();
        }

        $this->alert($method->start($grant), false);
    }

    private function alert(?string $text, bool $show = true): void
    {
        try {
            wHook()->api()->answerCallbackQuery(array_filter([
                'callback_query_id' => wHook()->update()->callbackQuery?->id,
                'text' => $text,
                'show_alert' => $text !== null && $show ? true : null,
            ]));
        } catch (TelegramSDKException) {
        }
    }
}

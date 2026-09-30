<?php

namespace TelegramBotEssentials\Campaigns\Telegram\StateAnswers\Member;

use Telegram\Bot\Objects\Message;
use TelegramBotEssentials\Campaigns\Claims\DiceGame;
use TelegramBotEssentials\Campaigns\Enums\PrizeGrantStatus;
use TelegramBotEssentials\Campaigns\Models\CampaignPrizeGrant;
use TelegramBotEssentials\Campaigns\Services\PrizeGranting;
use TelegramBotEssentials\Campaigns\Services\PrizeMessages;
use TelegramBotEssentials\Essence\Enums\Roles;
use TelegramBotEssentials\Essence\Telegram\StateAnswers\StateAnswer;

/**
 * The user is playing for a prize and the bot is waiting for their 🎲. It is
 * an ordinary essence state, so there is one game at a time, the cancel key
 * and the message's cancel button work, and any other action (a command, a
 * menu key, starting another game) ends it and puts the prize message back.
 * Cancelling keeps the throws already used.
 */
class DiceAnswer extends StateAnswer
{
    public const TYPE = 'CAMPAIGN_DICE';

    protected string $type = self::TYPE;

    protected int $perm = Roles::MEMBER->value;

    /** Text is allowed too, to tell the user what to send instead of "invalid request". */
    /** @var array<int, string> */
    protected array $allowedFields = ['dice', 'text'];

    public function throw(CampaignPrizeGrant $grant): void
    {
        $message = wHook()->update()->getMessage();

        if (! $message instanceof Message) {
            return;
        }

        if ($grant->bot_user_id !== wHook()->user()->id || $grant->status !== PrizeGrantStatus::Pending) {
            wHook()->user()->changeState();
            $this->say($message, __('tbe-campaigns::prizes.claim.already'));

            return;
        }

        if (! $message->has('dice')) {
            $this->say($message, __('tbe-campaigns::claims.dice.hint'));

            return;
        }

        $game = app(DiceGame::class);
        $rejection = $game->rejection($message, $grant);

        if ($rejection !== null) {
            $this->say($message, __('tbe-campaigns::claims.dice.rejected.'.$rejection));

            return;
        }

        $value = DiceGame::int(data_get($message->get('dice'), 'value'));
        [$outcome, $left] = $game->play($grant, DiceGame::int($message->get('message_id')), $value);

        match ($outcome) {
            'won' => $this->won($grant->refresh(), $message, $value),
            'miss' => $this->say($message, __('tbe-campaigns::claims.dice.miss', ['value' => $value, 'left' => $left])),
            'lost' => $this->lost($grant->refresh(), $message, $value),
            default => null,
        };
    }

    private function won(CampaignPrizeGrant $grant, Message $message, int $value): void
    {
        wHook()->user()->changeState();

        $status = app(PrizeGranting::class)->claim($grant);

        $this->say($message, __('tbe-campaigns::claims.dice.won', ['value' => $value]));

        if ($status === PrizeGrantStatus::Granted || $status === PrizeGrantStatus::Failed) {
            app(PrizeMessages::class)->settle($grant, $status);
        }
    }

    private function lost(CampaignPrizeGrant $grant, Message $message, int $value): void
    {
        if (! app(PrizeGranting::class)->forfeit($grant)) {
            return;
        }

        wHook()->user()->changeState();

        $this->say($message, __('tbe-campaigns::claims.dice.lastMiss', ['value' => $value]));
        app(PrizeMessages::class)->settle($grant, PrizeGrantStatus::Lost);
    }

    /** Replies to the throw, with the keyboard the user's state calls for. */
    private function say(Message $to, string $text): void
    {
        wHook()->api()->sendMessage([
            'chat_id' => wHook()->peerId(),
            'text' => $text,
            'reply_parameters' => ['message_id' => $to->get('message_id'), 'allow_sending_without_reply' => true],
            'reply_markup' => wHook()->user()->getKeyboard(),
        ]);
    }
}

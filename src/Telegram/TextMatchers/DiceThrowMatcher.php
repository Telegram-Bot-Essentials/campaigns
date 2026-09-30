<?php

namespace TelegramBotEssentials\Campaigns\Telegram\TextMatchers;

use Telegram\Bot\Objects\Message;
use TelegramBotEssentials\Campaigns\Claims\DiceGame;
use TelegramBotEssentials\Campaigns\Enums\PrizeGrantStatus;
use TelegramBotEssentials\Campaigns\Models\CampaignPrizeGrant;
use TelegramBotEssentials\Campaigns\Services\PrizeGranting;
use TelegramBotEssentials\Campaigns\Services\PrizeMessages;
use TelegramBotEssentials\Essence\Enums\Roles;
use TelegramBotEssentials\Essence\Telegram\TextMatchers\TextMatcher;

/**
 * Takes a 🎲 the user throws while one of their dice games is waiting. It runs
 * after reply keys and state answers, so it never takes a form answer, and it
 * only matches while a game is open, so a stray 🎲 gets the usual answer.
 *
 * Essence runs a matcher through `TextMatcherBus::handler()`, which cancels
 * whatever form or process the user had open; a throw ends it.
 */
class DiceThrowMatcher extends TextMatcher
{
    protected int $perm = Roles::MEMBER->value;

    public function matches(Message $message): bool
    {
        return $message->has('dice') && app(DiceGame::class)->open(wHook()->user())->isNotEmpty();
    }

    public function handle(): void
    {
        $game = app(DiceGame::class);
        $message = wHook()->update()->getMessage();

        if (! $message instanceof Message) {
            return;
        }

        $grant = $game->locate($game->open(wHook()->user()), $message);

        if ($grant === null) {
            $this->say($message, __('tbe-campaigns::claims.dice.ambiguous'));

            return;
        }

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
        $status = app(PrizeGranting::class)->claim($grant);

        $this->say($message, __('tbe-campaigns::claims.dice.won', ['value' => $value]));

        if ($status === PrizeGrantStatus::Granted || $status === PrizeGrantStatus::Failed) {
            app(PrizeMessages::class)->settle($grant, $status);
        }
    }

    private function lost(CampaignPrizeGrant $grant, Message $message, int $value): void
    {
        if (app(PrizeGranting::class)->forfeit($grant)) {
            $this->say($message, __('tbe-campaigns::claims.dice.lastMiss', ['value' => $value]));
            app(PrizeMessages::class)->settle($grant, PrizeGrantStatus::Lost);
        }
    }

    private function say(Message $to, string $text): void
    {
        wHook()->api()->sendMessage([
            'chat_id' => wHook()->peerId(),
            'text' => $text,
            'reply_parameters' => ['message_id' => $to->get('message_id'), 'allow_sending_without_reply' => true],
        ]);
    }
}

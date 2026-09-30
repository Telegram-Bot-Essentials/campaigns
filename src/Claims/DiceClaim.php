<?php

namespace TelegramBotEssentials\Campaigns\Claims;

use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramBotEssentials\Campaigns\Contracts\ClaimMethod;
use TelegramBotEssentials\Campaigns\Enums\PrizeGrantStatus;
use TelegramBotEssentials\Campaigns\Models\CampaignPrizeGrant;
use TelegramBotEssentials\Campaigns\Telegram\Forms\Claims\DiceClaimForm;
use TelegramBotEssentials\Campaigns\Telegram\StateAnswers\Member\DiceAnswer;
use TelegramBotEssentials\Essence\Models\MessageMeta;
use TelegramBotEssentials\Essence\Telegram\StateAnswers\StateAnswer;

/**
 * The dice game. The user taps to play, the bot asks for a 🎲, and the prize
 * is theirs if the number they throw is one the admin picked. The admin also
 * sets how many throws they get. The game is an essence state (`DiceAnswer`),
 * so only one runs at a time and it can be cancelled like any other flow.
 *
 * Config: `numbers` (list of 1-6 that win) and `tries` (throws allowed).
 */
class DiceClaim implements ClaimMethod
{
    public const KEY = 'dice';

    public const MAX_TRIES = 10;

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('tbe-campaigns::claims.dice.label');
    }

    public function describe(array $config): string
    {
        return __('tbe-campaigns::claims.dice.describe', [
            'numbers' => implode(', ', self::numbers($config)),
            'tries' => self::tries($config),
        ]);
    }

    public function configForm(): ?string
    {
        return DiceClaimForm::class;
    }

    public function defaultConfig(): array
    {
        return ['numbers' => [2, 4, 6], 'tries' => 1];
    }

    public function buttonLabel(string $prize): string
    {
        return __('tbe-campaigns::claims.dice.play', ['prize' => $prize]);
    }

    /**
     * Opens the game the way any essence flow opens one: the prize message is
     * locked with a cancel button, the user's state is set, and the bot asks
     * for a 🎲. Whatever the user was in the middle of, including another
     * game, is cancelled first, so only one game is open at a time.
     */
    public function start(CampaignPrizeGrant $grant): ?string
    {
        if ($grant->status !== PrizeGrantStatus::Pending) {
            return __('tbe-campaigns::prizes.claim.already');
        }

        $this->cancelOpenProcess();

        $config = $grant->method_config ?? [];

        $messageMeta = MessageMeta::makeWithCurrentMessage();
        $messageMeta->cancelableLockAction(__('tbe-campaigns::claims.dice.lockLabel'));

        wHook()->user()->changeState(encodeAnswerState(DiceAnswer::TYPE, 'throw', [
            'grant' => $grant->id,
            'message_meta' => $messageMeta->id,
        ]));

        try {
            $prompt = wHook()->api()->sendMessage([
                'chat_id' => wHook()->peerId(),
                'text' => __('tbe-campaigns::claims.dice.prompt', [
                    'prize' => e($grant->prize->describe()),
                    'numbers' => implode(', ', self::numbers($config)),
                    'tries' => self::tries($config) - $grant->plays,
                ]),
                'parse_mode' => 'HTML',
                'reply_markup' => wHook()->user()->getKeyboard(),
            ]);
        } catch (TelegramSDKException) {
            // Nothing was asked, so leave the user where they were.
            wHook()->user()->changeState();
            $messageMeta->revertAction();

            return __('tbe-campaigns::claims.dice.notStarted');
        }

        // A throw must come after this message: ids only grow within a chat.
        $grant->forceFill(['prompt_message_id' => $prompt->messageId])->save();

        return null;
    }

    /**
     * Ends whatever the user has open, as essence does when a command or a
     * menu key arrives: the state answer behind it is told to cancel (which
     * puts its message back) and the state is cleared. Essence only does this
     * for messages, not for a button tap, so it is done here.
     */
    private function cancelOpenProcess(): void
    {
        $state = wHook()->requestState();

        if (! is_string($state) || $state === '') {
            return;
        }

        $decoded = decodeAnswerState($state);
        $open = stateAnswerBus()->getStateAnswerTypes()[$decoded['type'] ?? ''] ?? null;

        $answer = is_object($open) ? app($open::class) : null;

        if ($answer instanceof StateAnswer) {
            $answer->setParams($decoded['params']);
            $answer->setMethod('cancel');
            $answer->cancel();
        }

        wHook()->user()->changeState();
    }

    /** Persian and Arabic-Indic digits and the Persian comma as their ASCII forms, as an admin types them. */
    public static function latin(string $text): string
    {
        return strtr($text, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '،' => ',', '٬' => ',',
        ]);
    }

    /**
     * The winning numbers of a config, cleaned: whole numbers 1-6, no repeats.
     *
     * @param  array<string, mixed>  $config
     * @return list<int>
     */
    public static function numbers(array $config): array
    {
        $numbers = [];

        foreach ((array) ($config['numbers'] ?? []) as $number) {
            if (is_numeric($number) && (int) $number >= 1 && (int) $number <= 6) {
                $numbers[(int) $number] = (int) $number;
            }
        }

        sort($numbers);

        return $numbers;
    }

    /** @param  array<string, mixed>  $config */
    public static function tries(array $config): int
    {
        $tries = $config['tries'] ?? 1;

        return max(1, min(self::MAX_TRIES, is_numeric($tries) ? (int) $tries : 1));
    }
}

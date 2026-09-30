<?php

namespace TelegramBotEssentials\Campaigns\Claims;

use Illuminate\Support\Facades\DB;
use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramBotEssentials\Campaigns\Contracts\ClaimMethod;
use TelegramBotEssentials\Campaigns\Enums\PrizeGrantStatus;
use TelegramBotEssentials\Campaigns\Models\CampaignPrizeGrant;
use TelegramBotEssentials\Campaigns\Services\PrizeMessages;
use TelegramBotEssentials\Campaigns\Telegram\Forms\Claims\DiceClaimForm;

/**
 * The dice game. The user taps to play, the bot asks for a 🎲, and the prize
 * is theirs if the number they throw is one the admin picked. The admin also
 * sets how many throws they get. The throw itself is handled by
 * `DiceThrowMatcher`, which keeps no per-user state: each throw is tied to
 * its prize by the message it replies to, so several prizes can be played at
 * the same time.
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
     * Opens the game: the bot asks for a dice as a reply, and the announcement
     * loses its button. The grant stays locked while the prompt goes out, on
     * purpose: a double tap must wait and see the prompt, not send a second one.
     */
    public function start(CampaignPrizeGrant $grant): ?string
    {
        $alert = DB::transaction(function () use ($grant): ?string {
            $locked = CampaignPrizeGrant::query()->whereKey($grant->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== PrizeGrantStatus::Pending) {
                return __('tbe-campaigns::prizes.claim.already');
            }

            if ($locked->prompt_message_id !== null) {
                return __('tbe-campaigns::claims.dice.playing');
            }

            $config = $locked->method_config ?? [];

            try {
                $prompt = wHook()->api()->sendMessage(array_filter([
                    'chat_id' => $locked->botUser->telegramUser->peer_id,
                    'text' => __('tbe-campaigns::claims.dice.prompt', [
                        'prize' => e($locked->prize->describe()),
                        'numbers' => implode(', ', self::numbers($config)),
                        'tries' => self::tries($config),
                    ]),
                    'parse_mode' => 'HTML',
                    'reply_parameters' => $locked->message_id === null ? null : ['message_id' => $locked->message_id, 'allow_sending_without_reply' => true],
                ]));
            } catch (TelegramSDKException) {
                return __('tbe-campaigns::claims.dice.notStarted');
            }

            $locked->forceFill(['prompt_message_id' => $prompt->messageId])->save();

            return null;
        });

        if ($alert === null) {
            app(PrizeMessages::class)->rewrite($grant, __('tbe-campaigns::claims.dice.inProgress', ['prize' => $grant->prize->describe()]));
        }

        return $alert;
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

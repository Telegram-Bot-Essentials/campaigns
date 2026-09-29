<?php

declare(strict_types=1);

namespace TelegramBotEssentials\Campaigns\Tests\Support;

use RuntimeException;
use TelegramBotEssentials\Campaigns\Contracts\PrizeType;
use TelegramBotEssentials\Campaigns\Models\CampaignPrizeGrant;
use TelegramBotEssentials\Campaigns\Telegram\Forms\Prizes\WalletPrizeForm;
use TelegramBotEssentials\Essence\Models\BotUser;

/** A prize type that records what it was asked to do and can be told to fail. */
class RecordingPrize implements PrizeType
{
    /** @var list<int> ids of the users it handed a prize to */
    public static array $granted = [];

    public static bool $fail = false;

    public static bool $requiresClaim = true;

    public static function reset(): void
    {
        self::$granted = [];
        self::$fail = false;
        self::$requiresClaim = true;
    }

    public function key(): string
    {
        return 'recording';
    }

    public function label(): string
    {
        return 'Recording';
    }

    public function describe(array $config): string
    {
        return 'a recorded prize';
    }

    public function configForm(): string
    {
        return WalletPrizeForm::class;
    }

    public function requiresClaim(): bool
    {
        return self::$requiresClaim;
    }

    public function grant(BotUser $user, array $config, CampaignPrizeGrant $grant): void
    {
        if (self::$fail) {
            throw new RuntimeException('panel is down');
        }

        self::$granted[] = $user->id;
    }
}

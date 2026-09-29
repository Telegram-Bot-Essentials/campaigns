<?php

namespace TelegramBotEssentials\Campaigns\Prizes;

use TelegramBotEssentials\Campaigns\Contracts\PrizeType;
use TelegramBotEssentials\Campaigns\Models\CampaignPrizeGrant;
use TelegramBotEssentials\Campaigns\Telegram\Forms\Prizes\WalletPrizeForm;
use TelegramBotEssentials\Essence\Models\BotUser;

/**
 * Wallet credit for joining. Registered only when user-wallet is installed.
 * Handed over the moment the user joins: there is nothing to claim.
 */
class WalletCreditPrize implements PrizeType
{
    public const KEY = 'wallet';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('tbe-campaigns::prizes.wallet.label');
    }

    public function describe(array $config): string
    {
        $amount = $config['amount'] ?? 0;

        return __('tbe-campaigns::prizes.wallet.describe', [
            'amount' => currency()->priceFormat(is_scalar($amount) ? (string) $amount : '0'),
        ]);
    }

    public function configForm(): string
    {
        return WalletPrizeForm::class;
    }

    public function requiresClaim(): bool
    {
        return false;
    }

    public function grant(BotUser $user, array $config, CampaignPrizeGrant $grant): void
    {
        $amount = $config['amount'] ?? '0';

        wallet()->adjustBalance(is_scalar($amount) ? (string) $amount : '0');
    }
}

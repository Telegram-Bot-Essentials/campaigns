<?php

namespace TelegramBotEssentials\Campaigns\Prizes;

use Filament\Support\Icons\Heroicon;
use TelegramBotEssentials\Campaigns\Contracts\PrizeType;
use TelegramBotEssentials\Campaigns\Models\CampaignPrizeGrant;
use TelegramBotEssentials\Campaigns\Telegram\Forms\Prizes\WalletPrizeForm;
use TelegramBotEssentials\Essence\Contracts\Labelled;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Essence\Support\Label;

/**
 * Wallet credit for joining. Registered only when user-wallet is installed.
 * Handed over the moment the user joins: there is nothing to claim.
 */
class WalletCreditPrize implements Labelled, PrizeType
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

    public function labelled(): Label
    {
        return new Label($this->label(), '💰', Heroicon::OutlinedWallet);
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

    public function grant(BotUser $user, array $config, CampaignPrizeGrant $grant): void
    {
        $amount = $config['amount'] ?? '0';

        wallet()->adjustBalance(is_scalar($amount) ? (string) $amount : '0');
    }
}

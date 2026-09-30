<?php

namespace TelegramBotEssentials\Campaigns\Telegram\Forms\Prizes;

use TelegramBotEssentials\Campaigns\Prizes\WalletCreditPrize;
use TelegramBotEssentials\Campaigns\Telegram\Forms\PrizeConfigForm;
use TelegramBotEssentials\Essence\Forms\Steps\Text;

class WalletPrizeForm extends PrizeConfigForm
{
    protected string $type = 'CAMPAIGN_PRIZE_WALLET';

    protected string $lang = 'tbe-campaigns::prizes.wallet.wizard';

    protected function prizeTypeKey(): string
    {
        return WalletCreditPrize::KEY;
    }

    public function steps(): array
    {
        return [
            Text::make('amount')->rules(['numeric', 'gt:0', 'max:100000000']),
        ];
    }

    protected function config(array $answers): array
    {
        $amount = $answers['amount'] ?? '0';

        return ['amount' => is_scalar($amount) ? (string) $amount : '0'];
    }
}

<?php

namespace TelegramBotEssentials\Campaigns\Claims;

use TelegramBotEssentials\Campaigns\Contracts\ClaimMethod;
use TelegramBotEssentials\Campaigns\Enums\PrizeGrantStatus;
use TelegramBotEssentials\Campaigns\Models\CampaignPrizeGrant;
use TelegramBotEssentials\Campaigns\Services\PrizeGranting;
use TelegramBotEssentials\Campaigns\Services\PrizeMessages;

/** The plain method: one tap and the prize is handed over. */
class ClickClaim implements ClaimMethod
{
    public const KEY = 'click';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('tbe-campaigns::claims.click.label');
    }

    public function describe(array $config): string
    {
        return __('tbe-campaigns::claims.click.describe');
    }

    public function configForm(): ?string
    {
        return null;
    }

    public function defaultConfig(): array
    {
        return [];
    }

    public function buttonLabel(string $prize): string
    {
        return __('tbe-campaigns::prizes.keys.claim', ['prize' => $prize]);
    }

    public function start(CampaignPrizeGrant $grant): ?string
    {
        $status = app(PrizeGranting::class)->claim($grant);

        if ($status === PrizeGrantStatus::Granted || $status === PrizeGrantStatus::Failed) {
            app(PrizeMessages::class)->settle($grant, $status);

            return null;
        }

        return __('tbe-campaigns::prizes.claim.already');
    }
}

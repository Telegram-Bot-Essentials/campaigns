<?php

namespace TelegramBotEssentials\Campaigns\Telegram\Forms;

use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Campaigns\Services\CampaignPrizes;
use TelegramBotEssentials\Campaigns\Telegram\Features\Admin\CampaignsFeature;
use TelegramBotEssentials\Essence\Enums\Roles;
use TelegramBotEssentials\Essence\Forms\Form;
use TelegramBotEssentials\Essence\Telegram\TelegramResponse;

/**
 * Base for a prize type's config form. Campaigns starts it with
 * `['campaign' => id, 'lastPage' => n]`; the subclass names its type, turns
 * the answers into the prize's config, and campaigns attaches the prize and
 * lands the admin back on the campaign's prize list.
 */
abstract class PrizeConfigForm extends Form
{
    protected int $perm = Roles::ADMIN->value;

    /** The key of the `PrizeType` this form configures. */
    abstract protected function prizeTypeKey(): string;

    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    abstract protected function config(array $answers): array;

    public function onComplete(array $answers, array $ctx): TelegramResponse
    {
        $campaign = $this->campaign($ctx);

        CampaignPrizes::attach($campaign, $this->prizeTypeKey(), $this->config($answers));

        return CampaignsFeature::prizes($campaign, $this->lastPage($ctx));
    }

    public function originalScreen(array $ctx): ?TelegramResponse
    {
        return CampaignsFeature::prizes($this->campaign($ctx), $this->lastPage($ctx));
    }

    /** @param  array<string, mixed>  $ctx */
    private function campaign(array $ctx): Campaign
    {
        $id = $ctx['campaign'] ?? 0;

        return Campaign::query()->findOrFail(is_numeric($id) ? (int) $id : 0);
    }

    /** @param  array<string, mixed>  $ctx */
    private function lastPage(array $ctx): int
    {
        $lastPage = $ctx['lastPage'] ?? 1;

        return max(1, is_numeric($lastPage) ? (int) $lastPage : 1);
    }
}

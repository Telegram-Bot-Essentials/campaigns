<?php

namespace TelegramBotEssentials\Campaigns\Telegram\Forms;

use TelegramBotEssentials\Campaigns\Models\CampaignPrize;
use TelegramBotEssentials\Campaigns\Telegram\Features\Admin\CampaignsFeature;
use TelegramBotEssentials\Essence\Enums\Roles;
use TelegramBotEssentials\Essence\Forms\Form;
use TelegramBotEssentials\Essence\Telegram\TelegramResponse;

/**
 * Base for a claim method's config form. Campaigns starts it with
 * `['prize' => id, 'lastPage' => n]`; the subclass names its method and turns
 * the answers into the method's config, and campaigns stores both on the
 * prize and lands the admin back on its screen. Only grants issued afterwards
 * use the new method: the ones already handed out keep the one they got.
 */
abstract class ClaimMethodConfigForm extends Form
{
    protected int $perm = Roles::ADMIN->value;

    /** The key of the `ClaimMethod` this form configures. */
    abstract protected function claimMethodKey(): string;

    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    abstract protected function config(array $answers): array;

    public function onComplete(array $answers, array $ctx): TelegramResponse
    {
        $prize = $this->prize($ctx);

        $prize->update(['method' => $this->claimMethodKey(), 'method_config' => $this->config($answers)]);

        return CampaignsFeature::prize($prize, $this->lastPage($ctx));
    }

    public function originalScreen(array $ctx): ?TelegramResponse
    {
        return CampaignsFeature::prize($this->prize($ctx), $this->lastPage($ctx));
    }

    /** @param  array<string, mixed>  $ctx */
    private function prize(array $ctx): CampaignPrize
    {
        $id = $ctx['prize'] ?? 0;

        return CampaignPrize::query()->findOrFail(is_numeric($id) ? (int) $id : 0);
    }

    /** @param  array<string, mixed>  $ctx */
    private function lastPage(array $ctx): int
    {
        $lastPage = $ctx['lastPage'] ?? 1;

        return max(1, is_numeric($lastPage) ? (int) $lastPage : 1);
    }
}

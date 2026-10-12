<?php

namespace TelegramBotEssentials\Campaigns\Telegram\ReplyKeys\Admin;

use TelegramBotEssentials\Campaigns\Telegram\Features\Admin\CampaignsFeature;
use TelegramBotEssentials\Essence\Telegram\ReplyKeys\ReplyKey;

class CampaignsKey extends ReplyKey
{
    protected ?string $permission = 'campaigns.manage';

    protected function text(): string
    {
        return __('tbe-campaigns::campaigns.reply.keys.campaigns.text');
    }

    protected function response(): string
    {
        return __('tbe-campaigns::campaigns.reply.keys.campaigns.response');
    }

    public function handle(): void
    {
        CampaignsFeature::menu()->send();
    }
}

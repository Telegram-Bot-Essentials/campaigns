<?php

namespace TelegramBotEssentials\Campaigns\Web;

use Filament\Contracts\Plugin;
use Filament\Panel;
use TelegramBotEssentials\Campaigns\Web\Manage\CampaignResource;
use TelegramBotEssentials\Campaigns\Web\Widgets\CampaignJoinsChart;
use TelegramBotEssentials\Campaigns\Web\Widgets\CampaignOverview;

class CampaignsManagePlugin implements Plugin
{
    public function getId(): string
    {
        return 'tbe-campaigns-manage';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([CampaignResource::class]);
        $panel->widgets([CampaignOverview::class, CampaignJoinsChart::class]);
    }

    public function boot(Panel $panel): void {}
}

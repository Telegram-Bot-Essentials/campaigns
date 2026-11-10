<?php

namespace TelegramBotEssentials\Campaigns\Web\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use TelegramBotEssentials\Campaigns\Enums\PrizeGrantStatus;
use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Campaigns\Models\CampaignAttribution;
use TelegramBotEssentials\Campaigns\Models\CampaignMiss;
use TelegramBotEssentials\Campaigns\Models\CampaignPrizeGrant;
use TelegramBotEssentials\Essence\Web\Filament\Concerns\GuardsWidget;
use TelegramBotEssentials\Essence\Web\Filament\DailySeries;

/** Live campaigns, and who joined, was turned away or got a prize this month. */
class CampaignOverview extends StatsOverviewWidget
{
    use GuardsWidget;

    protected static ?string $ability = 'campaigns.manage';

    protected static ?int $sort = 7;

    // Drawn with the page above the campaign list.
    protected static bool $isLazy = false;

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $joins = DailySeries::of(CampaignAttribution::query(), 'created_at');
        $misses = DailySeries::of(CampaignMiss::query(), 'created_at');
        $prizes = DailySeries::of(CampaignPrizeGrant::query()->where('status', PrizeGrantStatus::Granted), 'granted_at');
        $live = Campaign::query()->where('active', true)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count();
        $last30 = (string) __('tbe-campaigns::web.stats.last_30');

        return [
            Stat::make((string) __('tbe-campaigns::web.stats.live'), number_format($live))
                ->description((string) __('tbe-campaigns::web.stats.total', ['count' => number_format(Campaign::query()->count())]))
                ->color('primary'),
            Stat::make((string) __('tbe-campaigns::web.stats.joined'), number_format(array_sum($joins)))
                ->description($last30)->chart(array_values($joins))->color('success'),
            Stat::make((string) __('tbe-campaigns::web.stats.turned_away'), number_format(array_sum($misses)))
                ->description($last30)->chart(array_values($misses))->color('gray'),
            Stat::make((string) __('tbe-campaigns::web.stats.prizes'), number_format(array_sum($prizes)))
                ->description($last30)->chart(array_values($prizes))->color('warning'),
        ];
    }
}

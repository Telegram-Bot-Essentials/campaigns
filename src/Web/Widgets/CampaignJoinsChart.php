<?php

namespace TelegramBotEssentials\Campaigns\Web\Widgets;

use Filament\Widgets\ChartWidget;
use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Campaigns\Models\CampaignAttribution;
use TelegramBotEssentials\Campaigns\Models\CampaignMiss;
use TelegramBotEssentials\Essence\Web\Filament\Concerns\GuardsWidget;
use TelegramBotEssentials\Essence\Web\Filament\DailySeries;

/** Joins and turn-aways per day, of one campaign on its page or of all on the dashboard. */
class CampaignJoinsChart extends ChartWidget
{
    use GuardsWidget;

    protected static ?string $ability = 'campaigns.manage';

    protected static ?int $sort = 8;

    protected ?string $maxHeight = '260px';

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = '30d';

    public ?Campaign $record = null;

    public function getHeading(): string
    {
        return (string) __('tbe-campaigns::web.charts.joins');
    }

    /** @return array<string, string> */
    protected function getFilters(): array
    {
        return DailySeries::ranges();
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $days = DailySeries::days($this->filter);
        $joins = CampaignAttribution::query();
        $misses = CampaignMiss::query();
        if ($this->record !== null) {
            $joins->where('campaign_id', $this->record->id);
            $misses->where('campaign_id', $this->record->id);
        }
        $joins = DailySeries::of($joins, 'created_at', $days);
        $misses = DailySeries::of($misses, 'created_at', $days);

        $line = fn (string $label, array $series, string $color, string $fill): array => [
            'label' => $label,
            'data' => array_values($series),
            'borderColor' => $color,
            'backgroundColor' => $fill,
            'fill' => true,
            'tension' => .3,
            'cubicInterpolationMode' => 'monotone',
        ];

        return [
            'datasets' => [
                $line((string) __('tbe-campaigns::web.stats.joined'), $joins, '#22c55e', 'rgba(34, 197, 94, .15)'),
                $line((string) __('tbe-campaigns::web.stats.turned_away'), $misses, '#9ca3af', 'rgba(156, 163, 175, .1)'),
            ],
            'labels' => DailySeries::labels($joins),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    /** @return array<string, mixed> */
    protected function getOptions(): array
    {
        return ['scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]]];
    }
}

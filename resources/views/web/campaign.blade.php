<x-filament-panels::page>
    @php($campaign = $this->campaign())
    @php($stats = $this->stats())
    @php($link = $this->link())
    <div class="grid gap-6">
        <x-filament::section>
            <dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(11rem,1fr));gap:1rem;">
                <div style="grid-column:1/-1;"><dt style="font-size:.875rem;opacity:.7;">{{ __('tbe-campaigns::web.link') }}</dt><dd style="font-weight:600;word-break:break-all;">@if ($link)<code>{{ $link }}</code>@else<span style="opacity:.7;">{{ __('tbe-campaigns::web.link_unknown', ['payload' => $campaign->payload()]) }}</span>@endif</dd></div>
                <div><dt style="font-size:.875rem;opacity:.7;">{{ __('tbe-campaigns::web.status') }}</dt><dd><x-filament::badge :color="\TelegramBotEssentials\Campaigns\Web\Manage\CampaignResource::statusColor($campaign)">{{ \TelegramBotEssentials\Campaigns\Web\Manage\CampaignResource::status($campaign) }}</x-filament::badge></dd></div>
                <div><dt style="font-size:.875rem;opacity:.7;">{{ __('tbe-campaigns::web.expires') }}</dt><dd style="font-weight:600;">{{ $campaign->expires_at?->format('Y-m-d H:i') ?? __('tbe-campaigns::campaigns.main.never') }}</dd></div>
                <div><dt style="font-size:.875rem;opacity:.7;">{{ __('tbe-campaigns::web.joined') }}</dt><dd style="font-weight:600;">{{ number_format($stats['joined']) }}</dd></div>
                <div><dt style="font-size:.875rem;opacity:.7;">{{ __('tbe-campaigns::web.paid_users') }}</dt><dd style="font-weight:600;">{{ number_format($stats['paidUsers']) }}</dd></div>
                <div><dt style="font-size:.875rem;opacity:.7;">{{ __('tbe-campaigns::web.revenue') }}</dt><dd style="font-weight:600;">{{ currency()->priceFormat($stats['revenue']) }}</dd></div>
                <div><dt style="font-size:.875rem;opacity:.7;">{{ __('tbe-campaigns::web.stats.turned_away') }}</dt><dd style="display:flex;flex-wrap:wrap;gap:.25rem;">
                    @forelse ($stats['missesByReason'] as $reason => $total)
                        <x-filament::badge color="gray">{{ __('tbe-campaigns::campaigns.main.reasons.'.$reason) }} · {{ $total }}</x-filament::badge>
                    @empty
                        <span style="font-weight:600;">0</span>
                    @endforelse
                </dd></div>
            </dl>
        </x-filament::section>

        {{ $this->table }}
    </div>
</x-filament-panels::page>

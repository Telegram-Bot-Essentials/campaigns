<x-filament-panels::page>
    @php($campaign = $this->campaign())
    @php($stats = $this->stats())
    @php($link = $this->link())
    @php($R = \TelegramBotEssentials\Campaigns\Web\Manage\CampaignResource::class)
    <div class="grid gap-6">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(13rem,1fr));gap:1rem;">
            <x-tbe::stat-card :label="__('tbe-campaigns::web.joined')" :value="number_format($stats['joined'])" icon="heroicon-o-user-plus" />
            <x-tbe::stat-card :label="__('tbe-campaigns::web.paid_users')" :value="number_format($stats['paidUsers'])" icon="heroicon-o-shopping-cart" />
            <x-tbe::stat-card :label="__('tbe-campaigns::web.revenue')" :value="currency()->priceFormat($stats['revenue'])" icon="heroicon-o-banknotes" />
        </div>

        <x-filament::section>
            <x-tbe::facts>
                <x-tbe::fact :label="__('tbe-campaigns::web.link')" wide>
                    @if ($link)
                        <code style="user-select:all;word-break:break-all;">{{ $link }}</code>
                    @else
                        <span style="opacity:.7;font-weight:400;">{{ __('tbe-campaigns::web.link_unknown', ['payload' => $campaign->payload()]) }}</span>
                    @endif
                </x-tbe::fact>
                <x-tbe::fact :label="__('tbe-campaigns::web.status')">
                    <x-filament::badge :color="$R::statusColor($campaign)">{{ $R::status($campaign) }}</x-filament::badge>
                </x-tbe::fact>
                <x-tbe::fact :label="__('tbe-campaigns::web.expires')">
                    @if ($campaign->expires_at)
                        <x-tbe::when :at="$campaign->expires_at" exact />
                    @else
                        {{ __('tbe-campaigns::campaigns.main.never') }}
                    @endif
                </x-tbe::fact>
                <x-tbe::fact :label="__('tbe-campaigns::web.stats.turned_away')">
                    <div style="display:flex;flex-wrap:wrap;gap:.25rem;">
                        @forelse ($stats['missesByReason'] as $reason => $total)
                            <x-filament::badge color="gray">{{ __('tbe-campaigns::campaigns.main.reasons.'.$reason) }} · {{ $total }}</x-filament::badge>
                        @empty
                            0
                        @endforelse
                    </div>
                </x-tbe::fact>
            </x-tbe::facts>
        </x-filament::section>

        {{ $this->table }}
    </div>
</x-filament-panels::page>

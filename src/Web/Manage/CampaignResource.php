<?php

namespace TelegramBotEssentials\Campaigns\Web\Manage;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Campaigns\Web\Widgets\CampaignOverview;
use TelegramBotEssentials\Essence\Web\Filament\PanelResource;

/**
 * Campaigns: start links that tell where members came from, with the prizes
 * they hand out. Every change goes through CampaignAdmin, the bot's own rules.
 */
class CampaignResource extends PanelResource
{
    protected static ?string $model = Campaign::class;

    protected static ?string $ability = 'campaigns.manage';

    protected static ?string $slug = 'campaigns';

    protected static ?string $tenantOwnershipRelationshipName = 'bot';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    public static function getNavigationLabel(): string
    {
        return (string) __('tbe-campaigns::web.nav');
    }

    public static function getModelLabel(): string
    {
        return (string) __('tbe-campaigns::web.campaign');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('tbe-campaigns::web.nav');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['attributions', 'prizes']))
            ->columns([
                TextColumn::make('name')->label((string) __('tbe-campaigns::web.name'))->searchable()->weight('bold'),
                TextColumn::make('code')->label((string) __('tbe-campaigns::web.code'))->searchable()->copyable()->fontFamily('mono'),
                TextColumn::make('status')
                    ->label((string) __('tbe-campaigns::web.status'))
                    ->state(fn (Campaign $record): string => self::status($record))
                    ->badge()
                    ->color(fn (Campaign $record): string => self::statusColor($record)),
                TextColumn::make('attributions_count')->label((string) __('tbe-campaigns::web.joined'))->sortable(),
                TextColumn::make('prizes_count')->label((string) __('tbe-campaigns::web.prizes')),
                TextColumn::make('expires_at')->label((string) __('tbe-campaigns::web.expires'))->since()->placeholder('—')->sortable(),
                TextColumn::make('created_at')->label((string) __('tbe-campaigns::web.created'))->since()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('active')->label((string) __('tbe-campaigns::web.enabled')),
            ])
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (Campaign $record): string => ViewCampaign::getUrl(['record' => $record]));
    }

    public static function status(Campaign $campaign): string
    {
        return match (true) {
            ! $campaign->active => (string) __('tbe-campaigns::campaigns.main.disabled'),
            $campaign->isExpired() => (string) __('tbe-campaigns::campaigns.main.expired'),
            default => (string) __('tbe-campaigns::campaigns.main.enabled'),
        };
    }

    public static function statusColor(Campaign $campaign): string
    {
        return match (true) {
            ! $campaign->active => 'gray',
            $campaign->isExpired() => 'warning',
            default => 'success',
        };
    }

    public static function getWidgets(): array
    {
        return [CampaignOverview::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCampaigns::route('/'),
            'view' => ViewCampaign::route('/{record}'),
        ];
    }
}

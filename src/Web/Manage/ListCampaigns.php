<?php

namespace TelegramBotEssentials\Campaigns\Web\Manage;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use TelegramBotEssentials\Campaigns\Services\CampaignAdmin;
use TelegramBotEssentials\Campaigns\Web\Widgets\CampaignOverview;
use TelegramBotEssentials\Essence\Web\Filament\Concerns\GuardsResourcePage;

class ListCampaigns extends ListRecords
{
    use GuardsResourcePage;

    protected static string $resource = CampaignResource::class;

    protected function getHeaderWidgets(): array
    {
        return [CampaignOverview::class];
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label((string) __('tbe-campaigns::campaigns.main.keys.create'))
                ->icon(Heroicon::OutlinedPlus)
                ->schema([
                    TextInput::make('name')->label((string) __('tbe-campaigns::web.name'))->required()->maxLength(60),
                    TextInput::make('days')->label((string) __('tbe-campaigns::web.days'))->integer()->helperText((string) __('tbe-campaigns::web.days_hint')),
                ])
                ->action(function (array $data, Action $action, CampaignAdmin $campaigns): void {
                    $key = wHook()->bot()->getKey();
                    $name = is_string($data['name'] ?? null) ? trim($data['name']) : '';
                    $days = is_scalar($data['days'] ?? null) && trim((string) $data['days']) !== '' ? trim((string) $data['days']) : null;

                    try {
                        $campaign = $campaigns->create(is_numeric($key) ? (int) $key : 0, $name, $days);
                    } catch (ValidationException $e) {
                        Notification::make()->title(collect($e->errors())->flatten()->implode(' '))->danger()->send();
                        $action->halt();

                        return;
                    }

                    $this->redirect(ViewCampaign::getUrl(['record' => $campaign]));
                }),
        ];
    }
}

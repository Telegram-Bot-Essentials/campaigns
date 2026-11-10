<?php

namespace TelegramBotEssentials\Campaigns\Web\Manage;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use TelegramBotEssentials\Campaigns\Contracts\ClaimMethod;
use TelegramBotEssentials\Campaigns\Contracts\PrizeType;
use TelegramBotEssentials\Campaigns\Enums\PrizeGrantStatus;
use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Campaigns\Models\CampaignPrize;
use TelegramBotEssentials\Campaigns\Services\CampaignAdmin;
use TelegramBotEssentials\Campaigns\Services\CampaignLink;
use TelegramBotEssentials\Campaigns\Services\CampaignPrizes;
use TelegramBotEssentials\Campaigns\Services\CampaignQr;
use TelegramBotEssentials\Campaigns\Services\CampaignStats;
use TelegramBotEssentials\Campaigns\Telegram\Forms\ClaimMethodConfigForm;
use TelegramBotEssentials\Campaigns\Telegram\Forms\PrizeConfigForm;
use TelegramBotEssentials\Campaigns\Web\Widgets\CampaignJoinsChart;
use TelegramBotEssentials\Essence\Web\Filament\Concerns\GuardsResourcePage;
use TelegramBotEssentials\Essence\Web\Filament\FormFields;

/**
 * One campaign: its link and QR, who it brought in, and its prizes. Prize
 * types and claim methods are configured with their own bot forms, shown as
 * fields, so a project's own types work here too.
 */
class ViewCampaign extends Page implements HasTable
{
    use GuardsResourcePage;
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = CampaignResource::class;

    protected string $view = 'tbe-campaigns::web.campaign';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function campaign(): Campaign
    {
        return Campaign::query()->whereKey($this->getRecord()->getKey())->firstOrFail();
    }

    public function getTitle(): string
    {
        return $this->campaign()->name;
    }

    public function link(): ?string
    {
        return CampaignLink::tryFor($this->campaign());
    }

    /** @return array{joined: int, misses: int, missesByReason: array<string, int>, paidUsers: int, revenue: string} */
    public function stats(): array
    {
        return CampaignStats::for($this->campaign());
    }

    protected function getHeaderWidgets(): array
    {
        return [CampaignJoinsChart::class];
    }

    /** @return array<string, mixed> */
    public function getWidgetData(): array
    {
        return ['record' => $this->campaign()];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading((string) __('tbe-campaigns::web.prizes'))
            ->query(CampaignPrize::query()->where('campaign_id', $this->getRecord()->getKey()))
            ->columns([
                TextColumn::make('prize')->label((string) __('tbe-campaigns::web.prize'))
                    ->state(fn (CampaignPrize $record): string => $record->describe())->weight('bold'),
                TextColumn::make('method')->label((string) __('tbe-campaigns::web.method'))
                    ->state(fn (CampaignPrize $record): string => $record->describeMethod()),
                TextColumn::make('grants_count')->label((string) __('tbe-campaigns::web.used'))
                    ->state(fn (CampaignPrize $record): string => $record->grants_count.' / '.($record->max_grants ?? '∞')),
                TextColumn::make('failed')->label((string) __('tbe-campaigns::web.failed'))
                    ->state(fn (CampaignPrize $record): int => $record->grants()->where('status', PrizeGrantStatus::Failed)->count())
                    ->color(fn (int $state): ?string => $state > 0 ? 'danger' : null),
                IconColumn::make('active')->label((string) __('tbe-campaigns::web.enabled'))->boolean(),
            ])
            ->defaultSort('id')
            ->paginated(false)
            ->headerActions([
                ActionGroup::make(array_values(array_filter(array_map(fn (PrizeType $type): ?Action => $this->addPrizeAction($type), prizeTypes()->all()))))
                    ->label((string) __('tbe-campaigns::prizes.keys.add'))
                    ->icon(Heroicon::OutlinedGift)
                    ->button(),
            ])
            ->recordActions([
                Action::make('togglePrize')
                    ->label(fn (CampaignPrize $record): string => (string) __($record->active ? 'tbe-campaigns::web.disable' : 'tbe-campaigns::web.enable'))
                    ->color(fn (CampaignPrize $record): string => $record->active ? 'gray' : 'success')
                    ->action(fn (CampaignPrize $record, CampaignAdmin $campaigns) => $campaigns->togglePrize($record)),
                Action::make('cap')
                    ->label((string) __('tbe-campaigns::web.cap'))
                    ->icon(Heroicon::OutlinedHashtag)
                    ->fillForm(fn (CampaignPrize $record): array => ['cap' => (string) ($record->max_grants ?? 0)])
                    ->schema([
                        TextInput::make('cap')->label((string) __('tbe-campaigns::web.cap'))->integer()->required()->helperText((string) __('tbe-campaigns::web.cap_hint')),
                    ])
                    ->action(function (CampaignPrize $record, array $data, Action $action, CampaignAdmin $campaigns): void {
                        try {
                            $campaigns->setCap($record, is_scalar($data['cap'] ?? null) ? trim((string) $data['cap']) : '');
                        } catch (ValidationException $e) {
                            Notification::make()->title(collect($e->errors())->flatten()->implode(' '))->danger()->send();
                            $action->halt();
                        }
                    }),
                ActionGroup::make(array_map(fn (ClaimMethod $method): Action => $this->methodAction($method), claimMethods()->all()))
                    ->label((string) __('tbe-campaigns::web.method'))
                    ->icon(Heroicon::OutlinedCursorArrowRays)
                    ->link(),
                Action::make('retry')
                    ->label((string) __('tbe-campaigns::web.retry'))
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (CampaignPrize $record): bool => $record->grants()->where('status', PrizeGrantStatus::Failed)->exists())
                    ->action(function (CampaignPrize $record, CampaignAdmin $campaigns): void {
                        $campaigns->retryFailed($record);
                        Notification::make()->title((string) __('tbe-campaigns::prizes.admin.retried'))->success()->send();
                    }),
            ])
            ->emptyStateHeading((string) __('tbe-campaigns::web.no_prizes'));
    }

    /** "Add" for one prize type, asking its config form's questions. Null when its form is not campaigns' kind. */
    private function addPrizeAction(PrizeType $type): ?Action
    {
        $form = app($type->configForm());
        if (! $form instanceof PrizeConfigForm) {
            return null;
        }

        return Action::make('addPrize_'.Str::slug($type->key(), '_'))
            ->label($type->label())
            ->modalHeading($type->label())
            ->schema(fn (): array => FormFields::for($form))
            ->action(function (array $data, Action $action) use ($form, $type): void {
                $answers = FormFields::answersFor($action, $form, $data);
                if ($answers === null) {
                    return;
                }

                CampaignPrizes::attach($this->campaign(), $type->key(), $form->configFrom($answers));
                Notification::make()->title((string) __('tbe-campaigns::web.prize_added'))->success()->send();
            });
    }

    /** Pick one claim method for a prize, through its config form when it has one. */
    private function methodAction(ClaimMethod $method): Action
    {
        $formClass = $method->configForm();
        $form = $formClass !== null ? app($formClass) : null;

        $action = Action::make('method_'.Str::slug($method->key(), '_'))
            ->label($method->label())
            ->icon(fn (CampaignPrize $record) => $record->method === $method->key() ? Heroicon::OutlinedCheck : null);

        if ($form instanceof ClaimMethodConfigForm) {
            return $action
                ->modalHeading($method->label())
                ->schema(fn (): array => FormFields::for($form))
                ->action(function (CampaignPrize $record, array $data, Action $action, CampaignAdmin $campaigns) use ($form, $method): void {
                    $answers = FormFields::answersFor($action, $form, $data);
                    if ($answers === null) {
                        return;
                    }

                    $campaigns->useMethod($record, $method, $form->configFrom($answers));
                    Notification::make()->title((string) __('tbe-campaigns::claims.admin.updated'))->success()->send();
                });
        }

        return $action->action(function (CampaignPrize $record, CampaignAdmin $campaigns) use ($method): void {
            $campaigns->useMethod($record, $method);
            Notification::make()->title((string) __('tbe-campaigns::claims.admin.updated'))->success()->send();
        });
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('qr')
                ->label((string) __('tbe-campaigns::web.qr'))
                ->icon(Heroicon::OutlinedQrCode)
                ->color('gray')
                ->visible(fn (): bool => $this->link() !== null)
                ->modalHeading(fn (): string => $this->campaign()->name)
                ->modalSubmitAction(false)
                ->modalContent(function (): HtmlString {
                    $link = (string) $this->link();
                    $png = 'data:image/png;base64,'.base64_encode(CampaignQr::png($link, 8));

                    return new HtmlString(
                        '<div style="display:grid;justify-items:center;gap:.75rem;">'
                        .'<img alt="QR" src="'.$png.'" style="width:16rem;height:16rem;image-rendering:pixelated;">'
                        .'<code style="word-break:break-all;">'.e($link).'</code>'
                        .'<a href="'.$png.'" download="campaign-'.e($this->campaign()->code).'.png" style="text-decoration:underline;">'.e((string) __('tbe-campaigns::web.download')).'</a>'
                        .'</div>'
                    );
                }),
            Action::make('edit')
                ->label((string) __('tbe-campaigns::web.edit'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->fillForm(fn (): array => ['name' => $this->campaign()->name])
                ->schema([
                    TextInput::make('name')->label((string) __('tbe-campaigns::web.name'))->required()->maxLength(60),
                    TextInput::make('days')->label((string) __('tbe-campaigns::web.days'))->integer()
                        ->helperText(fn (): string => (string) __('tbe-campaigns::web.expiry_keep', ['date' => $this->campaign()->expires_at?->format('Y-m-d') ?? __('tbe-campaigns::campaigns.main.never')])),
                    Checkbox::make('no_expiry')->label((string) __('tbe-campaigns::web.no_expiry')),
                ])
                ->action(function (array $data, Action $action, CampaignAdmin $campaigns): void {
                    $campaign = $this->campaign();
                    $text = fn (string $field): string => is_scalar($data[$field] ?? null) ? trim((string) $data[$field]) : '';

                    try {
                        $campaigns->rename($campaign, $text('name'));
                        if (($data['no_expiry'] ?? false) === true) {
                            $campaigns->clearExpiry($campaign);
                        } elseif ($text('days') !== '') {
                            $campaigns->expireIn($campaign, $text('days'));
                        }
                    } catch (ValidationException $e) {
                        Notification::make()->title(collect($e->errors())->flatten()->implode(' '))->danger()->send();
                        $action->halt();

                        return;
                    }

                    Notification::make()->title((string) __('tbe-campaigns::campaigns.main.answers.updated'))->success()->send();
                }),
            Action::make('toggle')
                ->label(fn (): string => (string) __($this->campaign()->active ? 'tbe-campaigns::web.disable' : 'tbe-campaigns::web.enable'))
                ->color(fn (): string => $this->campaign()->active ? 'gray' : 'success')
                ->action(fn (CampaignAdmin $campaigns) => $campaigns->toggle($this->campaign())),
            Action::make('delete')
                ->label((string) __('tbe-campaigns::web.delete'))
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription((string) __('tbe-campaigns::web.delete_hint'))
                ->action(function (CampaignAdmin $campaigns): void {
                    $campaigns->delete($this->campaign());
                    Notification::make()->title((string) __('tbe-campaigns::campaigns.main.answers.deleted'))->success()->send();
                    $this->redirect(CampaignResource::getUrl());
                }),
        ];
    }
}

<?php

namespace TelegramBotEssentials\Campaigns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use TelegramBotEssentials\Campaigns\Listeners\HandleCampaignDeepLink;
use TelegramBotEssentials\Campaigns\Models\CampaignAttribution;
use TelegramBotEssentials\Campaigns\Telegram\CallbackQueries\Admin\CampaignsQuery;
use TelegramBotEssentials\Campaigns\Telegram\Forms\CreateCampaignForm;
use TelegramBotEssentials\Campaigns\Telegram\StateAnswers\Admin\CampaignsAnswer;
use TelegramBotEssentials\Essence\Events\BotDeepLinkReceived;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\UserManagement\DTOs\BotUserFilter;
use TelegramBotEssentials\UserManagement\DTOs\UserSection;
use TelegramBotEssentials\UserManagement\Enums\SectionMode;

class TbeCampaignsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->registerPublishing();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'tbe-campaigns');

        // CallbackQueries, StateAnswers and Forms register themselves here.
        callbackQueryBus()->addCallbackQueries([
            CampaignsQuery::class,
        ]);

        stateAnswerBus()->addStateAnswers([
            CampaignsAnswer::class,
        ]);

        formRegistry()->addForms([
            CreateCampaignForm::class,
        ]);

        // ReplyKeys are different: essence has no directory-scan for a
        // companion's own ReplyKeys, so CampaignsKey only appears once the
        // consuming app lists it in config('tbe-essence.keyboard'). See the
        // README.

        Event::listen(BotDeepLinkReceived::class, HandleCampaignDeepLink::class);

        BotUser::resolveRelationUsing('campaignAttribution', function (BotUser $user) {
            return $user->hasOne(CampaignAttribution::class, 'bot_user_id', 'id');
        });

        $this->registerUserManagement();
    }

    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../lang' => resource_path('lang/vendor/tbe-campaigns'),
            ], 'tbe-campaigns-translations');
        }
    }

    /**
     * Optional: with user-management installed, the admin user detail screen
     * shows which campaign a user joined through, and the user list can be
     * narrowed to users who came from any campaign.
     */
    private function registerUserManagement(): void
    {
        if (! class_exists(BotUserFilter::class)) {
            return;
        }

        userManagementSections()->addSection(new UserSection(
            key: 'campaign',
            order: 20,
            mode: SectionMode::INLINE,
            label: fn (BotUser $user) => __('tbe-campaigns::user_management.section.label'),
            content: function (BotUser $user): array {
                $attribution = CampaignAttribution::query()
                    ->where('bot_user_id', $user->id)
                    ->with('campaign')
                    ->first();

                $campaign = $attribution?->campaign;

                return ['text' => __('tbe-campaigns::user_management.section.text', [
                    'campaign' => $campaign !== null ? e($campaign->name) : '—',
                ])];
            },
            active: fn (BotUser $user) => CampaignAttribution::query()->where('bot_user_id', $user->id)->exists(),
        ));

        botUserFilters()->addFilter(new BotUserFilter(
            key: 'campaign',
            label: fn () => __('tbe-campaigns::user_management.filters.campaign'),
            apply: fn (Builder $query) => $query->whereHas('campaignAttribution'),
        ));
    }
}

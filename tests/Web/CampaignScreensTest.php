<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use TelegramBotEssentials\Campaigns\Enums\PrizeGrantStatus;
use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Campaigns\Models\CampaignAttribution;
use TelegramBotEssentials\Campaigns\Models\CampaignPrize;
use TelegramBotEssentials\Campaigns\Models\CampaignPrizeGrant;
use TelegramBotEssentials\Campaigns\Services\CampaignPrizes;
use TelegramBotEssentials\Campaigns\Web\Manage\ListCampaigns;
use TelegramBotEssentials\Campaigns\Web\Manage\ViewCampaign;
use TelegramBotEssentials\Campaigns\Web\Widgets\CampaignOverview;
use TelegramBotEssentials\Essence\Web\WebAccounts;
use TelegramBotEssentials\UserWallet\Models\BotUserWallet;

beforeEach(function () {
    fakeTelegram();

    $this->bot = $this->makeBot(['bot_owner_peer_id' => 9501]);
    $this->admin = $this->grant($this->makeBotUser($this->bot, 9501), 'campaigns.manage', 'web.manage');
    $this->member = $this->makeBotUser($this->bot, 9502);
    $this->account = $this->panelAccount($this->admin);
    Auth::guard(WebAccounts::guard())->login($this->account);

    $this->campaign = Campaign::create(['bot_id' => $this->bot->id, 'name' => 'Summer sale']);
    $this->url = '/manage/'.$this->bot->unique_id.'/campaigns';
});

it('lists this bot\'s campaigns with their cards', function () {
    $otherBot = $this->makeBot(['unique_id' => 'other-bot']);
    Campaign::withoutGlobalScopes()->create(['bot_id' => $otherBot->id, 'name' => 'Elsewhere']);

    $this->get($this->url)
        ->assertOk()
        ->assertSee('Summer sale')
        ->assertSee($this->campaign->code)
        ->assertSee(__('tbe-campaigns::web.stats.live'))
        ->assertDontSee('Elsewhere');
});

it('keeps the screens from anyone without the ability', function () {
    $limited = $this->grant($this->makeBotUser($this->bot, 9503), 'web.manage');
    Auth::guard(WebAccounts::guard())->login($this->panelAccount($limited));

    $this->get($this->url)->assertForbidden();
    $this->get($this->url.'/'.$this->campaign->id)->assertForbidden();
});

it('does not open another bot\'s campaign', function () {
    $otherBot = $this->makeBot(['unique_id' => 'other-bot']);

    $this->get($this->url.'/'.Campaign::withoutGlobalScopes()->create(['bot_id' => $otherBot->id, 'name' => 'Theirs'])->id)->assertNotFound();
});

it('shows the link with the bot\'s username', function () {
    $this->get($this->url.'/'.$this->campaign->id)
        ->assertOk()
        ->assertSee('https://t.me/test_bot?start='.$this->campaign->payload());
});

it('still opens the campaign while Telegram does not answer', function () {
    // A fresh fake: fakeTelegram()'s would answer getMe first.
    $factory = new Factory;
    $factory->fake(['*' => Http::response(['ok' => false, 'description' => 'Bad Gateway'], 502)]);
    Http::swap($factory);

    $this->get($this->url.'/'.$this->campaign->id)
        ->assertOk()
        ->assertSee($this->campaign->payload())
        ->assertDontSee('t.me/');
});

it('creates a campaign and refuses a bad name or length', function () {
    $this->enterPanel('manage', $this->bot, $this->account);

    Livewire::test(ListCampaigns::class)->callAction('create', ['name' => 'Autumn', 'days' => '30'])->assertHasNoActionErrors();
    $autumn = Campaign::query()->where('name', 'Autumn')->firstOrFail();
    expect($autumn->expires_at?->isAfter(now()->addDays(29)))->toBeTrue();

    Livewire::test(ListCampaigns::class)->callAction('create', ['name' => 'A']);
    Livewire::test(ListCampaigns::class)->callAction('create', ['name' => 'Winter', 'days' => '5000']);
    expect(Campaign::query()->count())->toBe(2);
});

it('edits, pauses and ends a campaign, keeping who it brought in', function () {
    CampaignAttribution::create(['bot_id' => $this->bot->id, 'bot_user_id' => $this->member->id, 'campaign_id' => $this->campaign->id]);
    $this->enterPanel('manage', $this->bot, $this->account);

    Livewire::test(ViewCampaign::class, ['record' => $this->campaign->id])->callAction('edit', ['name' => 'Summer deal', 'days' => '7'])->assertHasNoActionErrors();
    expect($this->campaign->fresh()->name)->toBe('Summer deal')
        ->and($this->campaign->fresh()->expires_at)->not->toBeNull();

    Livewire::test(ViewCampaign::class, ['record' => $this->campaign->id])->callAction('edit', ['name' => 'Summer deal', 'no_expiry' => true]);
    expect($this->campaign->fresh()->expires_at)->toBeNull();

    Livewire::test(ViewCampaign::class, ['record' => $this->campaign->id])->callAction('toggle');
    expect($this->campaign->fresh()->active)->toBeFalse();

    Livewire::test(ViewCampaign::class, ['record' => $this->campaign->id])->callAction('delete');
    expect(Campaign::query()->find($this->campaign->id))->toBeNull()
        ->and(Campaign::withTrashed()->find($this->campaign->id))->not->toBeNull()
        ->and(CampaignAttribution::query()->where('campaign_id', $this->campaign->id)->count())->toBe(1);
});

it('adds a prize through its type\'s own questions', function () {
    $this->enterPanel('manage', $this->bot, $this->account);

    Livewire::test(ViewCampaign::class, ['record' => $this->campaign->id])
        ->callTableAction('addPrize_wallet', data: ['amount' => '5000'])
        ->assertHasNoTableActionErrors();

    $prize = CampaignPrize::query()->sole();
    expect($prize->type)->toBe('wallet')->and($prize->config)->toBe(['amount' => '5000']);
});

it('refuses a prize the type\'s rules refuse', function () {
    $this->enterPanel('manage', $this->bot, $this->account);

    Livewire::test(ViewCampaign::class, ['record' => $this->campaign->id])->callTableAction('addPrize_wallet', data: ['amount' => '0']);

    expect(CampaignPrize::query()->count())->toBe(0);
});

it('sets a claim method through its form, refusing a game that always wins', function () {
    $prize = CampaignPrizes::attach($this->campaign, 'wallet', ['amount' => '100']);
    $this->enterPanel('manage', $this->bot, $this->account);

    Livewire::test(ViewCampaign::class, ['record' => $this->campaign->id])
        ->callTableAction('method_dice', $prize, ['numbers' => '1,2,3,4,5,6', 'tries' => '2']);
    expect($prize->fresh()->method)->toBe('click');

    Livewire::test(ViewCampaign::class, ['record' => $this->campaign->id])
        ->callTableAction('method_dice', $prize, ['numbers' => '۲، ۴', 'tries' => '3'])
        ->assertHasNoTableActionErrors();
    expect($prize->fresh()->method)->toBe('dice')
        ->and($prize->fresh()->method_config)->toBe(['numbers' => [2, 4], 'tries' => 3]);

    Livewire::test(ViewCampaign::class, ['record' => $this->campaign->id])->callTableAction('method_click', $prize);
    expect($prize->fresh()->method)->toBe('click');
});

it('caps and pauses a prize', function () {
    $prize = CampaignPrizes::attach($this->campaign, 'wallet', ['amount' => '100']);
    $this->enterPanel('manage', $this->bot, $this->account);

    Livewire::test(ViewCampaign::class, ['record' => $this->campaign->id])->callTableAction('cap', $prize, ['cap' => '50']);
    expect($prize->fresh()->max_grants)->toBe(50);

    Livewire::test(ViewCampaign::class, ['record' => $this->campaign->id])->callTableAction('cap', $prize, ['cap' => '0']);
    expect($prize->fresh()->max_grants)->toBeNull();

    Livewire::test(ViewCampaign::class, ['record' => $this->campaign->id])->callTableAction('togglePrize', $prize);
    expect($prize->fresh()->active)->toBeFalse();
});

it('retries a failed prize for the member, not the admin', function () {
    $prize = CampaignPrizes::attach($this->campaign, 'wallet', ['amount' => '700']);
    $attribution = CampaignAttribution::create(['bot_id' => $this->bot->id, 'bot_user_id' => $this->member->id, 'campaign_id' => $this->campaign->id]);
    CampaignPrizeGrant::create([
        'bot_id' => $this->bot->id, 'campaign_prize_id' => $prize->id, 'campaign_attribution_id' => $attribution->id,
        'bot_user_id' => $this->member->id, 'status' => PrizeGrantStatus::Failed, 'method' => 'click',
    ]);
    $actor = $this->enterPanel('manage', $this->bot, $this->account);

    Livewire::test(ViewCampaign::class, ['record' => $this->campaign->id])->callTableAction('retry', $prize);

    $balance = fn ($user) => (float) (BotUserWallet::query()->where('bot_user_id', $user->id)->value('balance') ?? 0);
    expect(CampaignPrizeGrant::query()->sole()->status)->toBe(PrizeGrantStatus::Granted)
        ->and($balance($this->member))->toBe(700.0)
        ->and($balance($this->admin))->toBe(0.0)
        ->and(wHook()->user()->id)->toBe($actor->id);
});

// One Livewire update per test: Livewire skips persistent middleware it already ran for the route.
it('opens the edit form over a real request', function () {
    $this->livewireRequest($this->url.'/'.$this->campaign->id, 'ViewCampaign', 'mountAction', ['edit'])->assertOk();
});

it('refuses campaign updates once signed out', function () {
    $this->livewireRequest($this->url.'/'.$this->campaign->id, 'ViewCampaign', 'mountAction', ['edit'],
        fn () => Auth::guard(WebAccounts::guard())->logout())->assertUnauthorized();
});

it('hides the campaign cards from who may not manage campaigns', function () {
    $limited = $this->grant($this->makeBotUser($this->bot, 9504), 'web.manage');
    $this->enterPanel('manage', $this->bot, $this->panelAccount($limited));

    expect(CampaignOverview::canView())->toBeFalse();
});

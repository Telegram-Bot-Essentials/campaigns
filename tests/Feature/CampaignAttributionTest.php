<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Telegram\Bot\Objects\Update;
use TelegramBotEssentials\Campaigns\Enums\MissReason;
use TelegramBotEssentials\Campaigns\Events\CampaignUserAttributed;
use TelegramBotEssentials\Campaigns\Listeners\HandleCampaignDeepLink;
use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Campaigns\Models\CampaignAttribution;
use TelegramBotEssentials\Campaigns\Models\CampaignMiss;
use TelegramBotEssentials\Essence\Events\BotDeepLinkReceived;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Essence\Support\WebhookContext;

beforeEach(function () {
    $this->bot = $this->makeBot();
    wHook()->setBot($this->bot);

    $this->campaign = Campaign::create([
        'bot_id' => $this->bot->id,
        'name' => 'Summer sale',
    ]);
});

/**
 * Imports a full webhook context (bot, user and Telegram API) for the user,
 * the way the webhook middleware does, so the listener can send messages.
 */
function webhookContextFor(BotUser $user): WebhookContext
{
    $context = WebhookContext::fromArray([
        'bot_id' => test()->bot->id,
        'bot_user_id' => $user->id,
        'update' => new Update([]),
        'bot_token' => test()->bot->bot_token,
        'bot' => test()->bot,
        'bot_user' => $user,
    ]);

    wHook()->importContext($context);

    return $context;
}

function handleDeepLink(BotUser $user, string $payload): void
{
    app(HandleCampaignDeepLink::class)->handle(new BotDeepLinkReceived(webhookContextFor($user), $payload));
}

/**
 * Drives the listener the way the webhook does. A user created in this
 * very request has wasRecentlyCreated = true; a returning user is
 * re-fetched so the flag is false, exactly like a later request.
 */
function arriveViaCampaign(string $payload, int $peerId, bool $returning = false): BotUser
{
    $user = test()->makeBotUser(test()->bot, $peerId);

    if ($returning) {
        $user = BotUser::findOrFail($user->id);
    }

    handleDeepLink($user, $payload);

    return $user;
}

it('generates an eight character code and a c_ prefixed payload', function () {
    expect($this->campaign->code)->toHaveLength(8)
        ->and($this->campaign->payload())->toBe('c_'.$this->campaign->code);
});

it('attributes a brand-new user and fires the attributed event', function () {
    Event::fake([CampaignUserAttributed::class]);

    $user = arriveViaCampaign($this->campaign->payload(), 2001);

    $attribution = CampaignAttribution::sole();
    expect($attribution->campaign_id)->toBe($this->campaign->id)
        ->and($attribution->bot_user_id)->toBe($user->id)
        ->and(CampaignMiss::count())->toBe(0);

    Event::assertDispatched(CampaignUserAttributed::class);
});

it('ignores payloads that do not carry the reserved prefix', function () {
    arriveViaCampaign($this->campaign->code, 2002);
    arriveViaCampaign('CODE123', 2003);

    expect(CampaignAttribution::count())->toBe(0)
        ->and(CampaignMiss::count())->toBe(0);
});

it('records a miss and tells the user for every dead link', function (string $reason, Closure $arrange, string $payload) {
    Http::fake();
    $arrange($this->campaign);

    arriveViaCampaign($payload ?: $this->campaign->payload(), 2010);

    $miss = CampaignMiss::sole();
    expect($miss->reason)->toBe(MissReason::from($reason))
        ->and(CampaignAttribution::count())->toBe(0);

    Http::assertSent(fn ($request) => str_contains((string) $request->body(), 'sendMessage')
        || str_contains($request->url(), 'sendMessage'));
})->with([
    'unknown' => ['unknown', fn () => null, 'c_doesnotexist'],
    'deleted' => ['deleted', fn (Campaign $c) => $c->delete(), ''],
    'disabled' => ['disabled', fn (Campaign $c) => $c->update(['active' => false]), ''],
    'expired' => ['expired', fn (Campaign $c) => $c->update(['expires_at' => Carbon::now()->subMinute()]), ''],
]);

it('treats a returning user as a miss and does not attribute them', function () {
    arriveViaCampaign($this->campaign->payload(), 2020, returning: true);

    expect(CampaignMiss::sole()->reason)->toBe(MissReason::ExistingUser)
        ->and(CampaignAttribution::count())->toBe(0);
});

it('reports a dead link ahead of the returning-user reason', function () {
    $this->campaign->update(['expires_at' => Carbon::now()->subMinute()]);

    arriveViaCampaign($this->campaign->payload(), 2021, returning: true);

    expect(CampaignMiss::sole()->reason)->toBe(MissReason::Expired);
});

it('keeps one miss row when the same user hits the same dead link twice', function () {
    $this->campaign->update(['active' => false]);
    $user = arriveViaCampaign($this->campaign->payload(), 2030);

    handleDeepLink($user, $this->campaign->payload());

    expect(CampaignMiss::count())->toBe(1);
});

it('never attributes a user to a second campaign', function () {
    $second = Campaign::create(['bot_id' => $this->bot->id, 'name' => 'Winter sale']);

    $user = arriveViaCampaign($this->campaign->payload(), 2040);

    handleDeepLink($user, $second->payload());

    expect(CampaignAttribution::count())->toBe(1)
        ->and(CampaignAttribution::sole()->campaign_id)->toBe($this->campaign->id);
});

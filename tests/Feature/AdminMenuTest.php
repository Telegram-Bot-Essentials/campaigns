<?php

declare(strict_types=1);

use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Campaigns\Models\CampaignAttribution;
use TelegramBotEssentials\Campaigns\Telegram\ReplyKeys\Admin\CampaignsKey;
use TelegramBotEssentials\Essence\Enums\Roles;

const ADMIN = 900;
const MEMBER = 901;

beforeEach(function () {
    fakeTelegram();

    $this->bot = $this->makeBot();
    $this->admin = $this->makeBotUser($this->bot, ADMIN, ['power' => Roles::ADMIN->value]);
    $this->member = $this->makeBotUser($this->bot, MEMBER);
    wHook()->setBot($this->bot);

    // A companion's ReplyKeys are not auto-discovered: the consuming app
    // lists them in config('tbe-essence.keyboard'). One line stands in for it.
    replyKeyBus()->addReplyKey(CampaignsKey::class);

    $this->campaign = Campaign::create(['bot_id' => $this->bot->id, 'name' => 'Summer sale']);
});

function press(string $callbackData, int $peer = ADMIN): void
{
    test()->postWebhookUpdate(test()->bot, test()->makeCallbackQueryUpdate($callbackData, peerId: $peer))->assertOk();
}

function say(string $text, int $peer = ADMIN): void
{
    test()->postWebhookUpdate(test()->bot, test()->makeMessageUpdate($text, peerId: $peer))->assertOk();
}

function cb(string $method, array $params = []): string
{
    return encodeCallback('CAMPAIGNS', $method, $params);
}

it('opens the campaign menu from the admin reply key', function () {
    say(__('tbe-campaigns::campaigns.reply.keys.campaigns.text'));

    expect(lastBotText())->toBe(__('tbe-campaigns::campaigns.main.text.list'));
});

it('does not open the campaign menu for a member', function () {
    say(__('tbe-campaigns::campaigns.reply.keys.campaigns.text'), MEMBER);

    expect(lastBotText())->not->toBe(__('tbe-campaigns::campaigns.main.text.list'));
});

it('says so when there are no campaigns', function () {
    $this->campaign->forceDelete();

    press(cb('start', [1]));

    expect(lastBotText())->toBe(__('tbe-campaigns::campaigns.main.text.empty'));
});

it('lists campaigns with how many users each brought in', function () {
    $user = $this->makeBotUser($this->bot, 950);
    CampaignAttribution::create(['bot_id' => $this->bot->id, 'bot_user_id' => $user->id, 'campaign_id' => $this->campaign->id]);

    press(cb('start', [1]));

    $markup = tgCalls('editMessageText')->last()['reply_markup'];
    $markup = is_string($markup) ? json_decode($markup, true) : $markup;
    $buttons = collect($markup['inline_keyboard'] ?? [])->flatten(1)->pluck('text');

    expect($buttons->join('|'))->toContain('Summer sale')->toContain('1 joined');
});

it('shows a campaign with its t.me link and stats', function () {
    press(cb('show', [$this->campaign->id, 1]));

    expect(lastBotText())
        ->toContain('Summer sale')
        ->toContain("https://t.me/test_bot?start={$this->campaign->payload()}")
        ->toContain(__('tbe-campaigns::campaigns.main.stats.joined', ['count' => 0]));
});

it('enables and disables a campaign', function () {
    press(cb('toggle', [$this->campaign->id, 1]));
    expect($this->campaign->refresh()->active)->toBeFalse()
        ->and(lastBotText())->toContain(__('tbe-campaigns::campaigns.main.disabled'));

    press(cb('toggle', [$this->campaign->id, 1]));
    expect($this->campaign->refresh()->active)->toBeTrue();
});

it('lets a member neither open nor change a campaign', function () {
    press(cb('toggle', [$this->campaign->id, 1]), MEMBER);

    expect($this->campaign->refresh()->active)->toBeTrue();
});

it('sends the link as a QR code image', function () {
    press(cb('qr', [$this->campaign->id, 1]));

    expect(tgCalls('sendPhoto'))->toHaveCount(1);
});

it('soft-deletes a campaign and keeps who it brought in', function () {
    $user = $this->makeBotUser($this->bot, 951);
    CampaignAttribution::create(['bot_id' => $this->bot->id, 'bot_user_id' => $user->id, 'campaign_id' => $this->campaign->id]);

    press(cb('delete', [$this->campaign->id, 1]));

    expect(Campaign::count())->toBe(0)
        ->and(Campaign::withTrashed()->count())->toBe(1)
        ->and(CampaignAttribution::count())->toBe(1);
});

it('creates a campaign through the form and generates its code', function () {
    press(cb('create', [1]));
    say('Winter promo');
    say('30');
    say(__('tbe::forms.buttons.confirm'));

    $campaign = Campaign::query()->where('name', 'Winter promo')->sole();

    expect($campaign->code)->toHaveLength(8)
        ->and($campaign->active)->toBeTrue()
        ->and($campaign->expires_at?->isFuture())->toBeTrue()
        ->and((int) round(now()->diffInDays($campaign->expires_at, true)))->toBe(30);
});

it('creates a campaign without an expiry when the expiry step is skipped', function () {
    press(cb('create', [1]));
    say('Evergreen');
    say(__('tbe::forms.buttons.skip'));
    say(__('tbe::forms.buttons.confirm'));

    expect(Campaign::query()->where('name', 'Evergreen')->sole()->expires_at)->toBeNull();
});

it('writes nothing for a form that is never confirmed', function () {
    press(cb('create', [1]));
    say('Abandoned');

    expect(Campaign::query()->where('name', 'Abandoned')->exists())->toBeFalse();
});

it('renames a campaign', function () {
    press(cb('editName', [$this->campaign->id, 1]));
    say('Renamed sale');

    expect($this->campaign->refresh()->name)->toBe('Renamed sale');
});

it('refuses a name that is too short and leaves the campaign alone', function () {
    press(cb('editName', [$this->campaign->id, 1]));
    say('x');

    expect($this->campaign->refresh()->name)->toBe('Summer sale');
});

it('sets an expiry in days and clears it again', function () {
    press(cb('editExpiry', [$this->campaign->id, 1]));
    say('7');

    expect($this->campaign->refresh()->expires_at)->not->toBeNull()
        ->and((int) round(now()->diffInDays($this->campaign->expires_at, true)))->toBe(7);

    press(cb('clearExpiry', [$this->campaign->id, 1]));

    expect($this->campaign->refresh()->expires_at)->toBeNull();
});

it('refuses an expiry that is not a whole number of days', function () {
    press(cb('editExpiry', [$this->campaign->id, 1]));
    say('soon');

    expect($this->campaign->refresh()->expires_at)->toBeNull();
});

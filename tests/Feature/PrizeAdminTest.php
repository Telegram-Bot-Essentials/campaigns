<?php

declare(strict_types=1);

use TelegramBotEssentials\Campaigns\Enums\PrizeGrantStatus;
use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Campaigns\Models\CampaignAttribution;
use TelegramBotEssentials\Campaigns\Models\CampaignPrizeGrant;
use TelegramBotEssentials\Campaigns\Prizes\WalletCreditPrize;
use TelegramBotEssentials\Campaigns\Services\CampaignPrizes;
use TelegramBotEssentials\Campaigns\Telegram\CallbackQueries\Member\PrizeClaimQuery;
use TelegramBotEssentials\Campaigns\Tests\Support\RecordingPrize;
use TelegramBotEssentials\Essence\Enums\Roles;

beforeEach(function () {
    fakeTelegram();
    RecordingPrize::reset();

    $this->bot = $this->makeBot();
    $this->admin = $this->makeBotUser($this->bot, 800, ['power' => Roles::ADMIN->value]);
    $this->owner = $this->makeBotUser($this->bot, 801);
    $this->other = $this->makeBotUser($this->bot, 802);
    wHook()->setBot($this->bot);
    prizeTypes()->register(new RecordingPrize);

    $this->campaign = Campaign::create(['bot_id' => $this->bot->id, 'name' => 'Summer sale']);
    $this->prize = CampaignPrizes::attach($this->campaign, 'recording', []);
});

function pressAs(int $peer, string $type, string $method, array $params = []): void
{
    test()->postWebhookUpdate(test()->bot, test()->makeCallbackQueryUpdate(encodeCallback($type, $method, $params), peerId: $peer))->assertOk();
}

it('shows the prizes of a campaign to an admin', function () {
    pressAs(800, 'CAMPAIGNS', 'prizes', [$this->campaign->id, 1]);

    expect(lastBotText())->toContain('Summer sale');
    expect(json_encode(tgCalls('editMessageText')->last()['reply_markup']))->toContain('a recorded prize');
});

it('lists the registered prize types when adding a prize', function () {
    pressAs(800, 'CAMPAIGNS', 'addPrize', [$this->campaign->id, 1]);

    expect(json_encode(tgCalls('editMessageText')->last()['reply_markup']))->toContain('Recording');
});

it('starts the prize type config form from the type picker', function () {
    prizeTypes()->register(new WalletCreditPrize);

    pressAs(800, 'CAMPAIGNS', 'pickPrizeType', [$this->campaign->id, 'wallet', 1]);

    expect(lastBotText())->toContain(__('tbe-campaigns::prizes.wallet.wizard.fields.amount.prompt'));
});

it('accepts a wallet prize amount above 1000', function () {
    prizeTypes()->register(new WalletCreditPrize);

    pressAs(800, 'CAMPAIGNS', 'pickPrizeType', [$this->campaign->id, 'wallet', 1]);
    test()->postWebhookUpdate($this->bot, test()->makeMessageUpdate('50000', peerId: 800))->assertOk();

    expect(lastBotText())->toContain('50000');
});

it('refuses a prize type that is not registered', function () {
    pressAs(800, 'CAMPAIGNS', 'pickPrizeType', [$this->campaign->id, 'nope', 1]);

    expect(tgCalls('answerCallbackQuery')->last()['text'])->toBe(__('tbe-campaigns::prizes.admin.unknownType'));
});

it('keeps prize management away from members', function () {
    pressAs(801, 'CAMPAIGNS', 'togglePrize', [$this->prize->id, 1]);

    expect($this->prize->refresh()->active)->toBeTrue();
});

it('enables and disables a prize', function () {
    pressAs(800, 'CAMPAIGNS', 'togglePrize', [$this->prize->id, 1]);

    expect($this->prize->refresh()->active)->toBeFalse();
});

it('sets the cap from a typed answer, and treats 0 as no limit', function () {
    pressAs(800, 'CAMPAIGNS', 'editCap', [$this->prize->id, 1]);
    test()->postWebhookUpdate($this->bot, test()->makeMessageUpdate('5', peerId: 800))->assertOk();
    expect($this->prize->refresh()->max_grants)->toBe(5);

    pressAs(800, 'CAMPAIGNS', 'editCap', [$this->prize->id, 1]);
    test()->postWebhookUpdate($this->bot, test()->makeMessageUpdate('0', peerId: 800))->assertOk();
    expect($this->prize->refresh()->max_grants)->toBeNull();
});

it('lets an admin retry failed grants', function () {
    $attribution = CampaignAttribution::create(['bot_id' => $this->bot->id, 'bot_user_id' => $this->owner->id, 'campaign_id' => $this->campaign->id]);
    $grant = CampaignPrizeGrant::create([
        'bot_id' => $this->bot->id,
        'campaign_prize_id' => $this->prize->id,
        'campaign_attribution_id' => $attribution->id,
        'bot_user_id' => $this->owner->id,
        'status' => PrizeGrantStatus::Failed,
        'error' => 'panel is down',
    ]);

    pressAs(800, 'CAMPAIGNS', 'retryPrize', [$this->prize->id, 1]);

    expect($grant->refresh()->status)->toBe(PrizeGrantStatus::Granted)
        ->and(RecordingPrize::$granted)->toBe([$this->owner->id]);
});

describe('claiming', function () {
    beforeEach(function () {
        $attribution = CampaignAttribution::create(['bot_id' => $this->bot->id, 'bot_user_id' => $this->owner->id, 'campaign_id' => $this->campaign->id]);
        $this->grant = CampaignPrizeGrant::create([
            'bot_id' => $this->bot->id,
            'campaign_prize_id' => $this->prize->id,
            'campaign_attribution_id' => $attribution->id,
            'bot_user_id' => $this->owner->id,
        ]);
    });

    it('hands the prize to the user it was reserved for', function () {
        pressAs(801, PrizeClaimQuery::TYPE, 'claim', [$this->grant->id]);

        expect($this->grant->refresh()->status)->toBe(PrizeGrantStatus::Granted)
            ->and(RecordingPrize::$granted)->toBe([$this->owner->id]);
    });

    it('does not hand a forwarded button to someone else', function () {
        pressAs(802, PrizeClaimQuery::TYPE, 'claim', [$this->grant->id]);

        expect($this->grant->refresh()->status)->toBe(PrizeGrantStatus::Pending)
            ->and(RecordingPrize::$granted)->toBe([]);
    });
});

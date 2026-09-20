<?php

declare(strict_types=1);

use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Campaigns\Enums\MissReason;
use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Campaigns\Models\CampaignAttribution;
use TelegramBotEssentials\Campaigns\Models\CampaignMiss;
use TelegramBotEssentials\Campaigns\Services\CampaignStats;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\UserWallet\Models\CreditOrder;

beforeEach(function () {
    $this->bot = $this->makeBot();
    wHook()->setBot($this->bot);

    $this->campaign = Campaign::create(['bot_id' => $this->bot->id, 'name' => 'Summer sale']);
});

function joinedUser(int $peerId, ?Campaign $campaign = null): BotUser
{
    $user = test()->makeBotUser(test()->bot, $peerId);

    if ($campaign) {
        CampaignAttribution::create(['bot_id' => test()->bot->id, 'bot_user_id' => $user->id, 'campaign_id' => $campaign->id]);
    }

    return $user;
}

/** A paid invoice, written directly: status is guarded and the events are not under test. */
function invoiceFor(BotUser $user, int $price, string $status = 'paid', ?string $payableType = null): Invoice
{
    $invoice = Invoice::create([
        'bot_id' => test()->bot->id,
        'bot_user_id' => $user->id,
        'price' => (string) $price,
        'original_price' => (string) $price,
        'payable_type' => $payableType ?? BotUser::class,
        'payable_id' => 0,
    ]);

    Invoice::query()->whereKey($invoice->id)->update(['status' => $status]);

    return $invoice;
}

it('counts the users a campaign brought in', function () {
    joinedUser(1001, $this->campaign);
    joinedUser(1002, $this->campaign);
    joinedUser(1003);

    expect(CampaignStats::for($this->campaign)['joined'])->toBe(2);
});

it('breaks misses down by reason', function () {
    $user = joinedUser(1010);

    foreach ([MissReason::Expired, MissReason::ExistingUser] as $reason) {
        CampaignMiss::create([
            'bot_id' => $this->bot->id,
            'bot_user_id' => $user->id,
            'campaign_id' => $this->campaign->id,
            'payload' => $this->campaign->payload(),
            'reason' => $reason,
        ]);
    }

    $stats = CampaignStats::for($this->campaign);

    expect($stats['misses'])->toBe(2)
        ->and($stats['missesByReason'])->toEqual(['expired' => 1, 'existing_user' => 1]);
});

it('adds up the paid invoices of the campaign users', function () {
    $a = joinedUser(1020, $this->campaign);
    $b = joinedUser(1021, $this->campaign);

    invoiceFor($a, 100000);
    invoiceFor($a, 50000);
    invoiceFor($b, 25000);

    $stats = CampaignStats::for($this->campaign);

    expect($stats['paidUsers'])->toBe(2)
        ->and((float) $stats['revenue'])->toBe(175000.0);
});

it('leaves out pending and failed invoices', function () {
    $user = joinedUser(1030, $this->campaign);

    invoiceFor($user, 10000);
    invoiceFor($user, 20000, 'pending');
    invoiceFor($user, 30000, 'failed');

    expect((float) CampaignStats::for($this->campaign)['revenue'])->toBe(10000.0);
});

it('leaves out wallet top-ups, which move money rather than sell anything', function () {
    $user = joinedUser(1040, $this->campaign);

    invoiceFor($user, 10000);
    invoiceFor($user, 90000, 'paid', (new CreditOrder)->getMorphClass());

    expect((float) CampaignStats::for($this->campaign)['revenue'])->toBe(10000.0);
});

it('does not count what users of no campaign or another campaign paid', function () {
    $other = Campaign::create(['bot_id' => $this->bot->id, 'name' => 'Other']);

    invoiceFor(joinedUser(1050), 40000);
    invoiceFor(joinedUser(1051, $other), 60000);
    invoiceFor(joinedUser(1052, $this->campaign), 5000);

    expect((float) CampaignStats::for($this->campaign)['revenue'])->toBe(5000.0)
        ->and((float) CampaignStats::for($other)['revenue'])->toBe(60000.0);
});

it('stops counting an invoice once it is revoked back out of paid', function () {
    $user = joinedUser(1060, $this->campaign);
    $invoice = invoiceFor($user, 10000);

    expect((float) CampaignStats::for($this->campaign)['revenue'])->toBe(10000.0);

    Invoice::query()->whereKey($invoice->id)->update(['status' => 'failed']);

    expect((float) CampaignStats::for($this->campaign)['revenue'])->toBe(0.0);
});

it('reports zero revenue for a campaign nobody has paid through', function () {
    joinedUser(1070, $this->campaign);

    $stats = CampaignStats::for($this->campaign);

    expect($stats['paidUsers'])->toBe(0)
        ->and((float) $stats['revenue'])->toBe(0.0);
});

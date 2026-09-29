<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Telegram\Bot\Objects\Update;
use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Campaigns\Enums\PrizeGrantStatus;
use TelegramBotEssentials\Campaigns\Events\CampaignUserAttributed;
use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Campaigns\Models\CampaignAttribution;
use TelegramBotEssentials\Campaigns\Models\CampaignPrize;
use TelegramBotEssentials\Campaigns\Models\CampaignPrizeGrant;
use TelegramBotEssentials\Campaigns\Models\PrizePaymentAttempt;
use TelegramBotEssentials\Campaigns\Prizes\WalletCreditPrize;
use TelegramBotEssentials\Campaigns\Services\CampaignPrizes;
use TelegramBotEssentials\Campaigns\Services\CampaignStats;
use TelegramBotEssentials\Campaigns\Services\PrizeGranting;
use TelegramBotEssentials\Campaigns\Services\PrizeSettlement;
use TelegramBotEssentials\Campaigns\Telegram\Forms\Prizes\WalletPrizeForm;
use TelegramBotEssentials\Campaigns\Tests\Support\FakeOrder;
use TelegramBotEssentials\Campaigns\Tests\Support\RecordingPrize;
use TelegramBotEssentials\Essence\Enums\Roles;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Essence\Support\WebhookContext;

beforeEach(function () {
    fakeTelegram();
    RecordingPrize::reset();
    FakeOrder::$provisioned = 0;
    FakeOrder::$fail = false;

    $this->bot = $this->makeBot();
    wHook()->setBot($this->bot);
    tenancy()->initialize($this->bot);
    prizeTypes()->register(new RecordingPrize);

    $this->campaign = Campaign::create(['bot_id' => $this->bot->id, 'name' => 'Summer sale']);
});

function prizeContext(BotUser $user): WebhookContext
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

/** A brand-new user attributed to the campaign, as the deep-link listener leaves them. */
function newcomer(int $peerId): CampaignAttribution
{
    $user = test()->makeBotUser(test()->bot, $peerId);
    test()->context = prizeContext($user);

    return CampaignAttribution::create([
        'bot_id' => test()->bot->id,
        'bot_user_id' => $user->id,
        'campaign_id' => test()->campaign->id,
    ]);
}

function attachRecording(?int $cap = null): CampaignPrize
{
    return CampaignPrizes::attach(test()->campaign, 'recording', ['x' => 1], $cap);
}

it('reserves a pending grant for a new user and asks them to claim it', function () {
    $prize = attachRecording();
    $attribution = newcomer(3001);

    app(PrizeGranting::class)->issue($attribution);

    $grant = CampaignPrizeGrant::sole();
    expect($grant->status)->toBe(PrizeGrantStatus::Pending)
        ->and($grant->campaign_prize_id)->toBe($prize->id)
        ->and($prize->refresh()->grants_count)->toBe(1)
        ->and(RecordingPrize::$granted)->toBe([])
        ->and(tgCalls('sendMessage'))->toHaveCount(1)
        ->and(json_encode(tgCalls('sendMessage')->first()['reply_markup']))->toContain('CAMPAIGN_PRIZE');
});

it('listens for the attributed event', function () {
    attachRecording();
    $attribution = newcomer(3002);

    event(new CampaignUserAttributed($this->context, $this->campaign, $attribution));

    expect(CampaignPrizeGrant::count())->toBe(1);
});

it('hands a prize that needs no claim over at once', function () {
    RecordingPrize::$requiresClaim = false;
    attachRecording();
    $attribution = newcomer(3003);

    app(PrizeGranting::class)->issue($attribution);

    expect(CampaignPrizeGrant::sole()->status)->toBe(PrizeGrantStatus::Granted)
        ->and(RecordingPrize::$granted)->toBe([$attribution->bot_user_id]);
});

it('credits the wallet for the wallet prize', function () {
    prizeTypes()->register(new WalletCreditPrize);
    CampaignPrizes::attach($this->campaign, WalletCreditPrize::KEY, ['amount' => '25000']);
    $attribution = newcomer(3004);

    app(PrizeGranting::class)->issue($attribution);

    prizeContext($attribution->botUser);
    expect(CampaignPrizeGrant::sole()->status)->toBe(PrizeGrantStatus::Granted)
        ->and((string) wallet()->currentUserWalletBalance())->toBe('25000');
});

it('never reserves more grants than the cap', function () {
    $prize = attachRecording(cap: 1);

    foreach ([3010, 3011, 3012] as $peerId) {
        app(PrizeGranting::class)->issue(newcomer($peerId));
    }

    expect(CampaignPrizeGrant::count())->toBe(1)
        ->and($prize->refresh()->grants_count)->toBe(1);
});

it('gives a user each prize once however often the listener runs', function () {
    $prize = attachRecording();
    $attribution = newcomer(3020);

    app(PrizeGranting::class)->issue($attribution);
    app(PrizeGranting::class)->issue($attribution);

    expect(CampaignPrizeGrant::count())->toBe(1)
        ->and($prize->refresh()->grants_count)->toBe(1);
});

it('skips a disabled prize', function () {
    attachRecording()->update(['active' => false]);

    app(PrizeGranting::class)->issue(newcomer(3030));

    expect(CampaignPrizeGrant::count())->toBe(0);
});

it('hands a claimed prize over once, however often the button is tapped', function () {
    attachRecording();
    app(PrizeGranting::class)->issue(newcomer(3040));
    $grant = CampaignPrizeGrant::sole();

    $first = app(PrizeGranting::class)->claim($grant);
    $second = app(PrizeGranting::class)->claim($grant);

    expect($first)->toBe(PrizeGrantStatus::Granted)
        ->and($second)->toBe(PrizeGrantStatus::Granted)
        ->and(RecordingPrize::$granted)->toHaveCount(1)
        ->and($grant->refresh()->attempts)->toBe(1)
        ->and($grant->granted_at)->not->toBeNull();
});

it('records the error of a failed hand-over and keeps the slot', function () {
    $prize = attachRecording(cap: 1);
    app(PrizeGranting::class)->issue(newcomer(3050));
    $grant = CampaignPrizeGrant::sole();
    RecordingPrize::$fail = true;

    $status = app(PrizeGranting::class)->claim($grant);

    expect($status)->toBe(PrizeGrantStatus::Failed)
        ->and($grant->refresh()->error)->toBe('panel is down')
        ->and($prize->refresh()->grants_count)->toBe(1);
});

it('retries only failed grants, and grants them once', function () {
    attachRecording();
    app(PrizeGranting::class)->issue(newcomer(3060));
    $grant = CampaignPrizeGrant::sole();

    RecordingPrize::$fail = true;
    app(PrizeGranting::class)->claim($grant);
    RecordingPrize::$fail = false;

    expect(app(PrizeGranting::class)->retry($grant))->toBe(PrizeGrantStatus::Granted)
        ->and(app(PrizeGranting::class)->retry($grant))->toBe(PrizeGrantStatus::Granted)
        ->and(RecordingPrize::$granted)->toHaveCount(1)
        ->and($grant->refresh()->error)->toBeNull()
        ->and($grant->attempts)->toBe(2);
});

it('fails a grant whose prize type is no longer installed', function () {
    CampaignPrizes::attach($this->campaign, 'recording', []);
    app(PrizeGranting::class)->issue(newcomer(3070));
    CampaignPrize::query()->update(['type' => 'gone']);

    expect(app(PrizeGranting::class)->claim(CampaignPrizeGrant::sole()))->toBe(PrizeGrantStatus::Failed)
        ->and(CampaignPrizeGrant::sole()->error)->toContain('gone');
});

it('leaves a prize-marked invoice out of revenue even at a non-zero price', function () {
    $attribution = newcomer(3090);
    $invoice = Invoice::create([
        'bot_id' => $this->bot->id,
        'bot_user_id' => $attribution->bot_user_id,
        'price' => '50000',
        'original_price' => '50000',
        'payable_type' => BotUser::class,
        'payable_id' => 0,
    ]);
    Invoice::query()->whereKey($invoice->id)->update(['status' => 'paid', 'payment_attempt_type' => (new PrizePaymentAttempt)->getMorphClass(), 'payment_attempt_id' => 1]);

    $stats = CampaignStats::for($this->campaign);

    expect($stats['paidUsers'])->toBe(0)->and($stats['revenue'])->toBe('0');
});

it('attaches a wallet prize from its config form', function () {
    prizeTypes()->register(new WalletCreditPrize);
    prizeContext($this->makeBotUser($this->bot, 3095, ['power' => Roles::ADMIN->value]));

    $screen = (new WalletPrizeForm)->onComplete(['amount' => '25000'], ['campaign' => $this->campaign->id, 'lastPage' => 1]);

    $prize = CampaignPrize::sole();
    expect($prize->type)->toBe(WalletCreditPrize::KEY)
        ->and($prize->config)->toBe(['amount' => '25000'])
        ->and($prize->campaign_id)->toBe($this->campaign->id)
        ->and($screen->text)->toContain('Summer sale');
});

describe('settlement', function () {
    beforeEach(function () {
        Schema::create('fake_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->unsignedBigInteger('bot_user_id');
            $table->timestamps();
        });
    });

    function settlementFixture(): array
    {
        attachRecording();
        $attribution = newcomer(3080);
        app(PrizeGranting::class)->issue($attribution);
        $grant = CampaignPrizeGrant::sole();
        $order = FakeOrder::create(['bot_id' => test()->bot->id, 'bot_user_id' => $attribution->bot_user_id]);

        return [$grant, $order];
    }

    it('pays the order invoice with a prize attempt and runs the order hook once', function () {
        [$grant, $order] = settlementFixture();

        $invoice = app(PrizeSettlement::class)->pay($order, $grant);

        expect($invoice->refresh()->status)->toBe('paid')
            ->and(PrizePaymentAttempt::isPrizeInvoice($invoice))->toBeTrue()
            ->and(PrizePaymentAttempt::sole()->campaign_prize_grant_id)->toBe($grant->id)
            ->and((string) $invoice->price)->toBe('0')
            ->and((string) $invoice->original_price)->toBe('50000')
            ->and(FakeOrder::$provisioned)->toBe(1)
            ->and($grant->refresh()->invoice_id)->toBe($invoice->id)
            ->and($grant->order->is($order))->toBeTrue();
    });

    it('retries a failed provisioning without a second invoice or attempt', function () {
        [$grant, $order] = settlementFixture();

        FakeOrder::$fail = true;
        expect(fn () => app(PrizeSettlement::class)->pay($order, $grant))->toThrow(HttpException::class);

        FakeOrder::$fail = false;
        app(PrizeSettlement::class)->pay($grant->refresh()->order, $grant);

        expect(Invoice::count())->toBe(1)
            ->and(PrizePaymentAttempt::count())->toBe(1)
            ->and(FakeOrder::$provisioned)->toBe(1);
    });

    it('leaves prize invoices out of the campaign revenue', function () {
        [$grant, $order] = settlementFixture();
        app(PrizeSettlement::class)->pay($order, $grant);

        $stats = CampaignStats::for($this->campaign);

        expect($stats['paidUsers'])->toBe(0)
            ->and($stats['revenue'])->toBe('0');
    });
});

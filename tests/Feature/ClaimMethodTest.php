<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Telegram\Bot\Objects\Update;
use TelegramBotEssentials\Campaigns\Claims\ClickClaim;
use TelegramBotEssentials\Campaigns\Claims\DiceClaim;
use TelegramBotEssentials\Campaigns\Enums\PrizeGrantStatus;
use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Campaigns\Models\CampaignAttribution;
use TelegramBotEssentials\Campaigns\Models\CampaignPrize;
use TelegramBotEssentials\Campaigns\Models\CampaignPrizeGrant;
use TelegramBotEssentials\Campaigns\Services\CampaignPrizes;
use TelegramBotEssentials\Campaigns\Services\PrizeGranting;
use TelegramBotEssentials\Campaigns\Telegram\Forms\Claims\DiceClaimForm;
use TelegramBotEssentials\Campaigns\Tests\Support\RecordingPrize;
use TelegramBotEssentials\Essence\Enums\Roles;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Essence\Support\WebhookContext;

beforeEach(function () {
    fakeTelegram();
    RecordingPrize::reset();

    $this->bot = $this->makeBot();
    wHook()->setBot($this->bot);
    tenancy()->initialize($this->bot);
    prizeTypes()->register(new RecordingPrize);

    $this->campaign = Campaign::create(['bot_id' => $this->bot->id, 'name' => 'Summer sale']);
    $this->admin = $this->makeBotUser($this->bot, 900, ['power' => Roles::ADMIN->value]);
});

/** A recording prize received by dice. */
function dicePrize(array $numbers = [6], int $tries = 1, ?int $cap = null): CampaignPrize
{
    $prize = CampaignPrizes::attach(test()->campaign, 'recording', ['x' => 1], $cap);
    $prize->update(['method' => 'dice', 'method_config' => ['numbers' => $numbers, 'tries' => $tries]]);

    return $prize;
}

/** A newcomer with every live prize issued, as the join listener leaves them. */
function playerWith(int $peerId): BotUser
{
    $user = test()->makeBotUser(test()->bot, $peerId);
    $context = WebhookContext::fromArray([
        'bot_id' => test()->bot->id,
        'bot_user_id' => $user->id,
        'update' => new Update([]),
        'bot_token' => test()->bot->bot_token,
        'bot' => test()->bot,
        'bot_user' => $user,
    ]);
    wHook()->importContext($context);

    $attribution = CampaignAttribution::create([
        'bot_id' => test()->bot->id,
        'bot_user_id' => $user->id,
        'campaign_id' => test()->campaign->id,
    ]);
    app(PrizeGranting::class)->issue($attribution);

    return $user;
}

function tapPlay(CampaignPrizeGrant $grant, int $peerId): void
{
    test()->postWebhookUpdate(test()->bot, test()->makeCallbackQueryUpdate(encodeCallback('CAMPAIGN_PRIZE', 'claim', [$grant->id]), peerId: $peerId))->assertOk();
}

/** @param  array<string, mixed>  $extra */
function throwDice(int $peerId, int $messageId, int $value, ?int $replyTo = null, string $emoji = '🎲', array $extra = []): void
{
    $message = [
        'message_id' => $messageId,
        'date' => time(),
        'chat' => ['id' => $peerId, 'type' => 'private'],
        'from' => ['id' => $peerId, 'is_bot' => false, 'first_name' => 'Test'],
        'dice' => ['emoji' => $emoji, 'value' => $value],
    ];

    if ($replyTo !== null) {
        $message['reply_to_message'] = ['message_id' => $replyTo, 'date' => time(), 'chat' => ['id' => $peerId, 'type' => 'private']];
    }

    test()->postWebhookUpdate(test()->bot, ['message' => $message + $extra])->assertOk();
}

/** A game that has been started: the grant, with the id a throw must exceed. */
function startedGame(int $peerId, ?CampaignPrizeGrant $grant = null): CampaignPrizeGrant
{
    $grant ??= CampaignPrizeGrant::query()->latest('id')->firstOrFail();
    tapPlay($grant, $peerId);

    return $grant->refresh();
}

it('registers a tap and a dice game', function () {
    expect(claimMethods()->get('click'))->toBeInstanceOf(ClickClaim::class)
        ->and(claimMethods()->get('dice'))->toBeInstanceOf(DiceClaim::class);
});

it('starts a prize as a plain tap', function () {
    $prize = CampaignPrizes::attach($this->campaign, 'recording', ['x' => 1]);

    expect($prize->refresh()->method)->toBe('click');
});

it('keeps the method a grant was issued with when the prize changes later', function () {
    $prize = dicePrize();
    playerWith(4001);

    $prize->update(['method' => 'click', 'method_config' => []]);

    expect(CampaignPrizeGrant::sole()->method)->toBe('dice');
});

describe('admin', function () {
    beforeEach(function () {
        $this->prize = CampaignPrizes::attach($this->campaign, 'recording', ['x' => 1]);
    });

    it('lists the claim methods on the prize screen', function () {
        pressAs(900, 'CAMPAIGNS', 'pickMethod', [$this->prize->id, 1]);

        expect(json_encode(tgCalls('editMessageText')->last()['reply_markup']))->toContain('One tap')->toContain('Dice game');
    });

    it('switches to a method without a form at once', function () {
        $this->prize->update(['method' => 'dice', 'method_config' => ['numbers' => [6], 'tries' => 1]]);

        pressAs(900, 'CAMPAIGNS', 'chooseMethod', [$this->prize->id, 'click', 1]);

        expect($this->prize->refresh()->method)->toBe('click');
    });

    it('starts the dice form for the dice method', function () {
        pressAs(900, 'CAMPAIGNS', 'chooseMethod', [$this->prize->id, 'dice', 1]);

        expect(lastBotText())->toContain(__('tbe-campaigns::claims.dice.wizard.fields.numbers.prompt'))
            ->and($this->prize->refresh()->method)->toBe('click');
    });

    it('refuses an unknown method', function () {
        pressAs(900, 'CAMPAIGNS', 'chooseMethod', [$this->prize->id, 'nope', 1]);

        expect(tgCalls('answerCallbackQuery')->last()['text'])->toBe(__('tbe-campaigns::claims.admin.unknown'));
    });

    it('saves the dice config the admin typed', function () {
        (new DiceClaimForm)->onComplete(['numbers' => '2, 4 ،6', 'tries' => '3'], ['prize' => $this->prize->id, 'lastPage' => 1]);

        expect($this->prize->refresh()->method)->toBe('dice')
            ->and($this->prize->method_config)->toBe(['numbers' => [2, 4, 6], 'tries' => 3])
            ->and($this->prize->describeMethod())->toContain('2, 4, 6');
    });

    it('understands persian digits and commas', function () {
        (new DiceClaimForm)->onComplete(['numbers' => '۲،۴,۶', 'tries' => '۳'], ['prize' => $this->prize->id, 'lastPage' => 1]);

        expect($this->prize->refresh()->method_config)->toBe(['numbers' => [2, 4, 6], 'tries' => 3]);

        $steps = collect((new DiceClaimForm)->steps())->keyBy('key');
        $steps['numbers']->validate('۱،۲', [], 'numbers');
        $steps['tries']->validate('۲', [], 'tries');
    });

    it('rejects numbers outside 1 to 6, a full set, and too many tries', function (string $step, string $answer) {
        $steps = collect((new DiceClaimForm)->steps())->keyBy('key');

        expect(fn () => $steps[$step]->validate($answer, [], $step))->toThrow(ValidationException::class);
    })->with([
        'seven' => ['numbers', '2,7'],
        'zero' => ['numbers', '0'],
        'words' => ['numbers', 'even'],
        'all six' => ['numbers', '1,2,3,4,5,6'],
        'no tries' => ['tries', '0'],
        'too many' => ['tries', '11'],
    ]);
});

describe('dice game', function () {
    it('does not hand the prize over on the tap, and asks for a dice as a reply', function () {
        dicePrize();
        playerWith(4010);
        $grant = startedGame(4010);

        expect($grant->status)->toBe(PrizeGrantStatus::Pending)
            ->and(RecordingPrize::$granted)->toBe([])
            ->and($grant->prompt_message_id)->not->toBeNull()
            ->and(tgCalls('sendMessage')->last()['reply_parameters']['message_id'])->toBe($grant->message_id)
            ->and(tgCalls('editMessageText')->last())->not->toHaveKey('reply_markup');
    });

    it('asks once however often play is tapped', function () {
        dicePrize();
        playerWith(4011);
        $grant = startedGame(4011);
        $sent = tgCalls('sendMessage')->count();

        tapPlay($grant, 4011);

        expect(tgCalls('sendMessage'))->toHaveCount($sent);
    });

    it('hands the prize over on a winning throw and settles the message', function () {
        dicePrize([6]);
        $user = playerWith(4012);
        $grant = startedGame(4012);

        throwDice(4012, $grant->prompt_message_id + 5, 6, $grant->message_id);

        $edit = tgCalls('editMessageText')->last();
        expect($grant->refresh()->status)->toBe(PrizeGrantStatus::Granted)
            ->and(RecordingPrize::$granted)->toBe([$user->id])
            ->and($edit['text'])->toContain(__('tbe-campaigns::prizes.notify.received', ['prize' => 'a recorded prize']))
            ->and($edit)->not->toHaveKey('reply_markup');
    });

    it('lets a user throw again while tries remain', function () {
        $prize = dicePrize([6], tries: 2);
        playerWith(4013);
        $grant = startedGame(4013);

        throwDice(4013, $grant->prompt_message_id + 5, 1, $grant->message_id);

        expect($grant->refresh()->status)->toBe(PrizeGrantStatus::Pending)
            ->and($grant->plays)->toBe(1)
            ->and(lastBotText())->toContain('1 try(s) left')
            ->and($prize->refresh()->grants_count)->toBe(1);
    });

    it('loses on the last try, frees the slot and drops the button', function () {
        $prize = dicePrize([6], tries: 2, cap: 1);
        playerWith(4014);
        $grant = startedGame(4014);

        throwDice(4014, $grant->prompt_message_id + 5, 1, $grant->message_id);
        throwDice(4014, $grant->prompt_message_id + 6, 2, $grant->message_id);

        $edit = tgCalls('editMessageText')->last();
        expect($grant->refresh()->status)->toBe(PrizeGrantStatus::Lost)
            ->and($prize->refresh()->grants_count)->toBe(0)
            ->and(RecordingPrize::$granted)->toBe([])
            ->and($edit['text'])->toContain(__('tbe-campaigns::prizes.notify.lost', ['prize' => 'a recorded prize']))
            ->and($edit)->not->toHaveKey('reply_markup');
    });

    it('gives the freed slot to the next user', function () {
        $prize = dicePrize([6], tries: 1, cap: 1);
        playerWith(4015);
        $grant = startedGame(4015);
        throwDice(4015, $grant->prompt_message_id + 5, 1, $grant->message_id);

        playerWith(4016);

        expect(CampaignPrizeGrant::where('campaign_prize_id', $prize->id)->count())->toBe(2)
            ->and($prize->refresh()->grants_count)->toBe(1);
    });

    it('does not count a forwarded dice', function () {
        dicePrize([6]);
        playerWith(4020);
        $grant = startedGame(4020);

        throwDice(4020, $grant->prompt_message_id + 5, 6, $grant->message_id, extra: ['forward_origin' => ['type' => 'user', 'date' => time()]]);

        expect($grant->refresh()->status)->toBe(PrizeGrantStatus::Pending)
            ->and($grant->plays)->toBe(0)
            ->and(lastBotText())->toContain(__('tbe-campaigns::claims.dice.rejected.forwarded'));
    });

    it('does not count a dice sent through a bot', function () {
        dicePrize([6]);
        playerWith(4021);
        $grant = startedGame(4021);

        throwDice(4021, $grant->prompt_message_id + 5, 6, $grant->message_id, extra: ['via_bot' => ['id' => 5, 'is_bot' => true, 'first_name' => 'x']]);

        expect($grant->refresh()->plays)->toBe(0);
    });

    it('does not count another emoji', function () {
        dicePrize([1]);
        playerWith(4022);
        $grant = startedGame(4022);

        throwDice(4022, $grant->prompt_message_id + 5, 1, $grant->message_id, emoji: '🎯');

        expect($grant->refresh()->plays)->toBe(0)
            ->and(lastBotText())->toContain(__('tbe-campaigns::claims.dice.rejected.wrongEmoji'));
    });

    it('does not count a dice thrown before the game started', function () {
        dicePrize([6]);
        playerWith(4023);
        $grant = startedGame(4023);

        throwDice(4023, $grant->prompt_message_id - 1, 6, $grant->message_id);

        expect($grant->refresh()->plays)->toBe(0)
            ->and(lastBotText())->toContain(__('tbe-campaigns::claims.dice.rejected.stale'));
    });

    it('counts a redelivered update once', function () {
        dicePrize([6], tries: 3);
        playerWith(4024);
        $grant = startedGame(4024);

        throwDice(4024, $grant->prompt_message_id + 5, 1, $grant->message_id);
        throwDice(4024, $grant->prompt_message_id + 5, 1, $grant->message_id);

        expect($grant->refresh()->plays)->toBe(1);
    });

    it('accepts a throw that is not a reply when only one game is open', function () {
        dicePrize([6]);
        playerWith(4025);
        $grant = startedGame(4025);

        throwDice(4025, $grant->prompt_message_id + 5, 6);

        expect($grant->refresh()->status)->toBe(PrizeGrantStatus::Granted);
    });

    it('plays two prizes independently, each by its own reply', function () {
        dicePrize([6]);
        dicePrize([1]);
        playerWith(4026);
        [$first, $second] = CampaignPrizeGrant::query()->orderBy('id')->get()->all();
        startedGame(4026, $first);
        startedGame(4026, $second);
        $first->refresh();
        $second->refresh();

        throwDice(4026, max($first->prompt_message_id, $second->prompt_message_id) + 5, 1, $second->message_id);

        expect($second->refresh()->status)->toBe(PrizeGrantStatus::Granted)
            ->and($first->refresh()->status)->toBe(PrizeGrantStatus::Pending)
            ->and($first->plays)->toBe(0);
    });

    it('asks which prize a throw is for when several are open and it replies to none', function () {
        dicePrize([6]);
        dicePrize([1]);
        playerWith(4027);
        [$first, $second] = CampaignPrizeGrant::query()->orderBy('id')->get()->all();
        startedGame(4027, $first);
        startedGame(4027, $second);

        throwDice(4027, max($first->refresh()->prompt_message_id, $second->refresh()->prompt_message_id) + 5, 6);

        expect($first->refresh()->plays + $second->refresh()->plays)->toBe(0)
            ->and(lastBotText())->toContain(__('tbe-campaigns::claims.dice.ambiguous'));
    });

    it('ignores a dice from a user with no game open', function () {
        dicePrize([6]);
        playerWith(4028);
        $grant = CampaignPrizeGrant::sole();

        throwDice(4028, 999999, 6);

        expect($grant->refresh()->status)->toBe(PrizeGrantStatus::Pending)
            ->and($grant->plays)->toBe(0);
    });

    it('does not let another user play or win someone else\'s game', function () {
        dicePrize([6]);
        playerWith(4029);
        $grant = startedGame(4029);
        $this->makeBotUser($this->bot, 4030);

        throwDice(4030, $grant->prompt_message_id + 5, 6, $grant->message_id);

        expect($grant->refresh()->status)->toBe(PrizeGrantStatus::Pending);
    });
});

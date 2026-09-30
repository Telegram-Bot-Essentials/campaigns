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
use TelegramBotEssentials\Essence\Models\MessageMeta;
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

/** The user taps the button of the prize message. The tapped message carries its text and button, as Telegram sends them. */
function tapPlay(CampaignPrizeGrant $grant, int $peerId): void
{
    $update = test()->makeCallbackQueryUpdate(encodeCallback('CAMPAIGN_PRIZE', 'claim', [$grant->id]), peerId: $peerId);
    $update['callback_query']['message']['message_id'] = $grant->message_id;
    $update['callback_query']['message']['text'] = 'announcement '.$grant->id;
    $update['callback_query']['message']['reply_markup'] = ['inline_keyboard' => [[['text' => 'Play', 'callback_data' => 'x']]]];

    test()->postWebhookUpdate(test()->bot, $update)->assertOk();
}

/** @param  array<string, mixed>  $extra */
function throwDice(int $peerId, int $messageId, int $value, string $emoji = '🎲', array $extra = []): void
{
    test()->postWebhookUpdate(test()->bot, ['message' => [
        'message_id' => $messageId,
        'date' => time(),
        'chat' => ['id' => $peerId, 'type' => 'private'],
        'from' => ['id' => $peerId, 'is_bot' => false, 'first_name' => 'Test'],
        'dice' => ['emoji' => $emoji, 'value' => $value],
    ] + $extra])->assertOk();
}

function sendText(int $peerId, string $text): void
{
    test()->postWebhookUpdate(test()->bot, test()->makeMessageUpdate($text, peerId: $peerId))->assertOk();
}

/** A game that has been started: the grant, with the id a throw must exceed. */
function startedGame(int $peerId, ?CampaignPrizeGrant $grant = null): CampaignPrizeGrant
{
    $grant ??= CampaignPrizeGrant::query()->latest('id')->firstOrFail();
    tapPlay($grant, $peerId);

    return $grant->refresh();
}

function userState(int $peerId): ?string
{
    return BotUser::query()->where('telegram_user_peer_id', $peerId)->firstOrFail()->state;
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
    it('does not hand the prize over on the tap; it locks the message and waits for a dice', function () {
        dicePrize();
        playerWith(4010);
        $grant = startedGame(4010);

        expect($grant->status)->toBe(PrizeGrantStatus::Pending)
            ->and(RecordingPrize::$granted)->toBe([])
            ->and(userState(4010))->toContain('CAMPAIGN_DICE')
            ->and($grant->prompt_message_id)->not->toBeNull()
            ->and(json_encode(tgCalls('editMessageReplyMarkup')->last()['reply_markup']))->toContain('cancel_action');
    });

    it('hands the prize over on a winning throw, settles the message and ends the state', function () {
        dicePrize([6]);
        $user = playerWith(4012);
        $grant = startedGame(4012);

        throwDice(4012, $grant->prompt_message_id + 5, 6);

        $edit = tgCalls('editMessageText')->last();
        expect($grant->refresh()->status)->toBe(PrizeGrantStatus::Granted)
            ->and(RecordingPrize::$granted)->toBe([$user->id])
            ->and($edit['text'])->toContain(__('tbe-campaigns::prizes.notify.received', ['prize' => 'a recorded prize']))
            ->and($edit)->not->toHaveKey('reply_markup')
            ->and(userState(4012))->toBeNull();
    });

    it('lets a user throw again while tries remain, staying in the game', function () {
        $prize = dicePrize([6], tries: 2);
        playerWith(4013);
        $grant = startedGame(4013);

        throwDice(4013, $grant->prompt_message_id + 5, 1);

        expect($grant->refresh()->status)->toBe(PrizeGrantStatus::Pending)
            ->and($grant->plays)->toBe(1)
            ->and(lastBotText())->toContain('1 try(s) left')
            ->and(userState(4013))->toContain('CAMPAIGN_DICE')
            ->and($prize->refresh()->grants_count)->toBe(1);
    });

    it('loses on the last try, frees the slot, drops the button and ends the state', function () {
        $prize = dicePrize([6], tries: 2, cap: 1);
        playerWith(4014);
        $grant = startedGame(4014);

        throwDice(4014, $grant->prompt_message_id + 5, 1);
        throwDice(4014, $grant->prompt_message_id + 6, 2);

        $edit = tgCalls('editMessageText')->last();
        expect($grant->refresh()->status)->toBe(PrizeGrantStatus::Lost)
            ->and($prize->refresh()->grants_count)->toBe(0)
            ->and(RecordingPrize::$granted)->toBe([])
            ->and($edit['text'])->toContain(__('tbe-campaigns::prizes.notify.lost', ['prize' => 'a recorded prize']))
            ->and($edit)->not->toHaveKey('reply_markup')
            ->and(userState(4014))->toBeNull();
    });

    it('gives the freed slot to the next user', function () {
        $prize = dicePrize([6], tries: 1, cap: 1);
        playerWith(4015);
        $grant = startedGame(4015);
        throwDice(4015, $grant->prompt_message_id + 5, 1);

        playerWith(4016);

        expect(CampaignPrizeGrant::where('campaign_prize_id', $prize->id)->count())->toBe(2)
            ->and($prize->refresh()->grants_count)->toBe(1);
    });

    it('does not count a forwarded dice, and keeps the game open', function () {
        dicePrize([6]);
        playerWith(4020);
        $grant = startedGame(4020);

        throwDice(4020, $grant->prompt_message_id + 5, 6, extra: ['forward_origin' => ['type' => 'user', 'date' => time()]]);

        expect($grant->refresh()->status)->toBe(PrizeGrantStatus::Pending)
            ->and($grant->plays)->toBe(0)
            ->and(userState(4020))->toContain('CAMPAIGN_DICE')
            ->and(lastBotText())->toContain(__('tbe-campaigns::claims.dice.rejected.forwarded'));
    });

    it('does not count a dice sent through a bot', function () {
        dicePrize([6]);
        playerWith(4021);
        $grant = startedGame(4021);

        throwDice(4021, $grant->prompt_message_id + 5, 6, extra: ['via_bot' => ['id' => 5, 'is_bot' => true, 'first_name' => 'x']]);

        expect($grant->refresh()->plays)->toBe(0);
    });

    it('does not count another emoji', function () {
        dicePrize([1]);
        playerWith(4022);
        $grant = startedGame(4022);

        throwDice(4022, $grant->prompt_message_id + 5, 1, emoji: '🎯');

        expect($grant->refresh()->plays)->toBe(0)
            ->and(lastBotText())->toContain(__('tbe-campaigns::claims.dice.rejected.wrongEmoji'));
    });

    it('does not count a dice thrown before the game started', function () {
        dicePrize([6]);
        playerWith(4023);
        $grant = startedGame(4023);

        throwDice(4023, $grant->prompt_message_id - 1, 6);

        expect($grant->refresh()->plays)->toBe(0)
            ->and(lastBotText())->toContain(__('tbe-campaigns::claims.dice.rejected.stale'));
    });

    it('counts a redelivered update once', function () {
        dicePrize([6], tries: 3);
        playerWith(4024);
        $grant = startedGame(4024);

        throwDice(4024, $grant->prompt_message_id + 5, 1);
        throwDice(4024, $grant->prompt_message_id + 5, 1);

        expect($grant->refresh()->plays)->toBe(1);
    });

    it('tells the user what to send when they type instead of throwing', function () {
        dicePrize([6]);
        playerWith(4025);
        $grant = startedGame(4025);

        sendText(4025, 'hello');

        expect(lastBotText())->toContain(__('tbe-campaigns::claims.dice.hint'))
            ->and($grant->refresh()->plays)->toBe(0)
            ->and(userState(4025))->toContain('CAMPAIGN_DICE');
    });

    it('ignores a dice from a user who is not playing', function () {
        dicePrize([6]);
        playerWith(4028);
        $grant = CampaignPrizeGrant::sole();

        throwDice(4028, 999999, 6);

        expect($grant->refresh()->status)->toBe(PrizeGrantStatus::Pending)
            ->and($grant->plays)->toBe(0);
    });

    describe('cancelling', function () {
        it('cancels with the cancel key, puts the message back and keeps the throws used', function () {
            dicePrize([6], tries: 2);
            playerWith(4040);
            $grant = startedGame(4040);
            throwDice(4040, $grant->prompt_message_id + 5, 1);

            sendText(4040, __('tbe::cancel_process.reply_key'));

            $revert = tgCalls('editMessageText')->last();
            expect(userState(4040))->toBeNull()
                ->and($revert['message_id'])->toBe($grant->message_id)
                ->and($revert['text'])->toBe('announcement '.$grant->id)
                ->and($grant->refresh()->status)->toBe(PrizeGrantStatus::Pending)
                ->and($grant->plays)->toBe(1);
        });

        it('cancels with the button on the locked message', function () {
            dicePrize([6]);
            playerWith(4041);
            $grant = startedGame(4041);
            $meta = MessageMeta::query()->latest('id')->firstOrFail();

            pressAs(4041, 'MESSAGE_META', 'cancel_action', [$meta->id]);

            expect(userState(4041))->toBeNull()
                ->and(tgCalls('editMessageText')->last()['text'])->toBe('announcement '.$grant->id);
        });

        it('lets the user resume after cancelling, with the tries they have left', function () {
            dicePrize([6], tries: 2);
            playerWith(4042);
            $grant = startedGame(4042);
            throwDice(4042, $grant->prompt_message_id + 5, 1);
            sendText(4042, __('tbe::cancel_process.reply_key'));

            $grant = startedGame(4042, $grant);

            expect(lastBotText())->toContain('1 try(s) left')
                ->and(userState(4042))->toContain('CAMPAIGN_DICE');
        });

        it('cancels the open game when another action arrives', function () {
            dicePrize([6]);
            playerWith(4043);
            $grant = startedGame(4043);

            sendText(4043, '/start');

            expect(userState(4043))->toBeNull()
                ->and(tgCalls('editMessageText')->last()['text'])->toBe('announcement '.$grant->id);
        });

        it('reverts the first game when the user starts another', function () {
            dicePrize([6]);
            dicePrize([1]);
            playerWith(4044);
            [$first, $second] = CampaignPrizeGrant::query()->orderBy('id')->get()->all();

            startedGame(4044, $first);
            startedGame(4044, $second);

            $reverted = tgCalls('editMessageText')->last();
            expect($reverted['message_id'])->toBe($first->message_id)
                ->and($reverted['text'])->toBe('announcement '.$first->id)
                ->and(userState(4044))->toContain('"grant":'.$second->id);
        });

        it('lets only the game in the state be thrown for', function () {
            dicePrize([6]);
            dicePrize([6]);
            playerWith(4045);
            [$first, $second] = CampaignPrizeGrant::query()->orderBy('id')->get()->all();
            startedGame(4045, $first);
            $second = startedGame(4045, $second);

            throwDice(4045, $second->prompt_message_id + 5, 6);

            expect($second->refresh()->status)->toBe(PrizeGrantStatus::Granted)
                ->and($first->refresh()->status)->toBe(PrizeGrantStatus::Pending)
                ->and($first->plays)->toBe(0);
        });
    });
});

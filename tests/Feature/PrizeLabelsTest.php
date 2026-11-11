<?php

declare(strict_types=1);

use TelegramBotEssentials\Campaigns\Claims\ClickClaim;
use TelegramBotEssentials\Campaigns\Claims\DiceClaim;
use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Campaigns\Prizes\WalletCreditPrize;
use TelegramBotEssentials\Campaigns\Services\CampaignPrizes;
use TelegramBotEssentials\Campaigns\Support\CampaignLabels;
use TelegramBotEssentials\Campaigns\Tests\Support\RecordingPrize;

/** The bot's wording of prize types and claim methods stays as it was while the emoji moves out of the lang files. */
beforeEach(function () {
    fakeTelegram();
    RecordingPrize::reset();

    $this->bot = $this->makeBot();
    $this->grant($this->makeBotUser($this->bot, 800), 'campaigns.manage');
    wHook()->setBot($this->bot);
    prizeTypes()->register(new RecordingPrize);
    $this->campaign = Campaign::create(['bot_id' => $this->bot->id, 'name' => 'Summer sale']);
});

it('words prize types and claim methods for the bot with their emoji', function (string $locale, array $expected) {
    app()->setLocale($locale);

    expect(CampaignLabels::of(new WalletCreditPrize)->forBot())->toBe($expected[0])
        ->and(CampaignLabels::of(new ClickClaim)->forBot())->toBe($expected[1])
        ->and(CampaignLabels::of(app(DiceClaim::class))->forBot())->toBe($expected[2]);
})->with([
    'english' => ['en', ['💰 Wallet credit', '👆 One tap', '🎲 Dice game']],
    'persian' => ['fa', ['💰 اعتبار کیف پول', '👆 یک کلیک', '🎲 بازی تاس']],
]);

it('names them for the panel without the emoji', function () {
    app()->setLocale('en');

    expect(CampaignLabels::of(new WalletCreditPrize)->forWeb())->toBe('Wallet credit')
        ->and(CampaignLabels::of(new ClickClaim)->forWeb())->toBe('One tap')
        ->and(CampaignLabels::of(new ClickClaim)->icon)->not->toBeNull();
});

it('keeps the emoji on the bot\'s claim method buttons', function () {
    $prize = CampaignPrizes::attach($this->campaign, 'recording', ['x' => 1]);

    pressAs(800, 'CAMPAIGNS', 'pickMethod', [$prize->id, 1]);

    $markup = tgCalls('editMessageText')->last()['reply_markup'];

    expect(json_encode(is_string($markup) ? json_decode($markup, true) : $markup, JSON_UNESCAPED_UNICODE))
        ->toContain('👆 One tap')->toContain('🎲 Dice game');
});

it('falls back to a prize type\'s own label when it gives no Label', function () {
    expect(CampaignLabels::of(new RecordingPrize)->forBot())->toBe((new RecordingPrize)->label());
});

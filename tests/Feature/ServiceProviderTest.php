<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\HasOne;
use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Campaigns\Models\CampaignAttribution;
use TelegramBotEssentials\Essence\Events\BotDeepLinkReceived;
use TelegramBotEssentials\Essence\Models\BotUser;

it('listens for deep links', function () {
    expect(collect(app('events')->getListeners(BotDeepLinkReceived::class)))->not->toBeEmpty();
});

it('adds the campaignAttribution relation to BotUser', function () {
    $relation = (new BotUser)->campaignAttribution();

    expect($relation)->toBeInstanceOf(HasOne::class)
        ->and($relation->getRelated())->toBeInstanceOf(CampaignAttribution::class);
});

describe('user-management integration', function () {
    beforeEach(function () {
        $this->bot = $this->makeBot();
        wHook()->setBot($this->bot);
        $this->campaign = Campaign::create(['bot_id' => $this->bot->id, 'name' => 'Summer sale']);
    });

    it('shows the campaign section only for a user who joined through one', function () {
        $joined = $this->makeBotUser($this->bot, 2001);
        $other = $this->makeBotUser($this->bot, 2002);
        CampaignAttribution::create(['bot_id' => $this->bot->id, 'bot_user_id' => $joined->id, 'campaign_id' => $this->campaign->id]);

        $keysFor = fn ($user) => userManagementSections()->getSectionsFor($user)->pluck('key')->all();

        expect($keysFor($joined))->toContain('campaign')
            ->and($keysFor($other))->not->toContain('campaign');
    });

    it('names the campaign in the section, even after it was deleted', function () {
        $user = $this->makeBotUser($this->bot, 2003);
        CampaignAttribution::create(['bot_id' => $this->bot->id, 'bot_user_id' => $user->id, 'campaign_id' => $this->campaign->id]);
        $this->campaign->delete();

        $section = userManagementSections()->getSectionsFor($user)->firstWhere('key', 'campaign');

        expect($section->contentFor($user)['text'])->toContain('Summer sale');
    });

    it('registers a filter for users who came from a campaign', function () {
        $joined = $this->makeBotUser($this->bot, 2004);
        $this->makeBotUser($this->bot, 2005);
        CampaignAttribution::create(['bot_id' => $this->bot->id, 'bot_user_id' => $joined->id, 'campaign_id' => $this->campaign->id]);

        $filter = botUserFilters()->getFilter('campaign');

        expect($filter)->not->toBeNull();

        $ids = $filter->applyTo(BotUser::query())->pluck('id')->all();

        expect($ids)->toBe([$joined->id]);
    });
});

<?php

namespace TelegramBotEssentials\Campaigns\Telegram\CallbackQueries\Admin;

use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Campaigns\Telegram\Features\Admin\CampaignsFeature;
use TelegramBotEssentials\Campaigns\Telegram\Forms\CreateCampaignForm;
use TelegramBotEssentials\Essence\Enums\Roles;
use TelegramBotEssentials\Essence\Exceptions\InvalidPageNumber;
use TelegramBotEssentials\Essence\Models\MessageMeta;
use TelegramBotEssentials\Essence\Telegram\CallbackQueries\CallbackQuery;

class CampaignsQuery extends CallbackQuery
{
    protected string $type = 'CAMPAIGNS';

    protected int $perm = Roles::ADMIN->value;

    /**
     * @throws InvalidPageNumber
     */
    public function start(int $page = 1, int $currentPage = 0): void
    {
        CampaignsFeature::menu($page, $currentPage)->update();
    }

    public function setStartPage(): void
    {
        $messageMeta = MessageMeta::makeWithCurrentMessage();
        $messageMeta->lockAction(__('tbe-campaigns::campaigns.wizard.waitingPage'));

        wHook()->user()->changeState(encodeAnswerState($this->type, 'setStartPage', [
            'message_meta' => $messageMeta->id,
        ]));

        wHook()->api()->sendMessage([
            'chat_id' => wHook()->peerId(),
            'text' => __('tbe-campaigns::campaigns.wizard.enterPage'),
            'reply_markup' => wHook()->user()->getKeyboard(),
        ]);

        $this->answer();
    }

    public function show(Campaign $campaign, int $lastPage = 1): void
    {
        CampaignsFeature::show($campaign, $lastPage)->update();
    }

    public function toggle(Campaign $campaign, int $lastPage = 1): void
    {
        $campaign->update(['active' => ! $campaign->active]);

        CampaignsFeature::show($campaign, $lastPage)
            ->answer($campaign->active
                ? __('tbe-campaigns::campaigns.main.answers.enabled')
                : __('tbe-campaigns::campaigns.main.answers.disabled'))
            ->update();
    }

    public function qr(Campaign $campaign, int $lastPage = 1): void
    {
        CampaignsFeature::qr($campaign, $lastPage)->send();

        $this->answer(__('tbe-campaigns::campaigns.main.answers.qrSent'));
    }

    /**
     * The campaign is only soft-deleted: users it already brought in stay
     * attributed to it, and its link keeps answering "this offer has ended".
     */
    public function delete(Campaign $campaign, int $lastPage = 1): void
    {
        $campaign->delete();

        CampaignsFeature::menu(max(1, $lastPage))
            ->answer(__('tbe-campaigns::campaigns.main.answers.deleted'))
            ->update();
    }

    /** Starts the creation form: the current screen freezes until it ends. */
    public function create(int $lastPage = 1): void
    {
        CreateCampaignForm::start(['lastPage' => $lastPage]);

        $this->answer();
    }

    public function editName(Campaign $campaign, int $lastPage = 1): void
    {
        $this->askFor('updateName', $campaign, $lastPage, 'name');
    }

    public function editExpiry(Campaign $campaign, int $lastPage = 1): void
    {
        $this->askFor('updateExpiry', $campaign, $lastPage, 'expiry');
    }

    public function clearExpiry(Campaign $campaign, int $lastPage = 1): void
    {
        $campaign->update(['expires_at' => null]);

        CampaignsFeature::show($campaign, $lastPage)
            ->answer(__('tbe-campaigns::campaigns.main.answers.updated'))
            ->update();
    }

    /** Locks the screen and waits for the admin's typed answer. */
    private function askFor(string $answerMethod, Campaign $campaign, int $lastPage, string $field): void
    {
        $messageMeta = MessageMeta::makeWithCurrentMessage();
        $messageMeta->cancelableLockAction(__('tbe-campaigns::campaigns.wizard.fields.'.$field.'.label'));

        wHook()->user()->changeState(encodeAnswerState($this->type, $answerMethod, [
            'campaign' => $campaign->id,
            'lastPage' => $lastPage,
            'message_meta' => $messageMeta->id,
        ]));

        wHook()->api()->sendMessage([
            'chat_id' => wHook()->peerId(),
            'text' => __('tbe-campaigns::campaigns.wizard.fields.'.$field.'.editPrompt'),
            'reply_markup' => wHook()->user()->getKeyboard(),
            'parse_mode' => 'HTML',
        ]);

        $this->answer();
    }
}

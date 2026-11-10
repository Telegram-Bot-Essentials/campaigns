<?php

namespace TelegramBotEssentials\Campaigns\Telegram\StateAnswers\Admin;

use Illuminate\Validation\ValidationException;
use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Campaigns\Models\CampaignPrize;
use TelegramBotEssentials\Campaigns\Services\CampaignAdmin;
use TelegramBotEssentials\Campaigns\Telegram\Features\Admin\CampaignsFeature;
use TelegramBotEssentials\Essence\Enums\AllowableFields;
use TelegramBotEssentials\Essence\Exceptions\InvalidPageNumber;
use TelegramBotEssentials\Essence\Services\TelegramPaginator;
use TelegramBotEssentials\Essence\Telegram\StateAnswers\StateAnswer;

class CampaignsAnswer extends StateAnswer
{
    protected string $type = 'CAMPAIGNS';

    protected ?string $ability = 'campaigns.manage';

    /** @var array<int, string> */
    protected array $allowedFields = [
        AllowableFields::TEXT->value,
    ];

    /**
     * @throws InvalidPageNumber
     */
    public function setStartPage(): void
    {
        $page = $this->answerText();
        $lastPage = Campaign::query()->where('bot_id', wHook()->bot()->id)->paginate(perPage: 10)->lastPage();

        TelegramPaginator::validatePageInput($page, $lastPage);

        $data = CampaignsFeature::menu(intval($page));

        wHook()->conversation()->changeState();
        wHook()->api()->sendMessage([
            'chat_id' => wHook()->peerId(),
            'text' => __('tbe-campaigns::campaigns.wizard.pageLoaded', ['page' => $page]),
            'reply_markup' => replyKeyboard(),
        ]);

        $this->requireMessageMeta()->updateAndContinueAction($data);
    }

    /**
     * @throws ValidationException
     */
    public function updateName(Campaign $campaign, int $lastPage): void
    {
        $name = $this->answerText();

        app(CampaignAdmin::class)->rename($campaign, $name);
        wHook()->conversation()->changeState();

        $this->requireMessageMeta()->updateAndContinueAction(CampaignsFeature::show($campaign, $lastPage));
    }

    /**
     * @throws ValidationException
     */
    public function updateExpiry(Campaign $campaign, int $lastPage): void
    {
        $days = $this->answerText();

        app(CampaignAdmin::class)->expireIn($campaign, $days);
        wHook()->conversation()->changeState();

        $this->requireMessageMeta()->updateAndContinueAction(CampaignsFeature::show($campaign, $lastPage));
    }

    /**
     * 0 means unlimited. Lowering the cap below what is already reserved
     * only stops new grants; the users who already have one keep it.
     *
     * @throws ValidationException
     */
    public function updateCap(CampaignPrize $prize, int $lastPage): void
    {
        app(CampaignAdmin::class)->setCap($prize, $this->answerText());
        wHook()->conversation()->changeState();

        $this->requireMessageMeta()->updateAndContinueAction(CampaignsFeature::prize($prize, $lastPage));
    }

    private function answerText(): string
    {
        $message = wHook()->update()->message;

        return $message === null ? '' : trim($message->text ?? '');
    }
}

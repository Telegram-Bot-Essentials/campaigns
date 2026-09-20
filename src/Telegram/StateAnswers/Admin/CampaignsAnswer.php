<?php

namespace TelegramBotEssentials\Campaigns\Telegram\StateAnswers\Admin;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Campaigns\Telegram\Features\Admin\CampaignsFeature;
use TelegramBotEssentials\Essence\Enums\AllowableFields;
use TelegramBotEssentials\Essence\Enums\Roles;
use TelegramBotEssentials\Essence\Exceptions\InvalidPageNumber;
use TelegramBotEssentials\Essence\Services\TelegramPaginator;
use TelegramBotEssentials\Essence\Telegram\StateAnswers\StateAnswer;

class CampaignsAnswer extends StateAnswer
{
    protected string $type = 'CAMPAIGNS';

    protected int $perm = Roles::ADMIN->value;

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

        wHook()->user()->changeState();
        wHook()->api()->sendMessage([
            'chat_id' => wHook()->peerId(),
            'text' => __('tbe-campaigns::campaigns.wizard.pageLoaded', ['page' => $page]),
            'reply_markup' => wHook()->user()->getKeyboard(),
        ]);

        $this->requireMessageMeta()->updateAndContinueAction($data);
    }

    /**
     * @throws ValidationException
     */
    public function updateName(Campaign $campaign, int $lastPage): void
    {
        $name = $this->answerText();

        Validator::validate(['name' => $name], ['name' => 'required|string|min:2|max:60']);

        $campaign->update(['name' => $name]);
        wHook()->user()->changeState();

        $this->requireMessageMeta()->updateAndContinueAction(CampaignsFeature::show($campaign, $lastPage));
    }

    /**
     * @throws ValidationException
     */
    public function updateExpiry(Campaign $campaign, int $lastPage): void
    {
        $days = $this->answerText();

        Validator::validate(['days' => $days], ['days' => 'required|integer|min:1|max:3650']);

        $campaign->update(['expires_at' => now()->addDays((int) $days)]);
        wHook()->user()->changeState();

        $this->requireMessageMeta()->updateAndContinueAction(CampaignsFeature::show($campaign, $lastPage));
    }

    private function answerText(): string
    {
        $message = wHook()->update()->message;

        return $message === null ? '' : trim($message->text ?? '');
    }
}

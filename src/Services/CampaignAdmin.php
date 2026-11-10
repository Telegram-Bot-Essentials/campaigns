<?php

namespace TelegramBotEssentials\Campaigns\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use TelegramBotEssentials\Campaigns\Contracts\ClaimMethod;
use TelegramBotEssentials\Campaigns\Enums\PrizeGrantStatus;
use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Campaigns\Models\CampaignPrize;

/**
 * What an admin changes on campaigns and their prizes, shared by the bot's
 * screens and the web panel, so both apply the same limits.
 */
class CampaignAdmin
{
    /** @var list<string> */
    public const NAME_RULES = ['required', 'string', 'min:2', 'max:60'];

    /** @var list<string> */
    public const DAYS_RULES = ['required', 'integer', 'min:1', 'max:3650'];

    /** @var list<string> */
    public const CAP_RULES = ['required', 'integer', 'min:0', 'max:10000000'];

    /**
     * A new campaign, live until $days from now, or for good when null.
     *
     * @throws ValidationException
     */
    public function create(int $botId, string $name, ?string $days = null): Campaign
    {
        $this->validName($name);
        if ($days !== null) {
            $this->validDays($days);
        }

        return Campaign::create([
            'bot_id' => $botId,
            'name' => $name,
            'expires_at' => $days !== null ? now()->addDays((int) $days) : null,
        ]);
    }

    /** @throws ValidationException */
    public function rename(Campaign $campaign, string $name): void
    {
        $this->validName($name);

        $campaign->update(['name' => $name]);
    }

    /**
     * Ends the campaign $days from now.
     *
     * @throws ValidationException
     */
    public function expireIn(Campaign $campaign, string $days): void
    {
        $this->validDays($days);

        $campaign->update(['expires_at' => now()->addDays((int) $days)]);
    }

    public function clearExpiry(Campaign $campaign): void
    {
        $campaign->update(['expires_at' => null]);
    }

    public function toggle(Campaign $campaign): void
    {
        $campaign->update(['active' => ! $campaign->active]);
    }

    /**
     * Only soft-deleted: users it already brought in stay attributed to it,
     * and its link keeps answering "this offer has ended".
     */
    public function delete(Campaign $campaign): void
    {
        $campaign->delete();
    }

    /**
     * 0 means unlimited. Lowering the cap below what is already reserved only
     * stops new grants; the users who already have one keep it.
     *
     * @throws ValidationException
     */
    public function setCap(CampaignPrize $prize, string $cap): void
    {
        Validator::validate(['cap' => $cap], ['cap' => self::CAP_RULES]);

        $prize->update(['max_grants' => (int) $cap === 0 ? null : (int) $cap]);
    }

    public function togglePrize(CampaignPrize $prize): void
    {
        $prize->update(['active' => ! $prize->active]);
    }

    /**
     * Sets how new grants of the prize are received. $config defaults to the
     * method's own; grants already handed out keep the method they got.
     *
     * @param  array<string, mixed>|null  $config
     */
    public function useMethod(CampaignPrize $prize, ClaimMethod $method, ?array $config = null): void
    {
        $prize->update(['method' => $method->key(), 'method_config' => $config ?? $method->defaultConfig()]);
    }

    /** Retries every failed grant of the prize; each is claimed once, so a double tap does no harm. */
    public function retryFailed(CampaignPrize $prize): int
    {
        $granting = app(PrizeGranting::class);
        $grants = $prize->grants()->where('status', PrizeGrantStatus::Failed)->get();

        foreach ($grants as $grant) {
            $granting->retry($grant);
        }

        return $grants->count();
    }

    private function validName(string $name): void
    {
        Validator::validate(['name' => $name], ['name' => self::NAME_RULES]);
    }

    private function validDays(string $days): void
    {
        Validator::validate(['days' => $days], ['days' => self::DAYS_RULES]);
    }
}

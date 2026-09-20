<?php

namespace TelegramBotEssentials\Campaigns\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use TelegramBotEssentials\Campaigns\Enums\MissReason;
use TelegramBotEssentials\Essence\Models\BotUser;

/**
 * @property int $id
 * @property int $bot_id
 * @property int $bot_user_id
 * @property ?int $campaign_id
 * @property string $payload
 * @property MissReason $reason
 */
class CampaignMiss extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'reason' => MissReason::class,
        ];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class)->withTrashed();
    }

    /** @return BelongsTo<BotUser, $this> */
    public function botUser(): BelongsTo
    {
        return $this->belongsTo(BotUser::class);
    }
}

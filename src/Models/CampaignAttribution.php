<?php

namespace TelegramBotEssentials\Campaigns\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use TelegramBotEssentials\Essence\Models\BotUser;

/**
 * @property int $id
 * @property int $bot_id
 * @property int $bot_user_id
 * @property int $campaign_id
 */
class CampaignAttribution extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

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

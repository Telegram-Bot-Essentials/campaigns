<?php

namespace TelegramBotEssentials\Campaigns\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Campaigns\Enums\PrizeGrantStatus;
use TelegramBotEssentials\Essence\Models\BotUser;

/**
 * The ledger row of one prize owed to one attributed user.
 *
 * @property int $id
 * @property int $bot_id
 * @property int $campaign_prize_id
 * @property int $campaign_attribution_id
 * @property int $bot_user_id
 * @property PrizeGrantStatus $status
 * @property int $attempts
 * @property string|null $error
 * @property array<string, mixed>|null $meta
 * @property int|null $invoice_id
 * @property ?Carbon $granted_at
 * @property string|null $order_type
 * @property int|null $order_id
 * @property-read CampaignPrize $prize
 * @property-read CampaignAttribution $attribution
 * @property-read BotUser $botUser
 * @property-read Invoice|null $invoice
 */
class CampaignPrizeGrant extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => PrizeGrantStatus::class,
            'meta' => 'array',
            'granted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CampaignPrize, $this> */
    public function prize(): BelongsTo
    {
        return $this->belongsTo(CampaignPrize::class, 'campaign_prize_id');
    }

    /** @return BelongsTo<CampaignAttribution, $this> */
    public function attribution(): BelongsTo
    {
        return $this->belongsTo(CampaignAttribution::class, 'campaign_attribution_id');
    }

    /** @return BelongsTo<BotUser, $this> */
    public function botUser(): BelongsTo
    {
        return $this->belongsTo(BotUser::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return MorphTo<Model, $this> */
    public function order(): MorphTo
    {
        return $this->morphTo();
    }
}

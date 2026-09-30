<?php

namespace TelegramBotEssentials\Campaigns\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use TelegramBotEssentials\Campaigns\Contracts\PrizeType;

/**
 * A prize attached to a campaign: which type, how it is configured, and how
 * many users may still receive it.
 *
 * @property int $id
 * @property int $bot_id
 * @property int $campaign_id
 * @property string $type
 * @property array<string, mixed> $config
 * @property int|null $max_grants
 * @property int $grants_count
 * @property bool $active
 * @property string $method
 * @property array<string, mixed>|null $method_config
 */
class CampaignPrize extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'active' => 'boolean',
            'method_config' => 'array',
        ];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class)->withTrashed();
    }

    /** @return HasMany<CampaignPrizeGrant, $this> */
    public function grants(): HasMany
    {
        return $this->hasMany(CampaignPrizeGrant::class);
    }

    /** Null when the package that registered the type is no longer installed. */
    public function prizeType(): ?PrizeType
    {
        return prizeTypes()->get($this->type);
    }

    public function describe(): string
    {
        return $this->prizeType()?->describe($this->config) ?? $this->type;
    }

    /** How new grants of this prize are received, read as an admin would. */
    public function describeMethod(): string
    {
        return claimMethods()->get($this->method)?->describe($this->method_config ?? []) ?? $this->method;
    }
}

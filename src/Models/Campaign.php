<?php

namespace TelegramBotEssentials\Campaigns\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * @property int $id
 * @property int $bot_id
 * @property string $name
 * @property string $code
 * @property ?Carbon $expires_at
 * @property bool $active
 * @property-read int|null $attributions_count
 */
class Campaign extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    /**
     * Reserved start-payload prefix. Other packages consume the same
     * BotDeepLinkReceived event (affiliates match a referral code, vshop
     * matches a signed compact token), so campaigns only ever claim
     * payloads that start with this.
     */
    public const PAYLOAD_PREFIX = 'c_';

    private const CODE_LENGTH = 8;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // The code is generated once and is never editable, so a printed
        // link or QR code keeps working for the campaign's whole life.
        static::creating(function (Campaign $campaign) {
            if (empty($campaign->code)) {
                $campaign->code = self::generateCode((int) $campaign->bot_id);
            }
        });
    }

    public static function generateCode(int $botId): string
    {
        do {
            $code = Str::lower(Str::random(self::CODE_LENGTH));
        } while (static::withoutGlobalScopes()->withTrashed()->where('bot_id', $botId)->where('code', $code)->exists());

        return $code;
    }

    /** Value of the /start payload that identifies this campaign. */
    public function payload(): string
    {
        return self::PAYLOAD_PREFIX.$this->code;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** @return HasMany<CampaignAttribution, $this> */
    public function attributions(): HasMany
    {
        return $this->hasMany(CampaignAttribution::class);
    }

    /** @return HasMany<CampaignPrize, $this> */
    public function prizes(): HasMany
    {
        return $this->hasMany(CampaignPrize::class);
    }

    /** @return HasMany<CampaignMiss, $this> */
    public function misses(): HasMany
    {
        return $this->hasMany(CampaignMiss::class);
    }
}

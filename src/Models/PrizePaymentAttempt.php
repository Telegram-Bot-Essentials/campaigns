<?php

namespace TelegramBotEssentials\Campaigns\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use TelegramBotEssentials\Billing\Models\Abstract\PaymentAttempt;
use TelegramBotEssentials\Billing\Models\Invoice;

/**
 * The "payment method" of an order handed out as a campaign prize. No money
 * moves; the attempt only ties the invoice to the grant that paid it.
 *
 * It is also the sale marker: an invoice paid by this attempt is a giveaway,
 * not a sale, and revenue figures leave it out (see `isPrizeInvoice()`).
 *
 * It is created in code by `PrizeSettlement` and never registered as a
 * `Gateway`, so no invoice ever offers it as a way to pay.
 *
 * @property int $id
 * @property int $bot_id
 * @property int $campaign_prize_grant_id
 * @property string $amount
 * @property string|null $status
 */
class PrizePaymentAttempt extends PaymentAttempt
{
    use BelongsToTenant;

    /** @return BelongsTo<CampaignPrizeGrant, $this> */
    public function grant(): BelongsTo
    {
        return $this->belongsTo(CampaignPrizeGrant::class, 'campaign_prize_grant_id');
    }

    protected function attemptSucceedHook(): void {}

    protected function attemptFailedHook(): void {}

    /** True when the invoice was settled as a campaign prize instead of being paid for. */
    public static function isPrizeInvoice(Invoice $invoice): bool
    {
        return $invoice->getAttribute('payment_attempt_type') === (new self)->getMorphClass();
    }
}

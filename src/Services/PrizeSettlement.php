<?php

namespace TelegramBotEssentials\Campaigns\Services;

use TelegramBotEssentials\Billing\Models\Abstract\Order;
use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Campaigns\Models\CampaignPrizeGrant;
use TelegramBotEssentials\Campaigns\Models\PrizePaymentAttempt;

/**
 * For prizes that are an order of some other package (a service, a plan):
 * creates the invoice and settles it with a `PrizePaymentAttempt`, so the
 * order's own paid hook runs exactly as it does for a paying customer.
 * The invoice is settled at price 0 (its `original_price` is kept), so the
 * giveaway never shows up as revenue or earns commission.
 *
 * A prize type builds the order and calls `pay()`. On a retry it should
 * reuse `$grant->order` instead of creating another one.
 */
class PrizeSettlement
{
    /**
     * Idempotent for a grant: a retry after the order's paid hook threw finds
     * the invoice already paid and runs only the hook again, so the user never
     * gets a second invoice or a second attempt.
     *
     * Any exception from the order's paid hook is left to the caller, which
     * records the grant as failed.
     */
    public function pay(Order $order, CampaignPrizeGrant $grant): Invoice
    {
        if ($grant->order_id === null) {
            $grant->order()->associate($order);
            $grant->save();
        }

        $invoice = $grant->invoice;

        if ($invoice === null) {
            $existing = $order->invoice;
            $invoice = $existing instanceof Invoice ? $existing : billing()->createInvoice($order);
        }

        if ($grant->invoice_id !== $invoice->getKey()) {
            $grant->invoice()->associate($invoice);
            $grant->save();
        }

        if ($invoice->getAttribute('status') === 'paid') {
            $order->invoicePaidHook();

            return $invoice;
        }

        // A prize is free. Settling at price 0 keeps `original_price` for the
        // record, and makes every reader of the paid price (affiliate commission,
        // revenue reports, offers) see the giveaway for what it is without
        // each of them having to know about prizes.
        $invoice->forceFill(['price' => '0'])->save();

        $attempt = PrizePaymentAttempt::create([
            'campaign_prize_grant_id' => $grant->getKey(),
            'amount' => $invoice->getAttribute('original_price'),
        ]);

        billing()->attemptPayment($invoice, $attempt);
        $attempt->attemptSucceed();

        return $invoice;
    }
}

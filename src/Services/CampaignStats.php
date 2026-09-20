<?php

namespace TelegramBotEssentials\Campaigns\Services;

use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\UserWallet\Models\CreditOrder;

class CampaignStats
{
    /**
     * @return array{
     *     joined: int,
     *     misses: int,
     *     missesByReason: array<string, int>,
     *     paidUsers: int|null,
     *     revenue: string|null
     * }
     */
    public static function for(Campaign $campaign): array
    {
        $missesByReason = [];
        foreach ($campaign->misses()->selectRaw('reason, count(*) as total')->groupBy('reason')->get() as $row) {
            $missesByReason[$row->reason->value] = self::toInt($row->getAttribute('total'));
        }

        $revenue = self::revenue($campaign);

        return [
            'joined' => $campaign->attributions()->count(),
            'misses' => array_sum($missesByReason),
            'missesByReason' => $missesByReason,
            'paidUsers' => $revenue['paidUsers'] ?? null,
            'revenue' => $revenue['revenue'] ?? null,
        ];
    }

    private static function toDecimalString(mixed $value): string
    {
        return is_numeric($value) ? (string) $value : '0';
    }

    private static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Paid-user and revenue figures, worked out live from paid invoices of
     * the users the campaign brought in. Null when billing is not installed.
     *
     * Nothing is stored: an invoice that is revoked stops being `paid`, and
     * a soft-deleted invoice is left out, so the figures follow the invoices
     * without a listener or a ledger table. Wallet top-ups are excluded,
     * since money moved into a wallet is not a sale (only when user-wallet
     * is installed, so it stays an optional dependency).
     *
     * @return array{paidUsers: int, revenue: string}|null
     */
    private static function revenue(Campaign $campaign): ?array
    {
        if (! class_exists(Invoice::class)) {
            return null;
        }

        $invoices = Invoice::query()
            ->where('status', 'paid')
            ->whereIn('bot_user_id', $campaign->attributions()->select('bot_user_id'));

        if (class_exists(CreditOrder::class)) {
            $invoices->where('payable_type', '!=', (new CreditOrder)->getMorphClass());
        }

        return [
            'paidUsers' => (clone $invoices)->distinct()->count('bot_user_id'),
            'revenue' => self::toDecimalString((clone $invoices)->sum('price')),
        ];
    }
}

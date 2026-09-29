<?php

declare(strict_types=1);

namespace TelegramBotEssentials\Campaigns\Tests\Support;

use Illuminate\Support\Carbon;
use RuntimeException;
use TelegramBotEssentials\Billing\Models\Abstract\Order;

/** An order of some other package: provisions on paid, and can be told to throw doing so. */
class FakeOrder extends Order
{
    protected $table = 'fake_orders';

    public static int $provisioned = 0;

    public static bool $fail = false;

    public function getPaidAtAttribute(): ?Carbon
    {
        return $this->invoice?->paid_at;
    }

    public function getAmountAttribute(): string
    {
        return '50000';
    }

    public function getDescriptionAttribute(): string
    {
        return 'fake order';
    }

    public function invoicePaidHook(): void
    {
        if (self::$fail) {
            throw new RuntimeException('provisioning failed');
        }

        self::$provisioned++;
    }

    public function cancelOrderHook(): void {}
}

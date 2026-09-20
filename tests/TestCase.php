<?php

declare(strict_types=1);

namespace TelegramBotEssentials\Campaigns\Tests;

use TelegramBotEssentials\Billing\TbeBillingServiceProvider;
use TelegramBotEssentials\Campaigns\TbeCampaignsServiceProvider;
use TelegramBotEssentials\Essence\Testing\TestCase as EssenceTestCase;
use TelegramBotEssentials\Settings\TbeSettingsServiceProvider;
use TelegramBotEssentials\UserManagement\TbeUserManagementServiceProvider;
use TelegramBotEssentials\UserWallet\TbeUserWalletServiceProvider;

abstract class TestCase extends EssenceTestCase
{
    /**
     * The companions are all installed here so the optional integrations
     * (revenue from billing, top-up exclusion from user-wallet, the admin
     * user-list section and filter from user-management) can be tested.
     */
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            TbeSettingsServiceProvider::class,
            TbeBillingServiceProvider::class,
            TbeUserWalletServiceProvider::class,
            TbeUserManagementServiceProvider::class,
            TbeCampaignsServiceProvider::class,
        ]);
    }
}

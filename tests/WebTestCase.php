<?php

declare(strict_types=1);

namespace TelegramBotEssentials\Campaigns\Tests;

use TelegramBotEssentials\Billing\TbeBillingServiceProvider;
use TelegramBotEssentials\Campaigns\TbeCampaignsServiceProvider;
use TelegramBotEssentials\Essence\Testing\WebTestCase as EssenceWebTestCase;
use TelegramBotEssentials\Settings\TbeSettingsServiceProvider;
use TelegramBotEssentials\UserManagement\TbeUserManagementServiceProvider;
use TelegramBotEssentials\UserWallet\TbeUserWalletServiceProvider;

abstract class WebTestCase extends EssenceWebTestCase
{
    /** As TestCase: user-wallet registers the wallet prize type. */
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

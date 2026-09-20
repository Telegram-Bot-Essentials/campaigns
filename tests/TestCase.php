<?php

declare(strict_types=1);

namespace TelegramBotEssentials\Campaigns\Tests;

use TelegramBotEssentials\Essence\Testing\TestCase as EssenceTestCase;
use TelegramBotEssentials\Campaigns\TbeCampaignsServiceProvider;

abstract class TestCase extends EssenceTestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            TbeCampaignsServiceProvider::class,
        ]);
    }
}

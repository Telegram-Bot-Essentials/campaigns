<?php

use TelegramBotEssentials\Campaigns\Services\ClaimMethods;
use TelegramBotEssentials\Campaigns\Services\PrizeTypes;

if (! function_exists('prizeTypes')) {
    function prizeTypes(): PrizeTypes
    {
        return app(PrizeTypes::class);
    }
}

if (! function_exists('claimMethods')) {
    function claimMethods(): ClaimMethods
    {
        return app(ClaimMethods::class);
    }
}

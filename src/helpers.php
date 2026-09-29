<?php

use TelegramBotEssentials\Campaigns\Services\PrizeTypes;

if (! function_exists('prizeTypes')) {
    function prizeTypes(): PrizeTypes
    {
        return app(PrizeTypes::class);
    }
}

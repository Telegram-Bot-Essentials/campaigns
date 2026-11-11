<?php

namespace TelegramBotEssentials\Campaigns\Support;

use TelegramBotEssentials\Campaigns\Contracts\ClaimMethod;
use TelegramBotEssentials\Campaigns\Contracts\PrizeType;
use TelegramBotEssentials\Essence\Contracts\Labelled;
use TelegramBotEssentials\Essence\Support\Label;

/**
 * A prize type's or claim method's name for the bot and the panel. Types that
 * implement Labelled give their emoji and icon apart; others keep label().
 */
final class CampaignLabels
{
    public static function of(PrizeType|ClaimMethod $thing): Label
    {
        return $thing instanceof Labelled ? $thing->labelled() : Label::of($thing->label());
    }
}

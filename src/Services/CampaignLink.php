<?php

namespace TelegramBotEssentials\Campaigns\Services;

use Illuminate\Support\Facades\Cache;
use TelegramBotEssentials\Campaigns\Models\Campaign;

class CampaignLink
{
    public static function for(Campaign $campaign): string
    {
        return 'https://t.me/'.self::botUsername().'?start='.$campaign->payload();
    }

    /**
     * The bots table has no username column, so it is asked of Telegram once
     * and remembered per bot. An empty answer is never remembered.
     */
    private static function botUsername(): string
    {
        $key = 'tbe-campaigns.bot-username.'.wHook()->bot()->id;

        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $username = (string) wHook()->api()->getMe()->username;

        if ($username !== '') {
            Cache::put($key, $username, now()->addDay());
        }

        return $username;
    }
}

<?php

namespace TelegramBotEssentials\Campaigns\Telegram\Features\Admin;

use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Keyboard\Keyboard;
use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Campaigns\Services\CampaignLink;
use TelegramBotEssentials\Campaigns\Services\CampaignQr;
use TelegramBotEssentials\Campaigns\Services\CampaignStats;
use TelegramBotEssentials\Essence\Exceptions\InvalidPageNumber;
use TelegramBotEssentials\Essence\Services\TelegramPaginator;
use TelegramBotEssentials\Essence\Telegram\TelegramResponse;

class CampaignsFeature
{
    public static string $type = 'CAMPAIGNS';

    /**
     * @throws InvalidPageNumber
     */
    public static function menu(int $page = 1, int $currentPage = 0): TelegramResponse
    {
        $campaigns = Campaign::query()
            ->where('bot_id', wHook()->bot()->id)
            ->withCount('attributions')
            ->orderByDesc('id')
            ->paginate(perPage: 10, page: $page);

        TelegramPaginator::validatePageNumber($page, $currentPage, $campaigns);

        $replyMarkup = Keyboard::make()->inline();

        $replyMarkup->row([
            Keyboard::inlineButton([
                'text' => __('tbe-campaigns::campaigns.main.keys.create'),
                'callback_data' => encodeCallback(self::$type, 'create', [$page]),
            ]),
        ]);

        if (count($campaigns) == 0) {
            return new TelegramResponse(
                text: __('tbe-campaigns::campaigns.main.text.empty'),
                replyMarkup: $replyMarkup,
                parseMode: 'HTML'
            );
        }

        foreach ($campaigns as $campaign) {
            $replyMarkup->row([
                Keyboard::inlineButton([
                    'text' => self::listLabel($campaign),
                    'callback_data' => encodeCallback(self::$type, 'show', [$campaign->id, $page]),
                ]),
            ]);
        }

        $replyMarkup->row(TelegramPaginator::makeNavigationButtonsRow(self::$type, $page, $campaigns->lastPage()));

        return new TelegramResponse(
            text: __('tbe-campaigns::campaigns.main.text.list'),
            replyMarkup: $replyMarkup,
            parseMode: 'HTML'
        );
    }

    private static function listLabel(Campaign $campaign): string
    {
        $badge = self::isLive($campaign) ? '✅' : '🚫';
        $joined = $campaign->attributions_count ?? 0;

        return __('tbe-campaigns::campaigns.main.text.listLabel', [
            'badge' => $badge,
            'name' => $campaign->name,
            'joined' => $joined,
        ]);
    }

    public static function show(Campaign $campaign, int $lastPage = 1): TelegramResponse
    {
        $stats = CampaignStats::for($campaign);

        $text = __('tbe-campaigns::campaigns.main.text.show', [
            'name' => e($campaign->name),
            'link' => CampaignLink::for($campaign),
            'status' => self::statusLabel($campaign),
            'expiresAt' => $campaign->expires_at?->format('Y-m-d H:i') ?? __('tbe-campaigns::campaigns.main.never'),
            'stats' => self::statsText($stats),
        ]);

        $replyMarkup = Keyboard::make()->inline();

        $replyMarkup->row([
            Keyboard::inlineButton([
                'text' => $campaign->active
                    ? __('tbe-campaigns::campaigns.main.keys.disable')
                    : __('tbe-campaigns::campaigns.main.keys.enable'),
                'callback_data' => encodeCallback(self::$type, 'toggle', [$campaign->id, $lastPage]),
            ]),
            Keyboard::inlineButton([
                'text' => __('tbe-campaigns::campaigns.main.keys.qr'),
                'callback_data' => encodeCallback(self::$type, 'qr', [$campaign->id, $lastPage]),
            ]),
        ]);

        $replyMarkup->row([
            Keyboard::inlineButton([
                'text' => __('tbe-campaigns::campaigns.main.keys.editName'),
                'callback_data' => encodeCallback(self::$type, 'editName', [$campaign->id, $lastPage]),
            ]),
        ]);

        $expiryRow = [
            Keyboard::inlineButton([
                'text' => __('tbe-campaigns::campaigns.main.keys.editExpiry'),
                'callback_data' => encodeCallback(self::$type, 'editExpiry', [$campaign->id, $lastPage]),
            ]),
        ];

        if ($campaign->expires_at !== null) {
            $expiryRow[] = Keyboard::inlineButton([
                'text' => __('tbe-campaigns::campaigns.main.keys.clearExpiry'),
                'callback_data' => encodeCallback(self::$type, 'clearExpiry', [$campaign->id, $lastPage]),
            ]);
        }

        $replyMarkup->row($expiryRow);

        $replyMarkup->row([
            inlineConfirmationKey(
                keyText: __('tbe-campaigns::campaigns.main.keys.delete'),
                targetCallbackData: encodeCallback(self::$type, 'delete', [$campaign->id, $lastPage]),
                backCallbackData: encodeCallback(self::$type, 'show', [$campaign->id, $lastPage]),
                confirmationText: __('tbe-campaigns::campaigns.main.text.confirmDelete', ['name' => e($campaign->name)]),
            ),
        ]);

        $replyMarkup->row([
            Keyboard::inlineButton([
                'text' => __('tbe-campaigns::campaigns.main.keys.backToList'),
                'callback_data' => encodeCallback(self::$type, 'start', [$lastPage]),
            ]),
        ]);

        return new TelegramResponse(
            text: $text,
            replyMarkup: $replyMarkup,
            parseMode: 'HTML'
        );
    }

    /** The link as a QR image, sent as a new message (a photo cannot replace a text screen). */
    public static function qr(Campaign $campaign, int $lastPage = 1): TelegramResponse
    {
        $link = CampaignLink::for($campaign);

        $replyMarkup = Keyboard::make()->inline()->row([
            Keyboard::inlineButton([
                'text' => __('tbe-campaigns::campaigns.main.keys.backToCampaign'),
                'callback_data' => encodeCallback(self::$type, 'show', [$campaign->id, $lastPage]),
            ]),
        ]);

        return new TelegramResponse(
            text: __('tbe-campaigns::campaigns.main.text.qr', [
                'name' => e($campaign->name),
                'link' => $link,
            ]),
            replyMarkup: $replyMarkup,
            parseMode: 'HTML',
            photo: InputFile::createFromContents(CampaignQr::png($link), 'campaign-qr.png'),
        );
    }

    private static function isLive(Campaign $campaign): bool
    {
        return $campaign->active && ! $campaign->isExpired();
    }

    private static function statusLabel(Campaign $campaign): string
    {
        return match (true) {
            ! $campaign->active => __('tbe-campaigns::campaigns.main.disabled'),
            $campaign->isExpired() => __('tbe-campaigns::campaigns.main.expired'),
            default => __('tbe-campaigns::campaigns.main.enabled'),
        };
    }

    /**
     * @param  array{joined: int, misses: int, missesByReason: array<string, int>, paidUsers: int|null, revenue: string|null}  $stats
     */
    private static function statsText(array $stats): string
    {
        $lines = [
            __('tbe-campaigns::campaigns.main.stats.joined', ['count' => $stats['joined']]),
        ];

        if ($stats['misses'] > 0) {
            $breakdown = collect($stats['missesByReason'])
                ->map(fn (int $total, string $reason) => __('tbe-campaigns::campaigns.main.reasons.'.$reason).' '.$total)
                ->implode(', ');

            $lines[] = __('tbe-campaigns::campaigns.main.stats.misses', [
                'count' => $stats['misses'],
                'breakdown' => $breakdown,
            ]);
        }

        if ($stats['paidUsers'] !== null && $stats['revenue'] !== null) {
            $lines[] = __('tbe-campaigns::campaigns.main.stats.paidUsers', ['count' => $stats['paidUsers']]);
            $lines[] = __('tbe-campaigns::campaigns.main.stats.revenue', ['amount' => currency()->priceFormat($stats['revenue'])]);
        }

        return implode("\r\n", $lines);
    }
}

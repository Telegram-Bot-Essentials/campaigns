<?php

namespace TelegramBotEssentials\Campaigns\Telegram\Features\Admin;

use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Keyboard\Keyboard;
use TelegramBotEssentials\Campaigns\Enums\PrizeGrantStatus;
use TelegramBotEssentials\Campaigns\Models\Campaign;
use TelegramBotEssentials\Campaigns\Models\CampaignPrize;
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
                'text' => __('tbe-campaigns::prizes.keys.prizes'),
                'callback_data' => encodeCallback(self::$type, 'prizes', [$campaign->id, $lastPage]),
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

    /** The prizes a campaign hands out, with how far each cap is used. */
    public static function prizes(Campaign $campaign, int $lastPage = 1): TelegramResponse
    {
        $prizes = $campaign->prizes()->orderBy('id')->get();

        $replyMarkup = Keyboard::make()->inline();

        foreach ($prizes as $prize) {
            $replyMarkup->row([
                Keyboard::inlineButton([
                    'text' => self::prizeLabel($prize),
                    'callback_data' => encodeCallback(self::$type, 'prize', [$prize->id, $lastPage]),
                ]),
            ]);
        }

        $replyMarkup->row([
            Keyboard::inlineButton([
                'text' => __('tbe-campaigns::prizes.keys.add'),
                'callback_data' => encodeCallback(self::$type, 'addPrize', [$campaign->id, $lastPage]),
            ]),
        ]);

        $replyMarkup->row([
            Keyboard::inlineButton([
                'text' => __('tbe-campaigns::campaigns.main.keys.backToCampaign'),
                'callback_data' => encodeCallback(self::$type, 'show', [$campaign->id, $lastPage]),
            ]),
        ]);

        return new TelegramResponse(
            text: __($prizes->isEmpty() ? 'tbe-campaigns::prizes.admin.empty' : 'tbe-campaigns::prizes.admin.list', ['name' => e($campaign->name)]),
            replyMarkup: $replyMarkup,
            parseMode: 'HTML'
        );
    }

    /** The registered prize types to pick from. */
    public static function addPrize(Campaign $campaign, int $lastPage = 1): TelegramResponse
    {
        $replyMarkup = Keyboard::make()->inline();

        foreach (prizeTypes()->all() as $type) {
            $replyMarkup->row([
                Keyboard::inlineButton([
                    'text' => $type->label(),
                    'callback_data' => encodeCallback(self::$type, 'pickPrizeType', [$campaign->id, $type->key(), $lastPage]),
                ]),
            ]);
        }

        $replyMarkup->row([
            Keyboard::inlineButton([
                'text' => __('tbe-campaigns::campaigns.main.keys.backToCampaign'),
                'callback_data' => encodeCallback(self::$type, 'prizes', [$campaign->id, $lastPage]),
            ]),
        ]);

        return new TelegramResponse(
            text: __(prizeTypes()->all() === [] ? 'tbe-campaigns::prizes.admin.noTypes' : 'tbe-campaigns::prizes.admin.pickType'),
            replyMarkup: $replyMarkup,
            parseMode: 'HTML'
        );
    }

    public static function pickMethod(CampaignPrize $prize, int $lastPage = 1): TelegramResponse
    {
        $replyMarkup = Keyboard::make()->inline();

        foreach (claimMethods()->all() as $method) {
            $replyMarkup->row([
                Keyboard::inlineButton([
                    'text' => ($method->key() === $prize->method ? '✅ ' : '').$method->label(),
                    'callback_data' => encodeCallback(self::$type, 'chooseMethod', [$prize->id, $method->key(), $lastPage]),
                ]),
            ]);
        }

        $replyMarkup->row([
            Keyboard::inlineButton([
                'text' => __('tbe-campaigns::claims.keys.back'),
                'callback_data' => encodeCallback(self::$type, 'prize', [$prize->id, $lastPage]),
            ]),
        ]);

        return new TelegramResponse(
            text: __('tbe-campaigns::claims.admin.pick'),
            replyMarkup: $replyMarkup,
            parseMode: 'HTML'
        );
    }

    public static function prize(CampaignPrize $prize, int $lastPage = 1): TelegramResponse
    {
        $failed = $prize->grants()->where('status', PrizeGrantStatus::Failed)->count();
        $pending = $prize->grants()->where('status', PrizeGrantStatus::Pending)->count();
        $granted = $prize->grants()->where('status', PrizeGrantStatus::Granted)->count();
        $lost = $prize->grants()->where('status', PrizeGrantStatus::Lost)->count();

        $replyMarkup = Keyboard::make()->inline();

        $replyMarkup->row([
            Keyboard::inlineButton([
                'text' => $prize->active ? __('tbe-campaigns::prizes.keys.disable') : __('tbe-campaigns::prizes.keys.enable'),
                'callback_data' => encodeCallback(self::$type, 'togglePrize', [$prize->id, $lastPage]),
            ]),
            Keyboard::inlineButton([
                'text' => __('tbe-campaigns::prizes.keys.editCap'),
                'callback_data' => encodeCallback(self::$type, 'editCap', [$prize->id, $lastPage]),
            ]),
        ]);

        $replyMarkup->row([
            Keyboard::inlineButton([
                'text' => __('tbe-campaigns::claims.keys.method', ['method' => $prize->describeMethod()]),
                'callback_data' => encodeCallback(self::$type, 'pickMethod', [$prize->id, $lastPage]),
            ]),
        ]);

        if ($failed > 0) {
            $replyMarkup->row([
                Keyboard::inlineButton([
                    'text' => __('tbe-campaigns::prizes.keys.retry', ['count' => $failed]),
                    'callback_data' => encodeCallback(self::$type, 'retryPrize', [$prize->id, $lastPage]),
                ]),
            ]);
        }

        $replyMarkup->row([
            Keyboard::inlineButton([
                'text' => __('tbe-campaigns::campaigns.main.keys.backToCampaign'),
                'callback_data' => encodeCallback(self::$type, 'prizes', [$prize->campaign_id, $lastPage]),
            ]),
        ]);

        return new TelegramResponse(
            text: __('tbe-campaigns::prizes.admin.show', [
                'prize' => e($prize->describe()),
                'status' => $prize->active ? __('tbe-campaigns::campaigns.main.enabled') : __('tbe-campaigns::campaigns.main.disabled'),
                'cap' => $prize->max_grants === null ? __('tbe-campaigns::campaigns.main.never') : (string) $prize->max_grants,
                'used' => $prize->grants_count,
                'granted' => $granted,
                'pending' => $pending,
                'failed' => $failed,
                'lost' => $lost,
                'method' => e($prize->describeMethod()),
            ]),
            replyMarkup: $replyMarkup,
            parseMode: 'HTML'
        );
    }

    private static function prizeLabel(CampaignPrize $prize): string
    {
        return __('tbe-campaigns::prizes.admin.label', [
            'badge' => $prize->active ? '✅' : '🚫',
            'prize' => $prize->describe(),
            'used' => $prize->grants_count,
            'cap' => $prize->max_grants ?? '∞',
        ]);
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
     * @param  array{joined: int, misses: int, missesByReason: array<string, int>, paidUsers: int, revenue: string}  $stats
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

        $lines[] = __('tbe-campaigns::campaigns.main.stats.paidUsers', ['count' => $stats['paidUsers']]);
        $lines[] = __('tbe-campaigns::campaigns.main.stats.revenue', ['amount' => currency()->priceFormat($stats['revenue'])]);

        return implode("\r\n", $lines);
    }
}

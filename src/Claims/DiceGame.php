<?php

namespace TelegramBotEssentials\Campaigns\Claims;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Telegram\Bot\Objects\Message;
use TelegramBotEssentials\Campaigns\Enums\PrizeGrantStatus;
use TelegramBotEssentials\Campaigns\Models\CampaignPrizeGrant;
use TelegramBotEssentials\Essence\Models\BotUser;

/**
 * The rules of a dice throw: which prize it is for, whether it counts, and
 * what it scored. No Telegram output happens here; `DiceThrowMatcher` tells
 * the user.
 */
class DiceGame
{
    public const EMOJI = '🎲';

    /**
     * Any of these on a message means it was not thrown by the user just now:
     * a forwarded 🎲 keeps its value, so a user could forward a six.
     */
    private const NOT_FIRST_HAND = [
        'forward_origin', 'forward_from', 'forward_from_chat', 'forward_from_message_id',
        'forward_date', 'forward_sender_name', 'forward_signature', 'is_automatic_forward', 'via_bot',
    ];

    /** A Telegram field as a whole number; 0 when it is missing or not numeric. */
    public static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * The user's dice games waiting for a throw.
     *
     * @return Collection<int, CampaignPrizeGrant>
     */
    public function open(BotUser $user): Collection
    {
        return CampaignPrizeGrant::query()
            ->where('bot_user_id', $user->id)
            ->where('method', DiceClaim::KEY)
            ->where('status', PrizeGrantStatus::Pending)
            ->whereNotNull('prompt_message_id')
            ->get();
    }

    /**
     * The game a throw is for: the one whose prize message or prompt it
     * replies to, or the only open game when it is not a reply at all. Null
     * when that is ambiguous.
     *
     * @param  Collection<int, CampaignPrizeGrant>  $open
     */
    public function locate(Collection $open, Message $message): ?CampaignPrizeGrant
    {
        $repliedTo = data_get($message->get('reply_to_message'), 'message_id');

        if ($repliedTo !== null) {
            return $open->first(fn (CampaignPrizeGrant $grant) => in_array($repliedTo, [$grant->message_id, $grant->prompt_message_id], true));
        }

        return $open->count() === 1 ? $open->first() : null;
    }

    /** Why the message cannot count as a throw for the grant, or null when it can. */
    public function rejection(Message $message, CampaignPrizeGrant $grant): ?string
    {
        foreach (self::NOT_FIRST_HAND as $field) {
            if ($message->has($field)) {
                return 'forwarded';
            }
        }

        if (data_get($message->get('dice'), 'emoji') !== self::EMOJI) {
            return 'wrongEmoji';
        }

        // Thrown before the bot asked: message ids only grow within a chat.
        if (self::int($message->get('message_id')) <= (int) $grant->prompt_message_id) {
            return 'stale';
        }

        return null;
    }

    /**
     * Counts one throw and says how the game stands: `won`, `miss` (tries
     * left), `lost` (that was the last try) or `ignored` when the throw is a
     * repeat or the game is over. The row is locked, so a redelivered update
     * cannot count twice.
     *
     * @return array{0: string, 1: int} the outcome and the tries left
     */
    public function play(CampaignPrizeGrant $grant, int $messageId, int $value): array
    {
        return DB::transaction(function () use ($grant, $messageId, $value) {
            $locked = CampaignPrizeGrant::query()->whereKey($grant->id)->lockForUpdate()->first();

            if ($locked === null
                || $locked->status !== PrizeGrantStatus::Pending
                || $locked->prompt_message_id === null
                || $messageId <= (int) $locked->last_throw_id) {
                return ['ignored', 0];
            }

            $locked->forceFill(['plays' => $locked->plays + 1, 'last_throw_id' => $messageId])->save();

            $config = $locked->method_config ?? [];

            if (in_array($value, DiceClaim::numbers($config), true)) {
                return ['won', 0];
            }

            $left = DiceClaim::tries($config) - $locked->plays;

            return [$left > 0 ? 'miss' : 'lost', max(0, $left)];
        });
    }
}

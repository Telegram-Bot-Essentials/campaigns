<?php

namespace TelegramBotEssentials\Campaigns\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Telegram\Bot\Keyboard\Keyboard;
use TelegramBotEssentials\Campaigns\Enums\PrizeGrantStatus;
use TelegramBotEssentials\Campaigns\Models\CampaignAttribution;
use TelegramBotEssentials\Campaigns\Models\CampaignPrize;
use TelegramBotEssentials\Campaigns\Models\CampaignPrizeGrant;
use TelegramBotEssentials\Campaigns\Telegram\CallbackQueries\Member\PrizeClaimQuery;
use Throwable;

/**
 * The ledger side of prizes: reserving one for a newly attributed user,
 * handing it over on claim, and retrying a failed hand-over.
 *
 * The prize type's `grant()` runs outside any transaction. It talks to
 * panels and other outside systems, and inside a transaction a throw would
 * roll back the `failed` row that records it. The claim is guarded instead
 * by a short locked transaction that flips the row to `processing`, so two
 * taps of the button hand the prize over once.
 */
class PrizeGranting
{
    /**
     * Reserves every live prize of the attribution's campaign for the user,
     * hands over the ones that need no claim, and tells the user about all
     * of them in one message. Safe to run twice for the same attribution.
     */
    public function issue(CampaignAttribution $attribution): void
    {
        $prizes = CampaignPrize::query()
            ->where('campaign_id', $attribution->campaign_id)
            ->where('active', true)
            ->orderBy('id')
            ->get();

        $lines = [];
        $claimable = [];

        foreach ($prizes as $prize) {
            $grant = $this->reserve($prize, $attribution);

            if ($grant === null) {
                continue;
            }

            $type = $prize->prizeType();

            if ($type !== null && ! $type->requiresClaim()) {
                $status = $this->claim($grant);
                $lines[] = $status === PrizeGrantStatus::Granted
                    ? __('tbe-campaigns::prizes.notify.granted', ['prize' => $prize->describe()])
                    : __('tbe-campaigns::prizes.notify.instantFailed', ['prize' => $prize->describe()]);

                continue;
            }

            $lines[] = __('tbe-campaigns::prizes.notify.claimable', ['prize' => $prize->describe()]);
            $claimable[] = $grant;
        }

        if ($lines !== []) {
            $this->notify($attribution, $lines, $claimable);
        }
    }

    /**
     * Takes one slot of the prize's cap and writes the pending grant, or
     * returns null when the cap is spent or the grant already exists.
     * The slot is taken with a conditional UPDATE, so concurrent joins can
     * never hand out more than the cap.
     */
    private function reserve(CampaignPrize $prize, CampaignAttribution $attribution): ?CampaignPrizeGrant
    {
        try {
            return DB::transaction(function () use ($prize, $attribution) {
                $taken = CampaignPrize::query()
                    ->whereKey($prize->id)
                    ->where('active', true)
                    ->where(fn ($query) => $query->whereNull('max_grants')->orWhereColumn('grants_count', '<', 'max_grants'))
                    ->increment('grants_count');

                if ($taken === 0) {
                    tbeLog('campaigns')->info('Prize #{prize_id} has no grants left', ['prize_id' => $prize->id]);

                    return null;
                }

                return CampaignPrizeGrant::create([
                    'bot_id' => $attribution->bot_id,
                    'campaign_prize_id' => $prize->id,
                    'campaign_attribution_id' => $attribution->id,
                    'bot_user_id' => $attribution->bot_user_id,
                    'status' => PrizeGrantStatus::Pending,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // The transaction rolled the slot back with it: this user already has this prize.
            return null;
        }
    }

    /** The user taps "claim". A no-op unless the grant is still pending. */
    public function claim(CampaignPrizeGrant $grant): PrizeGrantStatus
    {
        return $this->run($grant, PrizeGrantStatus::Pending);
    }

    /** An admin retries a failed grant. A no-op unless the grant is failed. */
    public function retry(CampaignPrizeGrant $grant): PrizeGrantStatus
    {
        return $this->run($grant, PrizeGrantStatus::Failed);
    }

    private function run(CampaignPrizeGrant $grant, PrizeGrantStatus $from): PrizeGrantStatus
    {
        $locked = DB::transaction(function () use ($grant, $from) {
            $fresh = CampaignPrizeGrant::query()->whereKey($grant->id)->lockForUpdate()->first();

            if ($fresh === null || $fresh->status !== $from) {
                return null;
            }

            $fresh->status = PrizeGrantStatus::Processing;
            $fresh->attempts++;
            $fresh->save();

            return $fresh;
        });

        if ($locked === null) {
            return $grant->fresh()->status ?? $grant->status;
        }

        $prize = $locked->prize;
        $type = $prize->prizeType();
        $user = $locked->botUser;

        try {
            if ($type === null) {
                throw new \RuntimeException(sprintf('Prize type "%s" is not registered.', $prize->type));
            }

            // runForUser() hands any exception to essence's handler, which tells
            // the user "something went wrong" and rethrows a bare HTTP 203, losing
            // the reason. Catch it inside so the ledger keeps the real message.
            $failure = null;
            wHook()->runForUser($user, function () use ($type, $user, $prize, $locked, &$failure) {
                try {
                    $type->grant($user, $prize->config, $locked);
                } catch (Throwable $e) {
                    $failure = $e;
                }
            });

            if ($failure !== null) {
                throw $failure;
            }

            $locked->refresh();
            $locked->forceFill(['status' => PrizeGrantStatus::Granted, 'error' => null, 'granted_at' => now()])->save();

            tbeLog('campaigns')->info('Prize #{prize_id} granted (grant #{grant_id})', [
                'prize_id' => $prize->id,
                'grant_id' => $locked->id,
            ]);
        } catch (Throwable $e) {
            $locked->refresh();
            $locked->forceFill(['status' => PrizeGrantStatus::Failed, 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();

            tbeLog('campaigns')->error('Prize #{prize_id} failed (grant #{grant_id}): '.$e->getMessage(), [
                'prize_id' => $prize->id,
                'grant_id' => $locked->id,
                'exception' => $e,
            ]);
        }

        return $locked->status;
    }

    /**
     * @param  list<string>  $lines
     * @param  list<CampaignPrizeGrant>  $claimable
     */
    private function notify(CampaignAttribution $attribution, array $lines, array $claimable): void
    {
        $markup = Keyboard::make()->inline();

        foreach ($claimable as $grant) {
            $markup->row([
                Keyboard::inlineButton([
                    'text' => __('tbe-campaigns::prizes.keys.claim', ['prize' => $grant->prize->describe()]),
                    'callback_data' => encodeCallback(PrizeClaimQuery::TYPE, 'claim', [$grant->id]),
                ]),
            ]);
        }

        try {
            wHook()->api()->sendMessage(array_filter([
                'chat_id' => wHook()->user()->telegramUser->peer_id,
                'text' => __('tbe-campaigns::prizes.notify.title')."\r\n\r\n".implode("\r\n", $lines),
                'reply_markup' => $claimable === [] ? null : $markup,
            ]));
        } catch (Throwable $e) {
            // The grants are recorded; a failed notification must not undo the join.
            tbeLog('campaigns')->error('Could not tell attribution #{attribution_id} about their prizes: '.$e->getMessage(), [
                'attribution_id' => $attribution->id,
                'exception' => $e,
            ]);
        }
    }
}

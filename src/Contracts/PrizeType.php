<?php

namespace TelegramBotEssentials\Campaigns\Contracts;

use TelegramBotEssentials\Campaigns\Models\CampaignPrizeGrant;
use TelegramBotEssentials\Essence\Forms\Form;
use TelegramBotEssentials\Essence\Models\BotUser;

/**
 * A kind of prize a project or package can hand out for joining through a
 * campaign. Campaigns owns everything generic (which campaign has which
 * prize, caps, the claim message, the ledger, retries); the type owns what
 * is specific: how it is configured and how it is handed over.
 */
interface PrizeType
{
    /** Stable identifier stored with every prize of this type. Never rename it. */
    public function key(): string;

    /** Shown to the admin in the "add a prize" list. */
    public function label(): string;

    /**
     * One line describing a configured prize, for the admin and for the
     * user's claim message.
     *
     * @param  array<string, mixed>  $config
     */
    public function describe(array $config): string;

    /**
     * The form an admin fills in to configure the prize. It is started with
     * the context `['campaign' => <campaign id>]` and must finish by calling
     * `CampaignPrizes::attach()`; campaigns registers it for you.
     *
     * @return class-string<Form>
     */
    public function configForm(): string;

    /**
     * Hands the prize over. Throw to record the grant as failed: an admin
     * can then retry, which calls this again for the same grant, so it must
     * be safe to run twice. Keep the ids of anything already created in
     * `$grant->meta`, or use `PrizeSettlement`, which does it for orders.
     *
     * Run for the user in the webhook context, so `wallet()` and friends act
     * on them.
     *
     * @param  array<string, mixed>  $config
     */
    public function grant(BotUser $user, array $config, CampaignPrizeGrant $grant): void;
}

<?php

namespace TelegramBotEssentials\Campaigns\Contracts;

use TelegramBotEssentials\Campaigns\Models\CampaignPrizeGrant;
use TelegramBotEssentials\Essence\Forms\Form;

/**
 * One way for a user to receive a pending prize: a plain tap, a dice game,
 * anything a project or package wants to add. Campaigns owns the ledger and
 * the hand-over; a method only decides what the user must do before it
 * happens, and calls `PrizeGranting` when they have done it.
 */
interface ClaimMethod
{
    /** Stable identifier stored on prizes. */
    public function key(): string;

    /** Name shown to admins when they pick a method. */
    public function label(): string;

    /**
     * One line for the admin screen, e.g. "dice: win on 2, 4, 6 (2 tries)".
     *
     * @param  array<string, mixed>  $config
     */
    public function describe(array $config): string;

    /**
     * The form an admin fills in to configure the method, or null when it has
     * nothing to configure. Campaigns registers it with the form registry and
     * starts it with `['prize' => id, 'lastPage' => n]`.
     *
     * @return class-string<Form>|null
     */
    public function configForm(): ?string;

    /**
     * The config a prize starts with when the admin picks a method without a form.
     *
     * @return array<string, mixed>
     */
    public function defaultConfig(): array;

    /** The text of the button under the prize message. */
    public function buttonLabel(string $prize): string;

    /**
     * The user tapped the button. Returns a short alert to show them, or null.
     * Runs in a callback query, so it may edit the tapped message.
     */
    public function start(CampaignPrizeGrant $grant): ?string;
}

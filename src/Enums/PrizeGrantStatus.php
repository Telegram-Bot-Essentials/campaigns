<?php

namespace TelegramBotEssentials\Campaigns\Enums;

enum PrizeGrantStatus: string
{
    /** Reserved for the user, waiting for them to claim it. */
    case Pending = 'pending';

    /** Claimed and being handed over; the row is locked out of a second claim. */
    case Processing = 'processing';

    case Granted = 'granted';

    /** The hand-over threw. The user is still owed the prize: an admin can retry. */
    case Failed = 'failed';
}

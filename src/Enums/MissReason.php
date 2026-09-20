<?php

namespace TelegramBotEssentials\Campaigns\Enums;

enum MissReason: string
{
    case Unknown = 'unknown';
    case Deleted = 'deleted';
    case Disabled = 'disabled';
    case Expired = 'expired';
    case ExistingUser = 'existing_user';
}

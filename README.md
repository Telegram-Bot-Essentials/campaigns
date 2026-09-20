# Telegram Bot Essentials — Campaigns

Tracked marketing links for the
[`telegram-bot-essentials/essence`](https://github.com/Telegram-Bot-Essentials/essence)
ecosystem. An admin creates a named campaign from the bot's admin menu and gets a
`t.me` link and a QR code. Brand-new users who arrive through it are attributed to
the campaign, and the campaign screen shows who joined, who was turned away and,
with billing installed, what they paid.

> **Status: `0.0.x`.** Prizes (a reward for joining) are planned as a second phase,
> registered by other packages. Until then, listen for `CampaignUserAttributed`.

## Installation

```bash
composer require telegram-bot-essentials/campaigns
php artisan migrate
```

There is nothing to switch on: installing the package enables it.

### Add the admin key

A companion's `ReplyKey`s are not auto-discovered, so list the campaigns key in your
app's `config/tbe-essence.php`:

```php
use TelegramBotEssentials\Campaigns\Telegram\ReplyKeys\Admin\CampaignsKey;

'keyboard' => [
    'admin' => [
        // ...
        [CampaignsKey::class],
    ],
],
```

## The admin menu

**📣 Campaigns** opens a paginated list. From there an admin can:

- **Create** a campaign: a name and an optional expiry in days. The link code is
  generated automatically and never changes, so a printed link or QR keeps working.
- Open a campaign to see its **link** and **stats**, get its **QR code**, **enable or
  disable** it, **rename** it, **set or clear its expiry**, or **delete** it.
- Deleting is a soft delete: users the campaign already brought in stay attributed
  to it, and its link keeps answering that the offer has ended.

## How it works

- A campaign link is `https://t.me/{bot}?start=c_<code>`. The `c_` prefix is reserved
  for this package, so it never collides with other packages that read the same
  start payload (affiliate referral codes, signed purchase tokens).
- **Only brand-new users are attributed**: the user must have been created by that
  very `/start` request. Each user belongs to at most one campaign
  (`unique(bot_id, bot_user_id)`, first touch wins).
- Unknown, deleted, disabled and expired links, and returning users, are stored as a
  *miss* (deduped per user, payload and reason), and the user is told why.
- After an attribution, `CampaignUserAttributed` is fired. Prize packages listen for it.

## Stats

Each campaign screen shows the users it brought in and the users it turned away, by
reason. With [`billing`](https://github.com/Telegram-Bot-Essentials/billing) installed
it also shows **paying users and revenue**, worked out live from paid invoices of the
campaign's users. Nothing is stored: a revoked invoice stops being `paid` and drops
out. Wallet top-ups are left out when
[`user-wallet`](https://github.com/Telegram-Bot-Essentials/user-wallet) is installed,
since money moved into a wallet is not a sale.

## With user-management

When [`user-management`](https://github.com/Telegram-Bot-Essentials/user-management)
is installed, the admin user screen shows which campaign a user joined through, and
the user list gains a **From a campaign** filter.

## Development

Built from the [`skeleton`](https://github.com/Telegram-Bot-Essentials/skeleton)
template. `composer test && composer lint && composer analyse`.

## License

[MIT](LICENSE).

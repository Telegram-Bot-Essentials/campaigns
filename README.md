# Telegram Bot Essentials — Campaigns

Tracked marketing links for the
[`telegram-bot-essentials/essence`](https://github.com/Telegram-Bot-Essentials/essence)
ecosystem. An admin creates a named campaign from the bot's admin menu and gets a
`t.me` link and a QR code. Brand-new users who arrive through it are attributed to
the campaign, and the campaign screen shows who joined, who was turned away and,
with billing installed, what they paid.

> **Status: `0.0.x`.** The prize contract may still change between `0.0.x` releases.

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
- After an attribution, `CampaignUserAttributed` is fired and the campaign's
  [prizes](#prizes) are reserved for the user.

## Prizes

An admin can attach **prizes** to a campaign: a reward every new user who joins through
it receives. Campaigns owns everything generic; a **prize type** owns what is specific to
one kind of reward.

| Campaigns does | The prize type does |
|---|---|
| Stores which prizes a campaign has, with a cap each | Names itself (`key`, `label`) |
| Shows the admin screens (add, enable, cap, retry) | Provides the config form an admin fills in |
| Reserves a prize per new user, once, atomically | Says how it reads to a human (`describe`) |
| Sends the claim message and handles the tap | Hands the prize over (`grant`) |
| Keeps the ledger: status, error, invoice, attempts | Says what the prize is and describes it |

In the admin menu, a campaign has a **🎁 Prizes** screen. **Add a prize** lists every
registered type, hands over to that type's own form, and attaches the result. Each prize
has an optional **limit** (0 = unlimited), can be **disabled**, and has a **retry** button
when hand-overs failed.

### Shipped type

**💰 Wallet credit** is registered automatically when
[`user-wallet`](https://github.com/Telegram-Bot-Essentials/user-wallet) is installed.
It is handed over the moment the user joins.

### Registering your own type

Implement `TelegramBotEssentials\Campaigns\Contracts\PrizeType` and register it from your
service provider's `boot()`:

```php
prizeTypes()->register(new FreeServicePrize);
```

The config form extends `PrizeConfigForm`: name the type and turn the answers into the
prize's config; campaigns attaches it and returns the admin to the prize list.

```php
class FreeServiceForm extends PrizeConfigForm
{
    protected string $type = 'FREE_SERVICE_PRIZE';
    protected string $lang = 'my-package::free_service.wizard';

    protected function prizeTypeKey(): string { return FreeServicePrize::KEY; }
    public function steps(): array { return [/* Choice, Text, ... */]; }
    protected function config(array $answers): array { return ['plan' => $answers['plan']]; }
}
```

`grant(BotUser $user, array $config, CampaignPrizeGrant $grant)` runs for that user.
**Throw to fail**: the ledger records the message and an admin can retry, which calls
`grant()` again for the same grant, so it must be safe to run twice. Keep the ids of
anything already created in `$grant->meta`.

### Prizes that are an order

For a reward that is an order of another package (a service, a plan), build the order and
call `PrizeSettlement::pay($order, $grant)`. It creates the invoice and settles it with a
`PrizePaymentAttempt` (a payment method that moves no money and is never offered on any
invoice), so the order's own paid hook runs exactly as for a paying customer. On a retry,
reuse `$grant->order` instead of creating another order: `pay()` then re-runs only the
paid hook, never a second invoice or attempt.

### Claiming

Every prize is reserved as **pending**, wallet credit included, so the user sees
something waiting for them and taps to receive it. Each prize gets its own
message with its own claim button, and nothing costly is created for someone who never
uses it. Tapping the button runs `grant()`, then the message is rewritten to say the prize
was received (or that it failed) and loses its button. A double tap hands the prize over
once, and a forwarded button does nothing for anyone but its owner. An admin retry of a
failed grant does not re-message the user.

### Claim methods

How the user earns the button's outcome is a second registry, separate from the prize
type: the type says *what* they get, the claim method says *how* they get it. An admin
picks it per prize from the prize screen (**🎯 Received by**). A prize starts as a plain tap.

| Method | What the user does |
| --- | --- |
| `click` | Taps the button. |
| `dice` | Taps **Play**, then sends the bot a 🎲. They win if it lands on a number the admin picked (`1-6`, comma separated), and get the admin's number of tries. |

Register another with `claimMethods()->register(new MyMethod)` from a provider's `boot()`.
Implement `Contracts\ClaimMethod`: `key`, `label`, `describe`, an optional `configForm()`
(extend `Telegram\Forms\ClaimMethodConfigForm`, started with `['prize' => id, 'lastPage' => n]`),
`buttonLabel` and `start($grant)`, which runs when the user taps. When the user has done
what the method asks, call `PrizeGranting::claim($grant)` to hand the prize over, or
`forfeit($grant)` to end it as lost. Then `PrizeMessages::settle()` rewrites the message.

- **The method is copied onto the grant when it is issued.** Changing a prize's method
  later only affects users who join afterwards.
- **The dice game is an essence state** (`DiceAnswer`), so it behaves like any other flow:
  one game at a time, the prize message is locked with a cancel button, the cancel key
  works, and any other action (a command, a menu key, or starting another game) cancels
  it and puts the prize message back. Cancelling keeps the throws already used, so it
  cannot be used to get more tries. Essence cancels the state on messages but not on a
  button tap, so `DiceClaim::start()` cancels the open one itself before starting.
- **Cheating.** A forwarded dice keeps its value, so a message with any forward field, a
  `via_bot`, an emoji other than 🎲, or an id lower than the bot's prompt does not count.
  Rejected throws do not use up a try, and a redelivered update counts once.
- **A lost game frees its slot.** The grant ends as `lost`, the prize's reserved count drops
  by one, and the user is not offered that prize again.

### Consequences

Read these before turning prizes on.

- **Billing is now required.** The payment attempt model lives here, so
  `telegram-bot-essentials/billing` moved from `suggest` to `require`.
- **A prize invoice is settled at price 0.** `PrizeSettlement` sets the invoice's `price`
  to 0 and keeps its `original_price`, so affiliate commission (worked out from `price`),
  billing revenue reports and offers see a free order without knowing about prizes. The
  invoice is still a *paid* invoice, so anything that counts paid invoices rather than
  summing their price (for example a "paying customers" count in another package) will
  include it. Campaign stats leave prize invoices out entirely, by
  `PrizePaymentAttempt::isPrizeInvoice()`, which is also the marker other packages can use.
- **A prize is not promised until it is claimed.** A reserved prize takes a slot of the
  prize's limit at once, so an unclaimed prize still counts against the cap. There is no
  claim-by date yet, so abandoned claims hold their slot for good.
- **A lost claim message strands the prize.** Nothing re-sends it, and there is no
  "My prizes" screen yet. Both are deferred.
- **Failure keeps the slot.** A failed grant is owed to the user, so it still counts
  against the cap until an admin retries it.
- **The reason for a failure inside an order's paid hook is generic.** Billing runs the hook
  through essence's exception handler, which tells the user "something went wrong" and
  rethrows a bare HTTP 203. The ledger stores that, not the original message; the original
  is in the exception report. A failure raised directly in `grant()` keeps its real message.
- **The user sees billing's own "payment accepted" message** when an order-based prize is
  settled, because the invoice goes through the normal paid flow.
- **Only new users get prizes**, and each user at most one attribution, so at most one of
  each prize per user. Throwaway Telegram accounts can still farm prizes: set limits.
- **Prize types are a public contract** other packages depend on. It stays at `0.0.x` and
  may change between releases.

## Stats

Each campaign screen shows the users it brought in and the users it turned away, by
reason, and **paying users and revenue**, worked out live from paid invoices of the
campaign's users. Nothing is stored: a revoked invoice stops being `paid` and drops
out. Invoices settled as a prize and wallet top-ups (when
[`user-wallet`](https://github.com/Telegram-Bot-Essentials/user-wallet) is installed)
are left out, since neither is a sale.

## With user-management

When [`user-management`](https://github.com/Telegram-Bot-Essentials/user-management)
is installed, the admin user screen shows which campaign a user joined through, and
the user list gains a **From a campaign** filter.

## Development

Built from the [`skeleton`](https://github.com/Telegram-Bot-Essentials/skeleton)
template. `composer test && composer lint && composer analyse`.

## License

[MIT](LICENSE).

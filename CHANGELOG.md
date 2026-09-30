# Changelog

All notable changes to this project are documented here. Format follows
[Keep a Changelog](https://keepachangelog.com/en/1.0.0/). Until the API
stabilizes at 1.0 a `0.0.x` bump may carry breaking changes.

## [Unreleased]

### Added

- `campaigns`, `campaign_attributions` and `campaign_misses` tables and models.
- `c_<code>` start-payload handling: a brand-new user arriving through a live
  campaign link is attributed to it (first touch wins, one campaign per user).
- Every dead link (unknown, deleted, disabled, expired) and every returning
  user is recorded as a deduped miss, and the user is told why.
- `CampaignUserAttributed` event, the seam for prize packages.
- Admin menu (`CampaignsKey`): paginated list, create form, per-campaign screen
  with link, stats and QR code, enable/disable, rename, expiry, soft delete.
- Per-campaign stats; with billing installed, paying users and revenue computed
  live from paid invoices (wallet top-ups excluded when user-wallet is installed).
- user-management integration: a per-user campaign section and a user-list filter.
- English and Persian translations.
- Prizes: a `PrizeType` contract and `prizeTypes()` registry, `campaign_prizes` and
  `campaign_prize_grants` tables, an admin **🎁 Prizes** screen (add, enable/disable,
  limit, retry failed), a claim message and button for new users, and a per-prize
  grant ledger with status, error and attempts.
- Wallet-credit prize, registered when user-wallet is installed.
- `PrizeConfigForm` base class for a prize type's config form.
- Claim methods: a `ClaimMethod` contract and `claimMethods()` registry, chosen per prize
  from the admin prize screen. Ships `click` (one tap) and `dice` (the user throws a 🎲;
  the admin picks the winning numbers and the number of tries). A lost game frees its cap
  slot (`lost` status). The dice game is an essence state (cancellable, one at a time). Forwarded, via-bot, non-🎲 and stale dice are rejected.
- `PrizeMessages` and one message per prize that settles on the outcome.
- `PrizePaymentAttempt` and `PrizeSettlement`: settle the invoice of an order-based
  prize without a real payment, retry-safe, at price 0 (`original_price` is kept).
  `PrizePaymentAttempt::isPrizeInvoice()` is the sale marker.

### Changed

- Every prize is claim-only: `PrizeType::requiresClaim()` was removed.
- **`telegram-bot-essentials/billing` is now required** (was suggested).
- Campaign stats exclude invoices settled as a prize.

### Known limitations

- A prize invoice is a paid invoice at price 0: readers that sum `price` are right,
  but a count of paid invoices in another package still includes it.
- Unclaimed prizes hold a slot of the limit indefinitely (no claim-by date).
- No "My prizes" screen and no re-send of a lost claim message.
- A failure inside an order's paid hook is recorded with a generic message.

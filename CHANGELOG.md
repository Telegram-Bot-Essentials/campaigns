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

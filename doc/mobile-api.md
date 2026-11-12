# Mobile API (v1)

Flutter and other API clients use **Laravel Sanctum** personal access tokens. Existing Blade routes are unchanged.

## Base path

All endpoints are under:

`{APP_URL}/api/mobile/v1`

Use HTTPS in production. Send `Authorization: Bearer {token}` for authenticated routes.

## Auth

| Method | Path | Body |
|--------|------|------|
| POST | `/auth/login` | `username`, `password`, optional `two_factor_code`, `recovery_code`, `device_name` |

If the user has Fortify 2FA enabled, a first response may be `428` with `{ "two_factor": true }`; repeat the request with a valid TOTP or recovery code.

| Method | Path | Auth |
|--------|------|------|
| POST | `/auth/logout` | Bearer |
| GET | `/me` | Bearer — returns user, roles, permissions, `mobile_actions`, and `preferences` (transaction defaults + `appearance`) |
| PATCH | `/preferences/appearance` | Bearer — body: `appearance`: `light` \| `dark` \| `system` (same as web settings) |

## Jubelio resi scan (barcode → queue)

Requires `jubelio-view`. Exposed in `mobile_actions` as `scan_resi` when allowed. User preferences `preferences.resi_scan` on `/me` mirror saved defaults.

| Method | Path | Body / notes |
|--------|------|----------------|
| GET | `/jubelio/resi-scan/options` | Warehouses with Jubelio mappings, marketplace list, saved defaults |
| PATCH | `/jubelio/resi-scan/defaults` | `warehouse_id`, `marketplace` |
| POST | `/jubelio/resi-scan` | `warehouse_id`, `marketplace` (`shopee`, `tiktok`, …), `barcodes[]` (raw tracking numbers without prefix), optional `remember_defaults` |

Creates `jubelioorders` rows (`type=SELL`, `order_status=READY TO SHIP`, `source=3`) — cron does not post these until Jubelio webhook promotes the row to `SHIPPED`. with marketplace invoice prefix (e.g. `SP-{barcode}`), `user_id` = scanner, and warehouse/store/location from `jubeliosyncs`. Duplicates (already in `jubelioorders` or sell `transactions`) are returned in `skipped` without inserting.

## Dashboard

| Method | Path | Auth | Notes |
|--------|------|------|-------|
| GET | `/dashboard` | Bearer | Permission-gated KPI subset from web `DashboardService` (`can.*` flags). Response includes `can`, `has_ops_panel`, `has_daily_panel`, and `kpis` (e.g. `activity_today`, `queue`, `jubelio`, `stock_alerts`, `book_closing` when allowed). |

## Transactions (read-only)

| Method | Path | Auth | Query |
|--------|------|------|-------|
| GET | `/transactions` | Bearer + `transactions-list` | `page`, `from`, `to`, `type`, `invoice`, `total`, `sort`, `direction`, `per_page` (100/200/300) |
| GET | `/transactions/{id}` | Bearer + `transactions-show` | — |

List rows use `visibleToUser` / ACL like web. Each row includes `type` (`id`, `slug`, `label`), `sender` / `receiver` (`id`, `name`), and signed header `total` (never `real_total`). Bank running balances on list parties follow web hidden-balance rules (superadmin exempt).

Detail returns `transaction` with header amounts from `total` only, optional line `details`, and party balances hidden for bank contacts when the user has `addrbook-bank-account-hidden-balance` (unless superadmin).

## Lookups

| Method | Path | Query |
|--------|------|--------|
| GET | `/items/by-code` | `code` |
| GET | `/items/by-id` | `id` |
| GET | `/items/barcode-lookup` | `barcode` (SKU, legacy_code, name, or numeric item id) |
| GET | `/addrbook/{type}` | `search` (min 3 chars), optional `addrbook_type` (comma-separated type ids) |

`{type}` is a transaction slug used for permission checks, e.g. `sell`, `move`, `cash-in`, `cash-out`, `transfer`.

## Transactions (same payloads as web FormRequests)

Optional header: `X-Idempotency-Key` (reuses the web duplicate-submit middleware).

| Method | Path | Body |
|--------|------|------|
| POST | `/transactions/item` | Same as `StoreItemTransactionRequest` (`type`: `sell`, `move`, `buy`, …) |
| POST | `/transactions/cash-in` | `StoreCashTransactionRequest` |
| POST | `/transactions/cash-out` | `StoreCashTransactionRequest` |
| POST | `/transactions/transfer` | `StoreTransferRequest` |

Successful responses return JSON with `id` or `ids` and a short `message`.

## Database

Production must run the Sanctum migration so `personal_access_tokens` exists:

```bash
php artisan migrate --path=database/migrations/*_create_personal_access_tokens_table.php
```

(Exact filename may vary; use the published migration from `vendor:publish` Sanctum.)

## Flutter app

Source lives in `apps/aria_mobile/`. Generate platform folders locally:

```bash
cd apps/aria_mobile && flutter create --platforms=android,ios . && flutter pub get
```

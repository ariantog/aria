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
| PATCH | `/preferences/pos-defaults` | Bearer — `sender_id`, `receiver_id`, `whatsapp_phone` (optional; persisted per user) |

## Jubelio resi scan (barcode → queue)

Requires `jubelio-view`. Exposed in `mobile_actions` as `scan_resi` when allowed. User preferences `preferences.resi_scan` on `/me` mirror saved defaults.

| Method | Path | Body / notes |
|--------|------|----------------|
| GET | `/jubelio/resi-scan/options` | Warehouses with Jubelio mappings, marketplace list, saved defaults |
| PATCH | `/jubelio/resi-scan/defaults` | `warehouse_id`, `marketplace` |
| POST | `/jubelio/resi-scan` | `warehouse_id`, `marketplace` (`shopee`, `tiktok`, …), `barcodes[]` (courier resi / AWB), optional `remember_defaults` |

For **Shopee**, **TikTok**, **Tokopedia**, and **Lazada**, each barcode is resolved via Jubelio (tracking query params, then `q` + date window, then ready-to-pick). AWB may only appear on full order detail — Aria fetches detail to confirm. Requires `JUBELIO_ACTIVE=true` and a valid Jubelio token (System Settings). Skip reasons distinguish **not found** vs **token/API errors** (e.g. `Token Jubelio tidak tersedia…`, `API Jubelio menolak akses (401/403)…`). **Lazada** AWB labels are often `LXAD-{orderId}` while Jubelio/webhook `salesorder_no` is typically `LZ-{orderId}-{jubelio_store_id}` (numeric middle segment, no `LXAD`); scan matching tries both shapes plus store-suffix variants like TikTok. **Blibli** / **Zalora** still use prefix rules only (`BL-`, `ZA-`, …).

Resolves AWB → invoice first (Jubelio tracking lookup or prefix rules), then updates an existing `jubelioorders` SELL row matched by canonical `invoice` (including optional `TT-`/`TP-`/`LZ-` store-suffix variants and Lazada `LXAD` ↔ numeric invoice shapes), Jubelio `salesorder_id`, or `shopee-push:{ordersn}` placeholder. Sets `READY TO SHIP`, scanner `user_id`, warehouse from `jubeliosyncs`; promotes placeholder `jubelio_order_id` when Jubelio returns a real id. **Shopee** does not insert when no row exists (use push pre-create or webhook). **TikTok**, **Tokopedia**, and **Lazada** may **insert** `READY TO SHIP` queue rows when AWB resolves and no row exists yet (`insert_queue_row_on_scan` in `config/jubelio_resi_scan.php`). Other channels skip with *Belum ada di antrean Jubelio — tunggu push marketplace atau webhook.* Response includes `created`, `updated`, and `skipped`. Posted sell `transactions` also skip. Cron does not post `READY TO SHIP` until Jubelio webhook sets `SHIPPED`.

## Dashboard

| Method | Path | Auth | Notes |
|--------|------|------|-------|
| GET | `/dashboard` | Bearer | Permission-gated KPI subset from web `DashboardService` (`can.*` flags). Response includes `can`, `has_ops_panel`, `has_daily_panel`, and `kpis` (e.g. `activity_today`, `queue`, `jubelio`, `stock_alerts`, `book_closing` when allowed). |

## Transactions (read-only)

| Method | Path | Auth | Query |
|--------|------|------|-------|
| GET | `/transactions` | Bearer + `transactions-list` | `page`, `from`, `to`, `type`, `invoice`, `total`, `sort`, `direction`, `per_page` (100/200/300) |
| GET | `/transactions/{id}` | Bearer + `transactions-show` | — |
| GET | `/transactions/{id}/receipt` | Bearer + `transactions-show` | 58mm thermal receipt PDF (inline) |

List rows use `visibleToUser` / ACL like web. Each row includes `type` (`id`, `slug`, `label`), `sender` / `receiver` (`id`, `name`), and signed header `total` (never `real_total`). Bank running balances on list parties follow web hidden-balance rules (superadmin exempt).

Detail returns `transaction` with header amounts from `total` only, unsigned `grand_total` for receipt print (same as PDF `displayGrandTotal()`), `receipt_branding` (`company_name`, `address`, `phone` from invoice branding / sender), optional line `details` (each line may include `display_name` for thermal receipts), and party balances hidden for bank contacts when the user has `addrbook-bank-account-hidden-balance` (unless superadmin).

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
| POST | `/transactions/item` | Same as `StoreItemTransactionRequest` (`type`: `sell`, `move`, `buy`, …). Sell POS: add `pos: true` for `receipt_url`, optional `whatsapp`, `jubelio_reminder`; `remember_defaults` saves parties + WA phone to `preferences.pos`. |
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

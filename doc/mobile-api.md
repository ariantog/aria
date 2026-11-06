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

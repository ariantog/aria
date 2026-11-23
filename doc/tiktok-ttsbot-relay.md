# TikTok OAuth relay (cdn `ttsbot.php` → aria3)

TikTok Partner Center redirect URL stays **`https://cdn.corenationactive.com/ttsbot.php`** (not the Aria app callback). After the seller approves, `ttsbot.php` server-side posts the one-time `code` to Aria and **does not** show the code in the browser.

## Flow

1. Seller authorizes in TikTok → redirect to `ttsbot.php?code=…` (optional `shop_region`).
2. CDN PHP reads env, `POST` JSON `{"code":"…"}` to aria3 `POST /api/readonly/tiktok/oauth/exchange` with `Authorization: Bearer …`.
3. Aria calls TikTok token API and persists `settings` slug **`tiktok_shop_oauth`** (same row as omnichannel + `GET /api/readonly/tiktok/oauth`).
4. Browser shows success or generic error (no tokens, no auth code).

TikTok `?error=` is still shown on the CDN page only.

## CDN environment

Set on the CDN host (not in the Aria repo):

| Variable | Value |
|----------|--------|
| `ARIA_BASE_URL` | `https://aria3.corenationactive.com` (no trailing slash) |
| `ARIA_READONLY_BEARER` | Same secret as aria3 `READONLY_EXPORT_BEARER_TOKEN` |

The bearer must authenticate user **`grok`** (or configured `READONLY_EXPORT_USERNAME`) with permissions **`export-readonly`** and **`api.readonly.tiktok.oauth`**.

## Deploying `ttsbot.php`

Copy from repo template **`doc/ttsbot.php.example`** to the CDN as `ttsbot.php`. Do not commit production bearer values.

Aria must have this PR deployed before the relay works (`exchange` route + middleware allow-list for grok POST).

## Aria API

Documented in **`doc/grok-readonly-api.md`** — `POST /api/readonly/tiktok/oauth/exchange`.

Body: `{"code":"…"}` or `{"auth_code":"…"}`.

Success: `{"ok":true,"expires_at":"…","expires_at_unix":…}`.

Failure: `{"ok":false,"error":"tiktok_exchange_failed","message":"…"}` (includes persisted `last_error` from failed TikTok API call).

**Never returned:** `refresh_token`, `access_token`.

## Partner Center

- Redirect URL: `https://cdn.corenationactive.com/ttsbot.php`
- Aria in-app authorize (`/tiktok-shop/authorize`) remains available for staff; env `TIKTOK_SHOP_REDIRECT_URL` should still match Partner Center (CDN URL), not `/tiktok-shop/oauth/callback`, when using this relay path exclusively.

See also **`doc/omnichannel.md`** (TikTok shop access token).
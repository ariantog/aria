# CDN marketplace push relay — maintainer deploy guide

Step-by-step ops to run Shopee Push and TikTok Shop webhooks on **`cdn.corenationactive.com`**, forwarding to aria3 after an immediate HTTP 200. For architecture and security notes see **`doc/marketplace-webhook-cdn-relay.md`**.

**Prerequisites**

- aria3 deployed with relay routes (`POST /shopee/push/relay`, `POST /tiktok-shop/webhook/relay`) — merged via PR #1177+.
- Queue worker running on aria3 (`php artisan queue:listen` / supervisor).
- CDN host with PHP + `curl`, separate docroot (`public_html`) and a writable directory **above** docroot for secrets.

---

## 1. File layout on the CDN host

| Path | Role |
|------|------|
| `/home/…/config/pushconfig.php` | **Private** — partner keys, app secret, aria3 URL, relay bearer (`chmod 600`, not web-accessible) |
| `…/public_html/shopeepush.php` | Shopee Push Mechanism callback |
| `…/public_html/tiktokpush.php` | TikTok Shop Event API webhook |

**Console / Partner Center URLs (exact strings):**

- Shopee Push: `https://cdn.corenationactive.com/shopeepush.php`
- TikTok webhook: `https://cdn.corenationactive.com/tiktokpush.php`

---

## 2. Copy config and scripts from the Aria repo

On your workstation (paths are examples — adjust to your CDN user home):

```bash
# Private config (edit values, then upload)
cp doc/pushconfig.php.example /path/to/upload/pushconfig.php

# Public scripts
cp doc/shopeepush.php.example /path/to/upload/shopeepush.php
cp doc/tiktokpush.php.example  /path/to/upload/tiktokpush.php
```

On the CDN server:

```bash
install -m 600 -o www-data -g www-data pushconfig.php /home/YOUR_USER/config/pushconfig.php
install -m 644 -o www-data -g www-data shopeepush.php /home/YOUR_USER/public_html/shopeepush.php
install -m 644 -o www-data -g www-data tiktokpush.php  /home/YOUR_USER/public_html/tiktokpush.php
```

Edit **`pushconfig.php`** with production values:

- `SHOPEE_PUSH_PARTNER_KEY` — **Live Push Partner Key** from Shopee Open Platform → Push Mechanism (not the Open API key).
- `SHOPEE_PUSH_CALLBACK_URL` — `https://cdn.corenationactive.com/shopeepush.php` (must match Console byte-for-byte for HMAC).
- `TIKTOK_SHOP_APP_KEY` / `TIKTOK_SHOP_APP_SECRET` — same as aria3 `.env` and Partner Center app.
- `ARIA_BASE_URL` — `https://aria3.corenationactive.com` (no trailing slash).
- `ARIA_WEBHOOK_RELAY_BEARER` — long random string; **must match** aria3 relay bearer env (see §5).

Edit **both** `shopeepush.php` and `tiktokpush.php`: set the constant to your live config path, e.g.:

```php
define('PUSH_RELAY_CONFIG_FILE', '/home/YOUR_USER/config/pushconfig.php');
```

Never commit `pushconfig.php` with real secrets. Confirm it is **not** under `public_html` and not fetchable over HTTP.

---

## 3. Smoke tests (before Console cutover)

**Config missing / wrong path** — expect **500** (empty body):

```bash
curl -sS -o /dev/null -w "%{http_code}\n" -X POST \
  "https://cdn.corenationactive.com/shopeepush.php" \
  -H "Content-Type: application/json" -d '{}'
```

With config in place but **no valid signature** — expect **401**:

```bash
curl -sS -o /dev/null -w "%{http_code}\n" -X POST \
  "https://cdn.corenationactive.com/shopeepush.php" \
  -H "Content-Type: application/json" \
  -H "Authorization: invalid" -d '{}'
```

Same for TikTok:

```bash
curl -sS -o /dev/null -w "%{http_code}\n" -X POST \
  "https://cdn.corenationactive.com/tiktokpush.php" \
  -H "Content-Type: application/json" -H "Authorization: invalid" -d '{}'
```

After aria3 relay env is enabled (§5), run Shopee **Verify** or **Push Test Data** from Console (§4) and confirm:

- CDN access log shows 200 on `shopeepush.php`.
- aria3 `storage/logs/laravel.log` shows relay hit / job dispatch.
- `shopee_push_inbox` or queue processes (see `doc/shopee-push-test.md`).

---

## 4. Shopee Open Platform

1. Open **Seller In-house app** → **Push Mechanism** → **Set Push**.
2. Set callback URL to:

   ```text
   https://cdn.corenationactive.com/shopeepush.php
   ```

3. Ensure `pushconfig.php` `SHOPEE_PUSH_CALLBACK_URL` is **identical** (scheme, host, path; trailing slash must match Console if Console has one).
4. Click **Verify** (URL check). Edge must return **2xx** quickly; with verify signature on, invalid auth returns 401 (Console may still accept connectivity tests — use official Verify flow).
5. Use **Push Test Data** → `order_status_push` when shadow/inbox enabled on aria3; confirm inbox/shadow per `doc/shopee-push-test.md`.

**OAuth:** Shopee authorize redirect stays on aria3 (`/shopee-admin/oauth/callback`) — do **not** point OAuth at `.php` on CDN (`doc/shopee-push-test.md`).

---

## 5. TikTok Partner Center

1. **App → Webhooks** (or Event API webhook settings).
2. Set webhook URL to:

   ```text
   https://cdn.corenationactive.com/tiktokpush.php
   ```

3. **Signature:** lowercase hex HMAC-SHA256 of `app_key + raw JSON body`, key = `app_secret`, sent in `Authorization`. CDN `tiktokpush.php` mirrors `doc/tiktok-shop-webhook.md` before forwarding.
4. Partner URL check must get **200** within ~3s; CDN ACKs first, then forwards async.
5. Subscribe to **`ORDER_STATUS_CHANGE`** first; confirm `tiktok_shop_webhook_inbox` on aria3 after a test event.

---

## 6. aria3 `.env` after relay merge

Enable relay acceptance on aria3 **before** or **with** CDN cutover. Bearer must match `pushconfig.php` `ARIA_WEBHOOK_RELAY_BEARER`.

```env
# Shopee — callback URL is the CDN script (not /shopee/push/relay)
SHOPEE_PUSH_PARTNER_KEY=...          # same Live Push Partner Key as pushconfig
SHOPEE_PUSH_CALLBACK_URL=https://cdn.corenationactive.com/shopeepush.php
SHOPEE_PUSH_VERIFY_SIGNATURE=true
SHOPEE_PUSH_RELAY_ENABLED=true
SHOPEE_PUSH_RELAY_BEARER=...         # same as ARIA_WEBHOOK_RELAY_BEARER in pushconfig

# TikTok
TIKTOK_SHOP_APP_KEY=...
TIKTOK_SHOP_APP_SECRET=...
TIKTOK_SHOP_WEBHOOK_ENABLED=true
TIKTOK_SHOP_WEBHOOK_VERIFY_SIGNATURE=true
TIKTOK_SHOP_WEBHOOK_RELAY_ENABLED=true
TIKTOK_SHOP_WEBHOOK_RELAY_BEARER=... # same bearer as Shopee relay (shared secret is fine)
```

`SHOPEE_PUSH_RELAY_BEARER` / `TIKTOK_SHOP_WEBHOOK_RELAY_BEARER` may be left empty for HMAC-only trust on relay endpoints — production should use a shared bearer.

Clear config cache after `.env` edits:

```bash
php artisan config:clear
```

---

## 7. Cutover order (recommended)

1. Deploy aria3 with relay routes + env (relay can stay `ENABLED=false` until smoke done).
2. Deploy CDN `pushconfig.php` + both public scripts; fix `PUSH_RELAY_CONFIG_FILE` paths.
3. Set aria3 relay `*_ENABLED=true` and matching bearers.
4. Shopee: change Push callback to CDN URL; Verify → test push.
5. TikTok: change webhook URL to `tiktokpush.php`; run Partner test / wait for live event.
6. Watch queue depth and inbox tables during promo.

---

## 8. Rollback

Point platforms back to **direct aria3** URLs (no CDN):

| Channel | Rollback URL |
|---------|----------------|
| Shopee Push | `https://aria3.corenationactive.com/shopee/push` |
| TikTok webhook | `https://aria3.corenationactive.com/tiktok-shop/webhook` |

On aria3, set:

```env
SHOPEE_PUSH_CALLBACK_URL=https://aria3.corenationactive.com/shopee/push
SHOPEE_PUSH_RELAY_ENABLED=false   # optional; direct /shopee/push still works
TIKTOK_SHOP_WEBHOOK_RELAY_ENABLED=false
```

CDN scripts can remain in place; platforms simply stop calling them. Rotate `ARIA_WEBHOOK_RELAY_BEARER` if the shared secret may have leaked.

---

## 9. Troubleshooting

| Symptom | Likely cause |
|---------|----------------|
| Shopee Verify fails / 401 | Wrong **Push Partner Key**, or `SHOPEE_PUSH_CALLBACK_URL` mismatch vs Console URL |
| CDN 500 | `PUSH_RELAY_CONFIG_FILE` wrong, empty key in `pushconfig.php`, or permissions |
| CDN 200 but no aria3 inbox | Relay not enabled, bearer mismatch, aria3 down, or forward timeout — check CDN `error_log` and laravel.log |
| TikTok 401 at edge | Wrong app key/secret or body altered before verify |
| Duplicate processing | Do not register **both** CDN and aria3 URLs in Console for the same channel |

Forward failures are logged on the CDN PHP host (`shopeepush relay forward failed` / `tiktokpush relay forward failed`). Platform already received 200 — recover via Shopee poll / TikTok backfill if needed (`doc/marketplace-webhook-cdn-relay.md`).

---

## Related files in repo

| File | Purpose |
|------|---------|
| `doc/pushconfig.php.example` | One shared private config template |
| `doc/shopeepush.php.example` | Deploy as `public_html/shopeepush.php` |
| `doc/tiktokpush.php.example` | Deploy as `public_html/tiktokpush.php` |
| `doc/marketplace-webhook-cdn-relay.md` | Architecture, headers, security |
| `doc/shopee-push-test.md` | Shopee keys, simulate, inbox checks |
| `doc/tiktok-shop-webhook.md` | TikTok signature and inbox |
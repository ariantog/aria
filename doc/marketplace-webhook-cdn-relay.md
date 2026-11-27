# Marketplace webhooks — CDN ACK-first relay → aria3

**Goal:** Shopee / TikTok hit a **fast edge** (CDN PHP): verify signature, return **HTTP 200** with empty body, then **forward** the raw payload to aria3 asynchronously. Laravel remains authoritative for inbox, `marketplace_channel_orders`, and `on_order` (queued jobs unchanged).

**When to use:** Flash sales / traffic spikes where PHP-FPM on aria3 is contended **before** the app can return 200, or you want geographic edge close to platforms. Optional — direct `https://aria3…/shopee/push` stays valid for dev and low traffic.

---

## Architecture

```text
Shopee / TikTok
    │ POST (signed body + Authorization)
    ▼
https://cdn.corenationactive.com/shopeepush.php   (or tiktokshop-webhook.php)
    │ 1) HMAC verify (same rules as Open Platform docs)
    │ 2) echo 200; flush
    │ 3) background POST → aria3 (raw body preserved)
    ▼
https://aria3.corenationactive.com/shopee/push/relay   (or /tiktok-shop/webhook/relay)
    │ relay bearer (optional) + re-verify platform HMAC; enqueue Persist*Job
    ▼
Queue workers (existing jobs)
```

TikTok: same pattern with `app_key + raw_body` HMAC hex in `Authorization`.

---

## Shopee Push

### Console

Register **Push Mechanism → Set Push** callback to the **CDN URL**, e.g.:

```text
https://cdn.corenationactive.com/shopeepush.php
```

HMAC base string uses that exact URL: `{callbackUrl}|{rawBody}` with **Live Push Partner Key** (not Open API key). See `doc/shopee-push-test.md`.

### CDN environment

| Variable | Purpose |
|----------|---------|
| `SHOPEE_PUSH_PARTNER_KEY` | Live Push Partner Key (verify at edge) |
| `SHOPEE_PUSH_CALLBACK_URL` | Must match Console URL **exactly** (including path, no stray slash) |
| `ARIA_BASE_URL` | `https://aria3.corenationactive.com` |
| `ARIA_WEBHOOK_RELAY_BEARER` | Shared secret; sent as `Authorization: Bearer …` to aria3 relay route |

### Edge behaviour

1. Read `php://input` once; keep bytes for verify + forward.
2. If verify fails → **401** (platform may retry; do not ACK bad signatures).
3. If verify OK → **200** empty body; `fastcgi_finish_request()` or equivalent.
4. `POST` to `{ARIA_BASE_URL}/shopee/push/relay` with:
   - Body = **identical** raw JSON
   - `Authorization: Bearer {ARIA_WEBHOOK_RELAY_BEARER}`
   - `X-Shopee-Push-Authorization: {original Authorization header}` (required for aria3 HMAC)
   - `X-Shopee-Push-Callback-Url: {SHOPEE_PUSH_CALLBACK_URL}` (for HMAC base string)

5. Short HTTP timeout on forward (e.g. 5s); if aria3 is down, **log locally** — platform already got 200. Recovery: replay from CDN log or Shopee poll / `shopee:poll-orders`.

**Do not** register a `.php` URL for **OAuth redirect** (Shopee bans `bot` / `.php` on authorize URLs) — this relay is **Push only**.

### aria3

```env
SHOPEE_PUSH_RELAY_ENABLED=true
SHOPEE_PUSH_RELAY_BEARER=...        # matches CDN ARIA_WEBHOOK_RELAY_BEARER (optional when empty)
SHOPEE_PUSH_CALLBACK_URL=https://cdn.corenationactive.com/shopeepush.php
SHOPEE_PUSH_PARTNER_KEY=...         # same Live Push Partner Key as CDN
```

Relay handler: optional bearer → verify platform HMAC → `PersistShopeePushInboxJob` → 200.

Route: `POST /shopee/push/relay` (`shopee.push.relay`). Direct `/shopee/push` remains for maintenance / verify tests.

---

## TikTok Shop webhook

Partner Center webhook URL → CDN, e.g. `https://cdn.corenationactive.com/tiktokshop-webhook.php`.

| CDN env | Purpose |
|---------|---------|
| `TIKTOK_SHOP_APP_KEY` | Signature prefix |
| `TIKTOK_SHOP_APP_SECRET` | HMAC key |
| `ARIA_BASE_URL` / `ARIA_WEBHOOK_RELAY_BEARER` | Forward to `/tiktok-shop/webhook/relay` |

Verify: `HMAC-SHA256(app_key + raw_body, app_secret)` hex compared to `Authorization` header.

Forward headers:

- `Authorization: Bearer {ARIA_WEBHOOK_RELAY_BEARER}`
- `X-TikTok-Shop-Authorization: {original Authorization}`

```env
TIKTOK_SHOP_WEBHOOK_RELAY_ENABLED=true
TIKTOK_SHOP_WEBHOOK_RELAY_BEARER=...
```

Route: `POST /tiktok-shop/webhook/relay` (`tiktok-shop.webhook.relay`).

Return 200 within ~3s at edge; forward async same as Shopee.

---

## Security

- **Bearer** must be long random; HTTPS only; rotate with CDN + aria3 together.
- CDN must **verify platform signature before 200** — never ACK-unverified body.
- aria3 re-verifies platform HMAC on relay (defense in depth).
- Rate-limit CDN by IP if abuse; platforms use fixed egress ranges (document in firewall).

---

## Operations

| Check | Action |
|-------|--------|
| Console verify fails | CDN URL must return 2xx; test `curl` to CDN with/without sig |
| aria3 backlog | Scale queue workers, not CDN |
| Lost forwards | CDN access log + local dead-letter file; Shopee poll / TikTok order poll backup |
| Dual path | Avoid Console pointing at both CDN and aria3 — pick one callback URL |

---

## Rollout (recommended)

1. Deploy aria3 **relay routes** + env flags (no change to default direct webhooks).
2. Deploy CDN scripts from `doc/shopeepush-relay.php.example` and `doc/tiktokshop-webhook-relay.php.example` — **not committed with secrets**.
3. Smoke: Console verify → CDN → relay → one inbox row + job processed.
4. Cutover: change Console webhook URL to CDN.
5. Monitor queue lag during promo.

**Out of scope:** Replacing Laravel queue workers with another language; business logic stays in Aria.

## Related

- `doc/shopee-push-test.md` — keys, callback URL, simulate command
- `doc/tiktok-shop-webhook.md` — TikTok verify + inbox
- `doc/tiktok-ttsbot-relay.md` — OAuth relay (different path from webhooks)
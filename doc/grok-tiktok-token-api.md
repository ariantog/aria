# Grok bot — TikTok Shop token API (for bot maintainers)

Aria **pulls** TikTok `access_token` from this endpoint before live Get Order Detail. Shopee is the mirror image: Grok pulls from Aria `GET /api/readonly/shopee/oauth` (see `doc/grok-readonly-api.md`).

Omnichannel operator context: `doc/omnichannel.md` (Aria env + cache behavior).

## Route

Implement on the Grok bot HTTP server (separate from Shopee OAuth callback port):

| Item | Value |
|------|--------|
| Method | `GET` |
| Path | `/api/tiktok/oauth` (Aria `TIKTOK_SHOP_GROK_TOKEN_URL` is the full URL) |
| Auth | `Authorization: Bearer <shared secret>` — same value as Aria `TIKTOK_SHOP_GROK_TOKEN_BEARER` or `READONLY_EXPORT_BEARER_TOKEN` |

Optional query: `shop_ref` (TikTok shop id string from webhooks).

## Response (200)

```json
{
  "access_token": "shop-access-token",
  "expires_at": "2026-11-23T14:00:00+00:00",
  "expires_in": 7200
}
```

`expires_at` and/or `expires_in` strongly recommended so Aria does not call on every order expand. `shop_cipher` may be included as a hint; Aria still uses `tiktok_shop_syncs.shop_cipher` for Open API calls.

## Errors

Return non-2xx or JSON without `access_token` when the bot cannot refresh TikTok OAuth. Aria logs a warning and falls back to cached token or `TIKTOK_SHOP_ACCESS_TOKEN` if set.

## Bot responsibilities

- Own TikTok Shop OAuth (authorize, refresh) — same partner `APP_KEY` / secret as Aria env `TIKTOK_SHOP_APP_KEY` / `TIKTOK_SHOP_APP_SECRET`.
- Never expose `refresh_token` or app secret in this JSON.
- Keep tokens updated before `expires_at`; Aria only caches what Grok returns.

This repository does **not** ship the Grok route implementation; add it in the TikTok/Grok bot codebase alongside existing Shopee token persistence.
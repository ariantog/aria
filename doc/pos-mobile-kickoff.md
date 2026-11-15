# Mobile POS v1 — kickoff

**Platform:** Flutter (`apps/aria_mobile`), phone/tablet. **Posting:** same as web sell — `POST /api/mobile/v1/transactions/item` with `StoreItemTransactionRequest`. No Jubelio stock reserve; reminder only after post.

## Maintainer decisions (v1)

| # | Decision |
|---|----------|
| 1 | POS on **Flutter**, not desktop web |
| 2 | **Sell only** from POS shell (buy/move/cash stay on create sheet) |
| 3 | **Defaults** — warehouse + customer/reseller; remember per user (server preferences, like resi scan) |
| 4 | **Pricing/tax** — same as web (`CreateItemTransaction`, stored `ppn`) |
| 5 | After post: **58mm thermal print** + optional **WhatsApp** (invoice PDF link, same message pattern as web) |
| 6 | **No stock reserve**; staff push Jubelio manually when needed (reminder on success, no auto push) |

## Acceptance (shipped)

| Item | Implementation |
|------|----------------|
| Complete sell via API | `POST /transactions/item` with `type=sell`, `pos=true` for enriched JSON |
| 58mm print | `transactions/pdf/receipt.blade.php` + `TransactionInvoiceService::createReceiptPdf()` |
| Receipt download | `GET /api/mobile/v1/transactions/{id}/receipt` (Sanctum, inline PDF) |
| WhatsApp (optional) | `pos.whatsapp.url` when `whatsapp_phone` on request or saved default |
| Defaults per user | `preferences.pos` on `/me`; `PATCH /preferences/pos-defaults`; `remember_defaults` on sell |
| Jubelio reminder | `pos.jubelio_reminder` when warehouse has `jubeliosync` and `JUBELIO_ACTIVE` |

**Preference slugs:** `mobile.pos.sender_id`, `mobile.pos.receiver_id`, `mobile.pos.whatsapp_phone` (`UserPreferenceRegistry`). Falls back to web transaction defaults when mobile slugs unset.

## Flutter (`apps/aria_mobile`)

- **`PosSellScreen`** — scanner-first sell, defaults prefill, remember defaults, optional WA phone.
- After success: receipt bytes → `printing`; open `whatsapp.url`; Jubelio reminder banner.
- Shell **POS** FAB when `mobile_actions` includes `pos`.

## API (additive)

- `PATCH /api/mobile/v1/preferences/pos-defaults` — `sender_id`, `receiver_id`, `whatsapp_phone`
- `POST /api/mobile/v1/transactions/item` — `pos`, `remember_defaults`, `whatsapp_phone`
- `GET /api/mobile/v1/transactions/{id}/receipt` — 58mm PDF

See `doc/mobile-api.md` for the full mobile API table.

## Out of scope (v1)

- Cash-in at POS, returns, multi-pay, offline queue
- Auto Jubelio push / reservation
- Server-side `mobile_home_mode` (local pref; see `mobile-app-design-direction.md`)

## Tests

- `tests/Feature/MobilePosTest.php` — defaults, enriched sell, receipt download
- Receipt blade: `TransactionPrintExportTest` (58mm)

## Design reference

`apps/aria_mobile/doc/mobile-app-design-direction.md` — full POS home mode later; v1 ships dedicated **POS sell** first.

**Kickoff prompt for follow-up chats:**

```
Goal: Mobile POS v1 — sell-only on Flutter, defaults, 58mm print, optional WhatsApp, Jubelio reminder (no reserve).
Where: apps/aria_mobile + Mobile* controllers, TransactionInvoiceService, receipt blade 58mm.
Acceptance: Complete sell via API like web; print receipt on device; wa link; defaults persisted per user.
Notes: See doc/pos-mobile-kickoff.md
```
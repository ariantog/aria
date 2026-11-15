# Mobile POS (v1) — kickoff

**Platform:** Flutter app (`apps/aria_mobile`) on phone/tablet only. No new Blade POS page.

**Posting:** Reuse web sell path — `POST /api/mobile/v1/transactions/item` with `type=sell` and `StoreItemTransactionRequest` (same totals, discount %, PPN rules as web).

## Maintainer decisions (v1)

| # | Decision |
|---|----------|
| 1 | POS lives on **phone/tablet** (Flutter), not desktop web |
| 2 | **Sell only** — no buy/move/cash from POS shell |
| 3 | **Defaults required** — warehouse (sender) + customer/reseller (receiver); remember per user (server preferences, like resi scan) |
| 4 | **Pricing/tax** — identical to web sell (`CreateItemTransaction`, customer PKP / stored `ppn`) |
| 5 | After post: **58mm thermal print** + optional **WhatsApp** (invoice link message, same as web `transactions.whatsapp`) |
| 6 | **No stock reserve** for online channels; staff **push Jubelio manually** when the warehouse is online (existing transaction Jubelio sync UI — remind on success, do not auto-queue) |

## Existing building blocks

- `SellFormScreen` — barcode scan, lines, sell POST, prefill from `GET /api/mobile/v1/preferences/transaction-defaults` → `prefill.sell`
- User prefs: `transactions.default_warehouse_id`, `transactions.default_customer_id` (System Settings / profile)
- Web receipt PDF: `TransactionInvoiceService::createReceiptPdf()` + `GET /transactions/{id}/receipt` (A4 today; **v1 POS needs 58mm layout** — align with legacy `old/receipt.blade.php` or dedicated narrow PDF view)
- Web WhatsApp: `POST /transactions/{id}/whatsapp` with phone → `wa.me` + CDN invoice URL

## POS UX (v1 scope)

1. **Entry:** Permission `transactions-type-sell` + `transactions-create`. Optional `mobile_actions` flag `pos_sell` or reuse `sell` with dedicated route from shell.
2. **Flow:** Open POS → locked defaults (warehouse + customer) with “Ubah default” → scan/add lines → disc% / adj / notes like web → submit → success screen:
   - **Cetak struk** — fetch receipt PDF, print via Bluetooth thermal (Flutter plugin TBD; PDF bytes from API)
   - **WhatsApp** — optional phone field → open share / wa link
   - **Jubelio** — if sender warehouse has Jubelio mapping and `JUBELIO_ACTIVE`, show “Stok online: push dari transaksi” link (deep link or copy invoice id); **no** automatic Jubelio adjust
3. **Defaults API (new):** Mirror resi scan pattern:
   - `GET/PATCH /api/mobile/v1/pos/defaults` — `warehouse_id`, `customer_id`, `remember_defaults`
   - Slugs e.g. `mobile.pos.warehouse_id`, `mobile.pos.customer_id` in `UserPreferenceRegistry` (or reuse transaction default slugs only — product choice)

## Mobile API gaps (to implement)

| Need | Proposal |
|------|------------|
| 58mm receipt | `GET /api/mobile/v1/transactions/{id}/receipt` — generate/load receipt PDF, Bearer auth |
| WhatsApp | `POST /api/mobile/v1/transactions/{id}/whatsapp` — `{ "phone": "628..." }` → JSON `{ "wa_url": "..." }` (no redirect) |
| Jubelio hint | `GET /api/mobile/v1/transactions/{id}` include `jubelio_push_available`, `web_show_url` (optional) |

## Out of scope (v1)

- Cash-in at POS, returns, multi-pay split, offline queue
- Auto Jubelio stock push / reservation
- Tabulator / web POS

## Tests

- Feature: mobile receipt + WhatsApp JSON endpoints (auth, sell txn)
- Feature: POS defaults PATCH/GET
- Flutter: widget/smoke for POS screen (CI optional until layout stable)

## Branch hint

`cursor/mobile-pos-sell-1f91` off `main` after resi/tracking PRs merged.

## Design reference

`apps/aria_mobile/doc/mobile-app-design-direction.md` — POS home mode; v1 can ship **dedicated POS sell screen** first without full “POS mode” dashboard.

**Kickoff prompt for new chat:**

```
Goal: Mobile POS v1 — sell-only on Flutter, defaults, 58mm print, optional WhatsApp, Jubelio reminder (no reserve).
Where: apps/aria_mobile + Mobile* controllers, TransactionInvoiceService, receipt blade 58mm.
Acceptance: Complete sell via API like web; print receipt on device; wa link; defaults persisted per user.
Notes: See doc/pos-mobile-kickoff.md
```
# Mobile POS v1 kickoff

**Scope:** Sell-only counter flow on Flutter against `/api/mobile/v1` — same posting rules as web sell. No Jubelio stock reserve; reminder only after post.

## Acceptance

| Item | Implementation |
|------|------------------|
| Complete sell via API | `POST /transactions/item` with `type=sell`, optional `pos=true` for enriched JSON |
| 58mm print | `TransactionInvoiceService::createReceiptPdf()` + `transactions/pdf/receipt.blade.php`; `GET /transactions/{id}/receipt` (Sanctum) |
| WhatsApp (optional) | Response `whatsapp.url` when `whatsapp_phone` sent or saved in `mobile.pos.whatsapp_phone` |
| Defaults per user | `preferences.pos` on `/me`; `PATCH /preferences/pos-defaults`; `remember_defaults` on sell |
| Jubelio reminder | Response `jubelio_reminder` when warehouse has `jubeliosync` and `JUBELIO_ACTIVE` — no API push/reserve |

## API (additive)

- `PATCH /api/mobile/v1/preferences/pos-defaults` — `sender_id`, `receiver_id`, `whatsapp_phone` (nullable)
- `POST /api/mobile/v1/transactions/item` — body may include `pos`, `remember_defaults`, `whatsapp_phone`
- `GET /api/mobile/v1/transactions/{id}/receipt` — inline PDF (58mm)

## Flutter (`apps/aria_mobile`)

- **POS Jual** screen (sell-only): scanner-first, defaults prefill, remember defaults checkbox, optional WA phone.
- After success: fetch receipt bytes → `printing` package; open `whatsapp.url` if present; show Jubelio reminder banner.

## Out of scope (v1)

- Buy/move/cash from POS shell (use existing create sheet).
- Jubelio outbound push or reserved qty from mobile.
- Server-side `mobile_home_mode` (local SharedPreferences only — see design doc).
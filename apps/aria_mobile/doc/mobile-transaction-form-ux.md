# Aria mobile — transaction form UX proposal

**Audience:** Product / maintainer review (Flutter app `apps/aria_mobile/`).  
**Status:** Proposal — not implemented beyond label-spacing fix (PR #1014).

## Current pain

Warehouse and floor staff enter **buy, sell, move, cash, and transfer** on phones that are often held in **landscape** outdoors or at a loading dock.

| Issue | What users see |
|-------|----------------|
| **Stacked outline fields with no vertical rhythm** | Material floating labels on back-to-back `TextField`s collide; long labels (“Invoice discount %”, “Adjustment (optional)”) overlap the field above. |
| **Flat information hierarchy** | Date, parties, invoice metadata, discount, lines, and submit share one scroll with equal visual weight — no cues for “required first” vs “optional later”. |
| **Mixed control types without grouping** | Party pickers (`ListTile` + search sheet) sit between outline fields; the flow reads as one long form, not “who → what → how much”. |
| **Landscape width underused** | Wide screens still use a single column; two-column header rows are rare, so thumb reach and scanning stay poor. |
| **Line items as repeated cards** | Each SKU is a mini-form (qty/price + delete); with many lines the submit button drifts far below the fold. |

Dark theme with teal accents is already in use; spacing fixes must stay legible in both themes.

## References (external patterns)

1. [Material Design 3 — Text fields](https://m3.material.io/components/text-fields/overview) — when to use outline vs filled, label behavior, and density.
2. [Flutter — Build and display a form](https://docs.flutter.dev/cookbook/forms) — `Form` / validation, focus order, and keyboard types.
3. [Material 3 for Flutter (`flutter/material`)](https://docs.flutter.dev/ui/widgets/material) — sections, `Card`, and responsive layout primitives.
4. [Shopify POS — design principles](https://www.shopify.com/pos/features) — speed-first retail flows (scan → adjust → pay); useful mental model for **sell/buy at the dock**.
5. [Stripe Dashboard mobile](https://stripe.com/docs/dashboard/mobile) — B2B “glanceable totals + drill-down” on a small screen; relevant for **sticky totals** before submit.

## Proposed layout directions

### Direction A — Stepped wizard (Party → Lines → Totals)

```mermaid
flowchart LR
  S1[1 Party + date] --> S2[2 Lines scan/list]
  S2 --> S3[3 Invoice options + review]
  S3 --> Submit[Submit]
```

**Layout sketch (portrait step 2):**

```
┌─────────────────────────┐
│ Sell          Step 2/3  │
├─────────────────────────┤
│ [Scan barcode] [Manual] │
│ ┌─ Line 1 ─────────────┐│
│ │ RUNNING SHIRT …      ││
│ │ Qty [ 1 ]  Price [ …]││
│ └──────────────────────┘│
│ Subtotal (preview)      │
│ [ Back ]    [ Next → ]  │
└─────────────────────────┘
```

| Pros | Cons |
|------|------|
| Clear mandatory order; fewer fields on screen at once | Extra taps; harder to fix party after lines unless step nav allows back |
| Fits one-handed scan workflow on step 2 | Wizard state must persist on rotate / app background |
| Natural place for **review + totals** before POST | More routing / state than today’s single `ListView` |

**Warehouse staff fit:** Strong for **sell/move** when scanning dominates; weaker for quick **cash/transfer** with only four fields.

---

### Direction B — Single scroll with collapsed sections + sticky submit

```ascii
+----------------------------------+
| Sell                    [Submit] |  <- sticky bar (disabled until valid)
+----------------------------------+
| v Party & date                   |
|   Date | Warehouse | Customer    |
+----------------------------------+
| v Lines (3)                      |
|   [Scan]  cards…                 |
+----------------------------------+
| > Invoice options (collapsed)    |
+----------------------------------+
```

| Pros | Cons |
|------|------|
| One mental model: “everything on one page” like web Aria | Still long with many lines unless lines section paginates |
| **ExpansionTile** / section headers fix hierarchy without new routes | Collapsed sections hide errors until expand |
| Sticky submit + live total matches Stripe-like review | Section open/close state per txn type to design |

**Warehouse staff fit:** Best default for **mixed txn types**; optional sections keep discount/adjustment out of the critical path (aligns with shipped **Invoice options** card on buy/sell).

## Recommendation (short)

1. **Ship spacing + section card** (done in code fix) as baseline.
2. **Pilot Direction B** on buy/sell first: `Party & date` → `Lines` → `Invoice options` (collapsed) + **sticky footer** with line count and signed total preview (read-only from client math, server still authoritative).
3. Revisit **Direction A** only for a dedicated **“fast sell”** entry from the scanner FAB if metrics show users abandoning multi-field scroll.

## Out of scope for this doc

- PPN / entity-specific tax toggles (domain rules differ from web; mirror `record_ppn` gating later).
- Offline queue / idempotency (already on API; UX for retry banners is separate).
- Full visual redesign of `AddrbookPickerField` (consider outline parity or chip selected state in a follow-up).

## Related repo paths

| Item | Path |
|------|------|
| Shared field spacing | `apps/aria_mobile/lib/widgets/transaction_form_fields.dart` |
| Buy / sell invoice options card | `buy_form_screen.dart`, `sell_form_screen.dart` |
| Web reference forms | `resources/views/transactions/create.blade.php`, `cash.blade.php` |

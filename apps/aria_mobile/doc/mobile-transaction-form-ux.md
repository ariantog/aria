# Aria mobile — transaction form UX proposal

**Audience:** Product / maintainer review (Flutter app `apps/aria_mobile/`).  
**Status:** Label-spacing shipped (PR #1014). **App shell / home:** see [`mobile-app-design-direction.md`](mobile-app-design-direction.md).

**Parent scope:** Phase 1 = cash (in/out/transfer) + inventory (buy/sell/move/POS). Phase 2 = reports, users.

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

1. [Material Design 3 — Text fields](https://m3.material.io/components/text-fields/overview)
2. [Flutter — Build and display a form](https://docs.flutter.dev/cookbook/forms)
3. [Material 3 for Flutter](https://docs.flutter.dev/ui/widgets/material)
4. [Shopify POS](https://www.shopify.com/pos/features)
5. [Stripe Dashboard mobile](https://stripe.com/docs/dashboard/mobile)

## Proposed layout directions

### Direction A — Stepped wizard (Party → Lines → Totals)

Best for scan-heavy sell/move; extra navigation cost for cash/transfer.

### Direction B — Single scroll with collapsed sections + sticky submit

**Recommended** for mixed txn types; aligns with shipped **Invoice options** card on buy/sell.

## Recommendation (short)

1. **Spacing + section card** — shipped.
2. **Pilot Direction B** on buy/sell with sticky footer totals preview.
3. **Fast sell wizard** only if data shows scroll abandonment.

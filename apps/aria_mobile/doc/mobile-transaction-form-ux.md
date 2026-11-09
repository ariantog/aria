# Aria mobile — transaction form UX proposal

**Audience:** Product / maintainer review  

**Status:** Label-spacing shipped (PR #1014). Home shell and POS vs Standard: [`mobile-app-design-direction.md`](mobile-app-design-direction.md).

**Scope:** Phase 1 forms for cash (in/out/transfer) and inventory (buy/sell/move). POS mode affects **home**, not a separate sell form — sell form gets scanner-first emphasis when `mobile_home_mode == pos`.

---

## Current pain

| Issue | What users see |
|-------|----------------|
| Stacked outline fields | Floating labels collide in landscape |
| Flat hierarchy | Party, lines, invoice options, submit all look equally important |
| Mixed controls | Addrbook pickers between outline fields without grouping |
| Landscape | Single column; thumb reach poor |
| Line cards | Many SKUs push submit far below fold |

Teal/dark theme must keep contrast after spacing fixes.

---

## References

1. [Material Design 3 — Text fields](https://m3.material.io/components/text-fields/overview)
2. [Flutter — Build and display a form](https://docs.flutter.dev/cookbook/forms)
3. [Material 3 for Flutter](https://docs.flutter.dev/ui/widgets/material)
4. [Shopify POS](https://www.shopify.com/pos/features)
5. [Stripe Dashboard mobile](https://stripe.com/docs/dashboard/mobile)

---

## Direction A — Wizard (Party → Lines → Totals)

Good for scan-heavy sell/move; extra taps for cash/transfer.

## Direction B — Collapsible sections + sticky submit (recommended)

Matches web mental model; **Invoice options** card already on buy/sell.

```
+----------------------------------+
| Sell                    [Submit] |
+----------------------------------+
| v Party & date                   |
| v Lines                          |
| > Invoice options (collapsed)    |
+----------------------------------+
```

---

## Recommendation

1. **Spacing + Invoice options card** — done (`transaction_form_fields.dart`).
2. **Direction B** on buy/sell: sticky footer with line count + payable preview (client estimate; server authoritative).
3. **Wizard (A)** only for optional “fast sell” entry if analytics warrant it.

---

## Out of scope here

PPN/entity rules, offline retry UX, full addrbook picker redesign.

---

## Code map

| Item | Path |
|------|------|
| Shared field spacing | `lib/widgets/transaction_form_fields.dart` |
| Invoice options card | `lib/screens/buy_form_screen.dart`, `sell_form_screen.dart` |
| Web reference | `resources/views/transactions/create.blade.php`, `cash.blade.php` |

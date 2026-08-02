---
name: Aria transaction domain model
description: Enum casts and the single polymorphic contacts table — the two things that trip up new views and queries.
---

Two domain facts that are easy to get wrong and cause silent breakage:

**1. `type` and `status` on a transaction are backed enums, not ints.**
Reading the attribute returns an enum instance. Any array-map lookup keyed by int
(`$typeMap[$tx->type]`) silently misses and falls through to a default. Always take the backing
value first (`$tx->type->value`) or use the enum's own `label()` method. Writing an int is fine —
the cast handles it.

**2. Every counterparty is a row in one `addrbooks` table, discriminated by a `type` column.**
Banks, warehouses, customers, suppliers, resellers, virtual warehouses and journal accounts are all
the same model. A transaction's sender and receiver both point there, so a "bank transfer" and a
"sell to customer" are structurally identical rows differing only by the counterparties' types.

**Why:** `config/transaction_rules.php` is the single source of truth for which addrbook types are
legal as sender/receiver per transaction type, and those constraints are expressed as *arrays* of
type ids (a sell can go to a customer OR a reseller). Treating them as scalars breaks — interpolating
one into a Blade `{{ }}` throws `htmlspecialchars(): must be of type string, array given`.

**How to apply:** When building a form or query for a transaction type, read the legal
sender/receiver types from `config/transaction_rules.php` rather than hardcoding. Note the store
request validates `sender_id`/`receiver_id` only — it does not accept or need `sender_type` /
`receiver_type`, so don't send them from the frontend.

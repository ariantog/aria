# Greenfield MySQL migration audit (INT vs BIGINT)

New-subdomain MySQL uses Laravel `$table->id()` on `customers`, `items`, `users`, `tags`, `item_group` → **BIGINT UNSIGNED** PKs. Production L10 clones use **INT(11)**.

MySQL errno **150** appears when a migration adds a **foreign key** whose column type does not match the referenced PK.

## Detection

`App\Support\GreenfieldMysqlSchema::usesBigintLegacyPrimaryKeys()` — true on MySQL when `customers.id` is BIGINT.

Use `GreenfieldMysqlSchema::legacyReferenceIdColumn()` and `add*ForeignKey()` helpers in any migration that creates production-style reference columns or FKs.

## No-op on greenfield (L10 clone bundles)

These must not shrink BIGINT → INT on a new domain:

| Migration | Reason |
|-----------|--------|
| `2026_08_13_100000_production_database_bootstrap` | Re-runs prod install + `fix_production_bigint_columns_to_int` |
| `2026_08_13_130000_fix_production_bigint_columns_to_int` | MODIFY legacy refs to INT(11) |
| `2026_08_21_100000_reapply_production_defaults_and_int_keys` | Re-runs fix + shrinks `standalone_invoices` keys |

## Install / CREATE paths using `GreenfieldMysqlSchema` (errno 150)

- `2026_08_12_200000_install_l12_production_tables`
- `2026_08_18_120000_create_warehouse_arrangement_refresh_jobs_table`
- `2026_08_22_110000_install_reporting_tables` (CREATE + skip `ensureIntegerColumn` on greenfield)
- `2026_08_19_070000_install_standalone_invoice_tables` (CREATE keys)
- `2026_08_29_100000_install_depreciation_register`
- `2026_08_29_100000_install_reporting_neraca_tables`
- `2026_08_31_100000_install_inventory_health_snapshots_table`
- `2026_09_01_210000_install_jubelio_stock_check_columns` (`warehouse_id`)

## Early greenfield migrations (already BIGINT-safe FKs)

These run before prod-style installs and use `foreignId` / `unsignedBigInteger` + FK:

- `customerstat`, `customer_class`, `stat_sells`, `warehouse_arrangement_*` (Aug 2026 create)
- `transactions.a_submit_by` / `b_submit_by` (`bigInteger`)
- `jubelio_stock_discrepancies.item_id` (`unsignedBigInteger` + FK)

## `unsignedInteger` refs **without** FK (migrate OK; not ideal long-term)

No errno 150, but column is narrower than BIGINT PKs. Fine while ids stay within 32-bit range.

Examples: `install_user_preferences`, `install_tax_faktur_imports`, `install_item_stock_notifications`, `install_staff_role_checklists`, `install_absensi_tables`, `install_item_insight_tables`, `jubelioorders.warehouse_id`, `jubeliosyncs` warehouse/customer ids, many payroll tables.

Widen with `GreenfieldMysqlSchema::legacyReferenceIdColumn()` when touching those migrations.

## Maintainer checklist for new migrations

1. Referencing `customers` / `items` / `users` / `tags` / `item_group` on MySQL?
2. If **FK** → use `GreenfieldMysqlSchema` helpers (or `foreignId` on greenfield-only tables).
3. If **production clone ALTER** shrinking BIGINT → INT → guard with `usesBigintLegacyPrimaryKeys()` no-op.
4. Do not add new `ensureIntegerColumn` / raw `MODIFY … INT(11)` without the same guard.

# Schema decisions — L12 on shared L10 production DB

This documents what L12 **uses**, **ignores**, and **adds** relative to `database/old.sql` (production truth).

## customers (L12 model: `Addrbook`)

| Production column | L12 usage |
|-------------------|-----------|
| `id`, `name`, `type`, `address`, `phone`, `email`, `description`, `ppn`, `is_online`, `deleted_at`, timestamps | Used |
| `memberId` | Used — search on location assignment + addrbook list |
| `operation_id`, `arrangement_enabled`, `contact_person` | L12-only — added by align migration |
| `phone2`, `fax`, `category`, `discount`, `birthdate`, `return_p`, `contract_ends`, `parent_id`, `city_id`, `province_id`, `password`, `portalId` | **Ignored** — L10 legacy; columns stay in DB, L12 never reads/writes |

L12 does not need these columns removed from production. L10 may still write them until retired.

## customerstat (L12 model: `AddrbookStat`)

| Column | L12 usage |
|--------|-----------|
| `customer_id` (PK), `balance`, timestamps | Used |
| `rating` | **Ignored** — not read or written by L12 |

## customer_class (L12 model: `AddrbookDaily`)

| Column | L12 usage |
|--------|-----------|
| `customer_id`, `customer_type`, `date`, bucket cols (`sell`, `buy`, `cash_in`, …) | Used |
| `class` | Written as empty string on create (production NOT NULL) |
| `rating` | **Ignored** |

## warehouse_item (L12 model: `WarehouseItem`)

Production has `id`, `item_id`, `warehouse_id`, `quantity` only.

L12 align migration adds (guarded): `warehouse_type`, `note`, `timestamps`.

Greenfield SQLite dev creates the full L12 shape from scratch — no conflict.

## transactions

**Not morph in production.** Production uses integer columns:

- `sender_id`, `receiver_id` — `customers.id`
- `sender_type`, `receiver_type` — addrbook type tinyint (`1` customer, `2` warehouse, …)

L12 code writes integers everywhere (`CreateItemTransaction`, Jubelio, reports). Greenfield migration must match (no `nullableMorphs`).

| L12 column | Production column | Notes |
|------------|-------------------|-------|
| `invoice` | `invoice` | |
| `due` | `due` | |
| `ppn` | `ppn` | tax amount |
| `discount` | `discount` | **percent only** (not amount) |
| `real_total` | `real_total` | |
| `notes` | — | L12-only (align migration adds) |

Production also has `detail_ids`, `cogs`, `location_id`, `real_total`, etc. L12 reads/writes overlapping subset; extra prod columns are left untouched.

## prod_produksi (L12 model: `Produksi`)

| Column | L12 usage |
|--------|-----------|
| `user_id` | **Required on create** — user who created the entry |
| `permak` | **Keep column** — tinyint flag; L12 does not use in UI yet; leave existing values |
| `description` | **Unused** — do not write from L12; column remains for legacy rows |
| `potong_id`, `jahit_id`, `qc_id`, `pritil_id`, dates, `status`, `temp_name`, `customer`, `warna`, `quantity`, `size_id`, `surat_jalan_potong`, `original_id`, `transaction_id`, … | Used per workflow |

## items / asset lancar

Single table `items`, `type` `1` = item, `2` = asset lancar. No separate asset table. See `doc/migration-runbook.md` for parallel L10/L12 notes.

## Seeders on production copy

| Seeder | On prod DB copy? |
|--------|------------------|
| `SuperAdminSeeder` | **No** — users already exist |
| `DemoDataSeeder` | **No** |
| `SettingSeeder` | **Maybe** — only if missing keys (usually prod has `settings`) |
| Permission sync (tinker) | **Yes** — after deploy, sync new L12 permissions to roles |

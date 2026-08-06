# 05 — Data Model Additions

Every new table, column and setting the design needs, in dependency order. Column names are
proposals — they are consistent with existing conventions (`snake_case`, `decimal(15,2)` for money,
`decimal(5,2)` for rates, nullable FKs with `nullOnDelete`).

Per `AGENTS.md`: new dated migrations only, never edit an existing one. Update `$fillable` /
`$guarded` and `$casts` on the affected models in the same change.

---

## 1. Tax identity

### 1.1 `add_tax_fields_to_addrbooks_table`

| Column | Type | Null | Notes |
|---|---|---|---|
| `npwp` | `string(25)` | yes | 16-digit since 2024; stored with formatting stripped, indexed |
| `nik` | `string(20)` | yes | for individual buyers without NPWP |
| `nitku` | `string(25)` | yes | branch identifier |
| `is_pkp` | `boolean` | no, default `false` | determines input-VAT creditability |
| `pkp_since` | `date` | yes | |
| `tax_name` | `string` | yes | legal name on the faktur; falls back to `name` |
| `tax_address` | `text` | yes | falls back to `address` |
| `tax_treatment` | `string(20)` | no, default `none` | `exclusive` \| `inclusive` \| `none` — see `02` §3 |
| `default_kode_transaksi` | `string(2)` | yes | usually `01` |

Data migration: `tax_treatment = ppn ? 'exclusive' : 'none'`. Keep `addrbooks.ppn` — it is read by
`CalculatesTransactionTotals`, the lookup API and the Alpine form, and removing it is a separate
concern.

### 1.2 Company profile

No new table — a `Company` settings group, consistent with how `restock.*` and `si_*` already work.

| Slug | Example |
|---|---|
| `company.name` | legal entity name |
| `company.npwp` | |
| `company.nitku` | |
| `company.address` | |
| `company.klu` | KLU code |
| `company.is_pkp` | boolean |
| `company.pkp_since` | date |
| `company.faktur_signatory_name` | |
| `company.taxpayer_type` | `badan` \| `orang_pribadi` |
| `company.fiscal_year_start_month` | default `1` |

---

## 2. Tax rates and per-transaction tax detail

### 2.1 `create_tax_rates_table`

Effective-dated, so recomputing a closed period uses the rate that applied then.

| Column | Type | Notes |
|---|---|---|
| `id` | id | |
| `tax_type` | `string(20)` | `ppn` \| `pph21` \| `pph23` \| `pph4_2` |
| `code` | `string(30)` | e.g. `ppn_umum`, `ppn_nilai_lain`, `pph23_jasa` |
| `rate` | `decimal(6,3)` | nominal rate, e.g. `12.000` |
| `dpp_factor` | `decimal(8,6)` | e.g. `0.916667` for 11/12; `1.000000` otherwise |
| `effective_from` | `date` | |
| `effective_to` | `date` nullable | |
| `description` | `string` nullable | |

Index `(tax_type, code, effective_from)`.

Seed: `ppn_nilai_lain` rate `12.000`, `dpp_factor` `0.916667`, from `2025-01-01`; `ppn_umum` rate
`11.000`, factor `1.000000`, from `2022-04-01` to `2024-12-31`.

`Setting::getValue('ppn_rate')` stays as the fallback so nothing breaks before the table is
populated.

### 2.2 `add_tax_detail_to_transactions_table`

Existing `tax_amount` keeps its meaning and value. These make its components explicit.

| Column | Type | Notes |
|---|---|---|
| `taxable_base` | `decimal(15,2)` default `0` | pre-nilai-lain amount |
| `dpp` | `decimal(15,2)` default `0` | `taxable_base × dpp_factor` |
| `dpp_basis` | `string(20)` nullable | `nilai_lain_11_12` \| `harga_jual` |
| `ppn_rate` | `decimal(6,3)` default `0` | nominal rate applied |
| `ppnbm_amount` | `decimal(15,2)` default `0` | |
| `tax_treatment` | `string(20)` nullable | snapshot of the addrbook's treatment at posting time |
| `faktur_number` | `string(25)` nullable | 17-digit NSFP, indexed |
| `faktur_date` | `date` nullable | |
| `faktur_kode_transaksi` | `string(2)` nullable | |
| `faktur_status` | `string(2)` nullable | `00` normal, `01`+ pengganti |
| `nota_retur_ref_id` | `foreignId` nullable | the transaction whose faktur this reverses |
| `tax_period` | `string(7)` nullable | `YYYY-MM`, indexed — masa pajak, may differ from `date` |

Mirror on `deleted_transactions`, which already tracks `tax_amount` and `discount_percent`.

Index `(tax_period, type)` — every tax report filters on exactly this.

Also cast `tax_amount`, `sender_balance` and `receiver_balance` to `decimal:2` on the `Transaction`
model; they are currently uncast.

---

## 3. Account classification

### 3.1 `add_classification_to_operations_table`

| Column | Type | Notes |
|---|---|---|
| `account_code` | `string(20)` nullable, indexed | e.g. `5-2100` |
| `account_type` | `string(20)` nullable | `asset` \| `liability` \| `equity` \| `revenue` \| `cogs` \| `expense` \| `other_income` \| `other_expense` |
| `statement_line` | `string(40)` nullable | e.g. `beban_operasional` |
| `normal_balance` | `string(6)` nullable | `debit` \| `credit` |
| `is_cash` | `boolean` default `false` | |
| `fiscal_correction` | `string(10)` default `none` | `none` \| `positive` \| `negative` |
| `sort_order` | `integer` default `0` | |

### 3.2 `add_account_overrides_to_addrbooks_table`

Same six classification columns, all nullable, as per-addrbook overrides. Resolution order:
addrbook override → its operation → `config/accounting.php` type default → `suspense`.

### 3.3 `config/accounting.php`

Not a migration, but part of the model. Holds the type-level defaults from `03` §2.3, the statement
line definitions with their order and labels, and the sign rules. Config rather than a table because
it is structural rather than user-edited, and it makes the mapping diffable in review.

---

## 4. Periods and snapshots

### 4.1 `create_accounting_periods_table`

| Column | Type | Notes |
|---|---|---|
| `year` | `smallInteger` | |
| `month` | `tinyInteger` | |
| `status` | `string(10)` default `open` | `open` \| `closed` |
| `closed_at` | `timestamp` nullable | |
| `closed_by` | `foreignId` nullable → `users` | |
| `notes` | `text` nullable | |

Unique `(year, month)`.

### 4.2 `create_period_balances_table`

| Column | Type | Notes |
|---|---|---|
| `year`, `month` | smallint / tinyint | |
| `addrbook_id` | `foreignId` → `addrbooks` | |
| `account_type`, `statement_line` | `string` | snapshot of classification at close time |
| `opening_balance` | `decimal(15,2)` | |
| `movement_debit` | `decimal(15,2)` | |
| `movement_credit` | `decimal(15,2)` | |
| `closing_balance` | `decimal(15,2)` | |

Unique `(year, month, addrbook_id)`.

Classification is snapshotted deliberately: reclassifying an account later must not silently
restate a filed period.

### 4.3 `create_period_inventory_table`

| Column | Type | Notes |
|---|---|---|
| `year`, `month` | smallint / tinyint | |
| `bucket` | `string(20)` | `bahan_baku` \| `wip` \| `barang_jadi` |
| `opening_value` | `decimal(15,2)` | |
| `purchases_value` | `decimal(15,2)` | |
| `consumption_value` | `decimal(15,2)` | |
| `production_value` | `decimal(15,2)` | |
| `cogs_value` | `decimal(15,2)` | |
| `computed_closing` | `decimal(15,2)` | |
| `opname_closing` | `decimal(15,2)` nullable | |
| `selisih` | `decimal(15,2)` default `0` | `opname − computed`, when opname exists |
| `method` | `string(12)` | `computed` \| `opname` |
| `notes` | `text` nullable | |

Unique `(year, month, bucket)`.

---

## 5. Standard cost

### 5.1 `add_standard_costs_to_items_table`

| Column | Type |
|---|---|
| `standard_material_cost` | `decimal(15,2)` nullable |
| `standard_labour_cost` | `decimal(15,2)` nullable |
| `standard_overhead_cost` | `decimal(15,2)` nullable |
| `standard_cost_effective_from` | `date` nullable |

### 5.2 `add_standard_costs_to_item_groups_table`

Same four columns, used as the fallback when the item has none.

`standard_labour_cost` can be backfilled from `tags.price` on each item's TYPE_JAHIT tag — the same
lookup `BoronganController::findBorongan()` already performs.

---

## 6. Payroll and labour posting

### 6.1 `add_posting_to_borongans_table`

| Column | Type | Notes |
|---|---|---|
| `paid_at` | `date` nullable | |
| `transaction_id` | `foreignId` nullable → `transactions` | the auto-posted CashOut |
| `pph_type` | `string(10)` nullable | `pph21` \| `pph23` \| `none` |
| `pph_amount` | `decimal(15,2)` default `0` | |
| `working_days` | `integer` nullable | for the TER Harian average |

### 6.2 `add_posting_to_gajis_table`

`paid_at`, `transaction_id`, `pph21_amount`, `dpp_pph21`.

### 6.3 `add_tax_identity_to_workers_table` and `..._to_karyawans_table`

`nik`, `npwp`, `ptkp_status` (`TK/0`, `K/0`, `K/1` …), `is_permanent` on `karyawans`;
`nik`, `npwp` on `workers`.

---

## 7. Fixed assets

`items.type = 3 (ASSET_TETAP)` and `TransactionType::Depreciation (18)` exist but carry no
depreciation data. Needed for the neraca aktiva tetap line and the SPT 1771 daftar penyusutan.

### `create_fixed_assets_table`

| Column | Type | Notes |
|---|---|---|
| `item_id` | `foreignId` nullable → `items` | link to the existing asset item if there is one |
| `name` | `string` | |
| `acquired_at` | `date` | |
| `acquisition_cost` | `decimal(15,2)` | |
| `salvage_value` | `decimal(15,2)` default `0` | |
| `useful_life_months` | `integer` | commercial |
| `method` | `string(20)` | `garis_lurus` \| `saldo_menurun` |
| `fiscal_group` | `string(20)` nullable | `kelompok_1` … `kelompok_4`, `bangunan_permanen`, `bangunan_non_permanen` |
| `fiscal_life_months` | `integer` nullable | per Pasal 11 UU PPh, drives koreksi fiskal |
| `accumulated_depreciation` | `decimal(15,2)` default `0` | |
| `disposed_at` | `date` nullable | |
| `location_id` | `foreignId` nullable → `locations` | |

Alternative: extend `items` instead of a new table. A separate table is cleaner — most of these
columns are meaningless for the ~all items that are garments, and `items` is already wide.

---

## 8. Settings summary

New rows for `SettingSeeder`, all using the existing group/slug/JSON-value shape.

| Group | Slug |
|---|---|
| Company | `company.name`, `company.npwp`, `company.nitku`, `company.address`, `company.klu`, `company.is_pkp`, `company.pkp_since`, `company.faktur_signatory_name`, `company.taxpayer_type`, `company.fiscal_year_start_month` |
| Inventory | `inventory.material_purchase_account_ids`, `inventory.opening_bahan_baku`, `inventory.opening_period`, `inventory.material_to_labour_ratio`, `inventory.default_overhead_per_unit`, `inventory.wip_completion_factor` |
| Payroll | `payroll.borongan_expense_account_id`, `payroll.gaji_expense_account_id`, `payroll.pph21_payable_account_id` |
| Accounting | `accounting.opening_equity`, `accounting.retained_earnings_account_id`, `accounting.ppn_payable_account_id`, `accounting.ppn_input_account_id` |

---

## 9. Migration order

Later migrations depend on earlier ones, mainly through foreign keys and the classification
resolution chain.

1. `add_tax_fields_to_addrbooks_table`
2. `create_tax_rates_table`
3. `add_tax_detail_to_transactions_table` (+ `deleted_transactions` mirror)
4. `add_classification_to_operations_table`
5. `add_account_overrides_to_addrbooks_table`
6. `create_accounting_periods_table`
7. `create_period_balances_table`
8. `add_standard_costs_to_items_table`, `add_standard_costs_to_item_groups_table`
9. `create_period_inventory_table`
10. `add_posting_to_borongans_table`, `add_posting_to_gajis_table`
11. `add_tax_identity_to_workers_table`, `add_tax_identity_to_karyawans_table`
12. `create_fixed_assets_table`

Steps 1–7 are Phase 0/1 and carry no behaviour change — they add nullable columns and empty tables.
Steps 8–12 land with the features that use them.

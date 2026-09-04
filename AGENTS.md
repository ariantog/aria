# AGENTS.md

Aria Core — a Laravel 12 inventory / accounting / transaction ERP, server-rendered with
**Blade + Alpine.js** (Tailwind + Alpine load from CDN; the old React/Inertia SPA has been
removed). Indonesian domain terms throughout.

## Project state — already done (do NOT redo or worry about)

The migration from the React/Inertia SPA to Blade+Alpine and a batch of UI/bug fixes are
**complete and merged**. A new chat should build on this, not revisit it:

- **React / Inertia / Vite / TypeScript stack is fully removed** — see **AI agent restrictions** (do not
  reintroduce a SPA or JS build step). Frontend is Blade + Alpine + Tailwind (CDN).
- **Blank list pages are fixed.** `items`, `assetlancar`, and `addrbook` index/transactions/items
  pages used a never-loaded Tabulator.js and rendered empty — they are now plain server-rendered HTML
  tables with Laravel pagination. Tabulator is reserved for the (not-yet-built) restock page only.
- **Transactions list is shared.** The `/transactions` table lives in
  `resources/views/transactions/partials/list-table.blade.php` and is reused by
  `addrbook/{id}/transactions` (balance shown under sender/receiver; the viewed contact is bolded).
- **Sidebar** (`resources/views/partials/sidebar-nav.blade.php`) has Journals / Produksi / Borongan
  links restored, gated by `journal-*` / `production-*` / `borongan-*` permissions or superadmin.
- **Superadmin (user 1) sees real balances** — it is exempt from the `bank_hidden_balance` check.
- **Transaction entry forms are inline + keyboard-driven** (cash-in/out and buy/sell/return/
  return-supplier): barcode/autocomplete lookup, discount in %, optional PPN (not on every
  invoice — see reporting entities below), AJAX submit that keeps inputs + highlights invalid
  rows on validation error, and a submit button gated by client-side validation
  (see `transactions/create.blade.php`, `transactions/cash.blade.php`).
- **Palette normalized to `gray-*`** (journals/produksi were `zinc-*`); page-load slide-in animation
  removed.
- **Transaction backend (signed posting + balances + Jubelio sync UI) is shipped.** Back-dated
  insert/edit/delete recalculates later running balances (`TransactionObserver`, `TransactionService`,
  `TransactionBalanceIntegrityTest`). Transaction show has Jubelio stock-sync buttons
  (`resources/views/transactions/partials/jubelio-sync.blade.php`, `JubelioService`, dormant while
  `JUBELIO_ACTIVE=false`). Manual batch tool: System Settings → Running Balances
  (`/recalculate-running-balances`).
- **Item catalog on `item_group` is shipped** (`App\Support\ItemCatalog`, `item_group.brand` /
  `genre`). Leftover `items.*` mirror columns stay for L10 parity; new shared attributes belong on
  the group. Colorway edit page, per-size price, asset TYPE→pcode autofill, and `item_group.name`
  schema (varchar 255, not UNIQUE) landed in PRs **#575–#587** — see **Item group & item identity**.
- **L12 reporting stack is shipped** (Blade reports + summary tables) — see **Reporting (L12)**.
  **Maintainer will still request confirmation and modifications**; do not treat report output as final.

Already-fixed gotchas — don't reintroduce them:
- Read query params with `request()->query('x')`, **not** `request('x')`, on routes that also have a
  route segment named `x` (e.g. `{type}`) — otherwise the segment leaks into the query.
- Never call `->wherePivot(...)` inside a `when()` closure that receives the **base** builder; use the
  qualified column instead (e.g. `where('warehouse_items.quantity', '>', 0)`).
- PHP **8.5 is not supported** by `phpoffice/phpspreadsheet`; the CI matrix is `8.3`/`8.4`.

## Production database safety (MUST follow in every PR)

See also **AI agent restrictions** — agents may only add columns or change default values on legacy
tables unless the user explicitly requests another schema change.

The app runs against the **live L10 production MySQL database** (schema snapshot:
`database/old.sql`; the legacy L10 app runs on it in parallel). Migrations are deployed to
production by running them individually with `php artisan migrate --path=...`, so **every
migration file must be production-safe on its own**:

- **NEVER drop a production table.** Any table listed in `database/old.sql` must never appear
  in `Schema::drop`, `Schema::dropIfExists`, a raw `DROP TABLE`, or a `down()` that could run on
  prod. Do not use `migrate:fresh`/`migrate:refresh` anywhere but local SQLite.
- **To change an existing table, ALTER it in place — never drop-and-recreate it.** No
  "drop old table first, then create the new shape". Use guarded, additive changes:
  `Schema::hasTable()` / `hasColumn()` around every ALTER, renames via `CHANGE` so data is kept.
- **Never commit `database/schema/*.sql`** (Laravel schema dumps). `migrate` auto-loads them
  before any migration when the `migrations` table is empty — even with `--path=` — and the dump
  starts with `DROP TABLE` statements. The folder is gitignored; if a dump reappears, delete it.
- **New tables:** guard with `Schema::hasTable()`. Columns referencing legacy tables
  (`users`, `customers`, `items`, `tags`, `item_group`, `transactions`, …) must be
  `integer()` / `unsignedInteger()` — **never `foreignId()`/BIGINT**, because legacy PKs are
  `INT(11)`. FKs between two *new* L12 tables may use `foreignId()`.
- **No FK constraints to `transactions` or `transaction_details`** — they are RANGE-partitioned
  by date and MySQL rejects FKs to partitioned tables (errno 150). Store the id + index only.
- **NOT NULL columns need defaults.** Legacy tables are full of NOT NULL columns without
  DEFAULTs; partial inserts throw MySQL 1364. Give new NOT NULL columns a `->default(...)`, and
  model-level fallbacks live in `App\Support\ProductionColumnDefaults`.
- **MySQL index / FK names max 64 characters** (errno 1059). Laravel's auto-generated names for
  long table + column lists exceed this — always pass a short explicit name on composite
  `->index(...)`, `->unique(...)`, and `->foreign(...)` (e.g. `'tax_faktur_period_entity_idx'`).
- A one-off greenfield `create_*` migration that later changes shape must be turned into a
  no-op and replaced by a guarded `install_*` migration (see the standalone-invoice pair
  `2026_08_19_040000` / `2026_08_19_070000`) — prod may have run the old version already.
- Fresh prod bootstrap = `2026_08_13_100000_production_database_bootstrap` (+ seeder). Add new
  L12 tables to the bootstrap's `up()` list as well as shipping the standalone migration.
- **Legacy table drops** (Phase 1 cleanup) are **maintainer manual only** — see **Roadmap & open work**
  and `database/legacy-table-audit.md`. Agents must not add `DROP TABLE` migrations for audit candidates.

## AI agent restrictions (MUST follow)

These override generic Laravel / accounting assumptions. **Do not "fix" or refactor these unless the
user explicitly asks.**

### No demo videos or GUI walkthroughs

- **Do NOT record demo videos, screen recordings, or walkthrough artifacts** (including
  `RecordScreen`, `computerUse` demos, and browser-based "prove it works" recordings).
- The maintainer tests manually. Implement, run `./vendor/bin/pest` / `curl` / tinker as needed, **commit**,
  and open a PR. Avoid burning tokens on GUI demos.

### Do NOT use `transactions.real_total`

Application code must ignore `transactions.real_total`. **`total` is the only header amount.**

- **`transactions.total`** is the signed **final payable** after invoice discount and adjustment,
  plus **stored** PPN only when tax was recorded. Balances, display, reports, and new writes all
  use this column. Do **not** assume every row includes 11% PPN.
- Line subtotals come from **`transaction_details.total`**, not from a second header column.
- `transactions.discount` is an invoice-discount **percent** (production `decimal(5,2)`), not money.
- The leftover MySQL column on partitioned `transactions` / `deleted` may stay (NOT NULL, no
  useful default on some prod rows). `ProductionColumnDefaults` (and raw upserts) may set
  `real_total => 0` on MySQL create so inserts do not hit errno 1364. That is a dummy fill — do
  **not** read, write a second semantic amount, display, or fall back to it in PHP, Blade, SQL
  aggregates, or tests.
- Do **not** ship `DROP COLUMN real_total` on partitioned `transactions` / `deleted`.
- Do **not** reintroduce `real_total` as a second Aria amount. Jubelio's HTTP payload field named
  `real_total` is **their** API, not our DB column — keep that mapping as API data only.
- Faktur-posted sells store **DPP** on `total` (tax linking sums that as DPP). Reconstruct gross as
  `abs(total) + ppn` — do not bring back a second header column for it.

### Do NOT change signed transaction totals

`transactions.total` is a **signed** integer/decimal by design — not an unsigned amount with sign
inferred elsewhere.

**Sign convention (do not flip):** positive = sender owes receiver; negative = receiver owes sender.

- **Negative:** sell, return-supplier, cash out, transfer, move.
- **Positive:** buy, return, cash in, adjustment.

Authoritative helper: `Transaction::signedAmount($type, $amount)` in `app/Models/Transaction.php`.

- **When writing new transactions**, store `total` through `Transaction::signedAmount()` (see
  `CreateItemTransaction`, `CreateCashTransaction`, `CreateTransferTransaction`). Do **not** store
  `abs($grandTotal)` and apply sign later.
- **When reading totals for balances**, use `TransactionService::balanceAmount()` — it reads
  **`total`** and normalizes legacy sign via `signedAmount(abs($stored))`. A stored `total` of `0`
  is a real zero (e.g. 100% invoice discount). Do **not** replace this with bare `abs()`, `*-1`
  flips, debit/credit logic, or "always store positive" refactors.
- **Display-only** formatting may use `abs()` for human-readable currency; **do not** change what is
  persisted based on display needs. On-screen payable uses `total`; on-screen line subtotal uses
  detail rows.
- **Do not edit** `Transaction::signedAmount()`, `Transaction::typeIsNegative()`, or tests such as
  `tests/Unit/TransactionSignedAmountTest.php` and `tests/Feature/TransactionBalanceIntegrityTest.php`
  unless the task **explicitly** requests a signed-total convention change.
- **Balance bugs:** fix running-balance recalculation, observer/job ordering, or back-dated row
  updates — **not** the sign convention. Delete reverts whatever was posted from `total`.

### Do NOT reintroduce React / Vite / a JS build

- The React/Inertia SPA is **gone**. Frontend is **Blade + Alpine.js + Tailwind (CDN)** only.
- **Do NOT add** `package.json`, `vite.config.ts`, `resources/js`, Inertia, React, Vue, Livewire as a
  SPA replacement, or CI steps that build/lint frontend bundles.
- **Do NOT** convert server-rendered pages back to client-side routing or a component framework.

### Do NOT drop or alter production tables (narrow exceptions)

Production shares the live L10 MySQL schema (`database/old.sql`). Unless the user **explicitly**
asks for a specific schema change:

- **NEVER** `Schema::drop`, `Schema::dropIfExists`, `DROP TABLE`, drop-and-recreate, or
  `migrate:fresh` / `migrate:refresh` on anything but local SQLite.
- **On legacy/production tables**, the only allowed ALTERs are **adding new columns** (guarded with
  `Schema::hasColumn()`) and **changing a column's default value**. Do **not** rename columns, change
  column types, drop columns, or reshape tables.
- **New L12 tables** are fine — guard with `Schema::hasTable()`. Full production-migration rules
  (integer FKs, no FK to partitioned `transactions`, index name length, etc.) live in **Production
  database safety** below.

### Do NOT remove `items.legacy_code`

`items.legacy_code` is a live production column. It stores the pre-conversion SKU so Jubelio
order matching still finds the item after `items.code` is rewritten.

- **Never drop, rename, or null out the column** — not in a migration `down()`, a "cleanup"
  after mass convert, or a refactor that treats conversion as finished.
- **Never wipe row values** (`UPDATE items SET legacy_code = NULL`, empty-string backfills,
  or "legacy_code is redundant now" edits).
- **When `code` changes**, preserve the old SKU in `legacy_code` if it is still empty
  (`ItemService` / `LegacyItemConverterService::preserveLegacyCode()`). If `legacy_code` is
  already set, do not overwrite it.
- Keep it on `Item` `$fillable` / forms / Jubelio lookups. Display-only UI may hide it;
  persistence must keep it.
- The **legacy identity converter UI** (`LegacyItemConverterService`, convert-identity routes)
  is a **temporary migration tool**. **`items.legacy_code` is permanent** — do not remove the
  column or row values when conversion work finishes. See **Item group & item identity** below.

### Do NOT regress item_group / item identity

Settled rules live in **Item group & item identity** below. In particular:

- **Do NOT add `item_group.legacy_name`** — it was never shipped; product title is
  `item_group.name` only.
- **Do NOT re-add a UNIQUE index on `item_group.name`** — multiple colorways may share the
  same bare title; identity is `(master, variant)`, not name.
- **Do NOT store color in `item_group.name`** — warna lives on `item_group.variant` (assets)
  or the pcode suffix (manufactured); SKU display names append color/size via `buildName()`.
- **Do NOT use manufactured master-only shapes for new writes** — canonical manufactured
  `master` is the full colorway pcode (`CX90233-23`). Legacy parent-only masters
  (`CX00122`, `CX00122/03`) are read/merge paths only (see PR #576).

### Do NOT change Alpine.js patterns

Alpine in this codebase has known gotchas. **Match existing conventions** in the file you edit — do
not "modernize" or refactor Alpine style:

- Use **methods** (e.g. `canSubmit()`) for `:disabled` / `:class` bindings — **not getters** (they
  have been unreliable here).
- Inside nested `x-data` (e.g. `asyncCombobox`), reference the parent with **bare, scope-inherited
  names** — never `$root.*`.
- Inside a double-quoted `x-data="..."` attribute, build endpoints with **`@js(...)`** — never
  `@json(...)`.

## Testing & workflow preferences (IMPORTANT)

- See **AI agent restrictions** above — no demo videos or GUI walkthroughs.
- Lightweight functional checks are still fine and encouraged before committing: run `./vendor/bin/pest`,
  and use `curl`/`php artisan tinker` to sanity‑check routes, queries, and JSON endpoints.
- Commit each logical change separately with a clear message; push to the working branch.

## Efficient debugging notes

- When probing the DOM, **target elements precisely** (by visible label, a scoped selector, or an
  `id`/`data-testid`) and confirm identity before trusting a reading. Gotcha: the sidebar contains a
  **"Log Out" `<button type="submit">`**, which is the *first* submit button on every page — a bare
  `querySelector('button[type=submit]')` will match it, not a form's Save button.
- Prefer adding stable `id` / `data-testid` hooks to key controls when editing a form.
- Alpine conventions are **AI agent restrictions** — see that section; do not refactor to getters,
  `$root`, or `@json(...)` in `x-data`.

## Environment / running

- Tooling in the VM image: **PHP 8.3** (+ sqlite3, mbstring, xml, curl, zip, gd, bcmath, intl, mysql),
  **Composer 2**, **Node 22**. The startup update script only refreshes deps (`composer install`).
- Config in `.env` (from `.env.example`), DB is SQLite at `database/database.sqlite` (both gitignored,
  persist in the VM snapshot). Migrate + seed: `php artisan migrate`, then `SuperAdminSeeder`,
  `SettingSeeder`, `DemoDataSeeder`. On a fresh dev DB, generate permissions once:
  `php artisan tinker --execute="app(\App\Services\PermissionGenerator::class)->generateAll(); \Spatie\Permission\Models\Role::findByName('superadmin', 'web')?->syncPermissions(\Spatie\Permission\Models\Permission::all());"`.
- Serve: `php artisan serve --host=0.0.0.0 --port=5000`. Run a queue worker
  (`php artisan queue:listen`) so `UpdateTransactionSummaries` jobs process.
- Preview login: `superadmin` / `password`. **Login is by username, not email** (`config/fortify.php`).

## Domain rules that affect code

- **User id 1 is the only superadmin** — it bypasses all authorization (`Gate::before` +
  `User::getIsSuperadminAttribute()`) and all ACL/location/hidden-balance restrictions. Every other user
  is subject to ACL.
- Balances use **signed values**, not debit/credit. Parties are sender/receiver; a positive value/balance
  means the sender owes the receiver, negative means the receiver owes the sender.
  **`total`** is the only header amount (final payable). Do **not** use `real_total`.
  Signs: sell / return-supplier / cash out / transfer / move → negative; buy / return / cash in /
  adjustment → positive. The double-entry is handled in the background.
  **Agents: do not reintroduce `real_total` or flip these signs** — see **AI agent restrictions** above.
- Transaction types (`App\Enums\TransactionType`): Buy=1, Sell=2, Move=3, Transfer=6, CashOut=7, Use=8,
  CashIn=9, Adjust=12, Return=15, Production=16, ReturnSupplier=17, Depreciation=18. Legal sender/receiver
  types per transaction live in `config/transaction_rules.php`.
- Addrbook `type` is polymorphic: 1 customer, 2 warehouse, 3 bank, 4 supplier, 5 v_warehouse,
  6 v_account, 7 reseller, 8 account, 99 other.
- **PPN is not always calculated.** It depends on the **reporting entity**, not a global 11% on every
  invoice. Do not infer tax from `ppn_rate` when reconstructing or "fixing" a payable.
  - Cash in/out: `record_ppn` is allowed only when the bank belongs to an active **PKP**
    reporting entity (`ReportingEntity::is_pkp` via `reporting_entity_banks`). Non-PKP entities
    do not take PPN keluaran; cash-in may get PPh final instead.
  - Tax reports attribute **stored** `transactions.ppn` to an entity (sell via cash-in bank,
    buy via cash-out bank). `ppn = 0` is a real zero — the row is not taxable.
  - Item buy/sell write path still uses the counterparty `addrbook.ppn` flag to decide whether
    to add tax to `total`. Do not change that unless asked. Reconstruct from the stored `ppn`
    column; never add rate × subtotal because a contact or entity "should" be PKP.
- Connects to **Jubelio** (Indonesian omnichannel) for online stock; dormant while `JUBELIO_ACTIVE=false`.
  See **Jubelio stock sync** below — do not guess what `a_submit_by` / `b_submit_by` mean.

## Item group & item identity (do NOT regress)

Canonical implementation: `App\Services\Items\ItemIdentityBuilder`, `App\Services\ItemService`.
Tests: `ItemIdentityBuilderTest`, `ItemServiceTest`, `ItemGroupHierarchyTest`, `ColorwayEditTest`,
`LegacyItemConverterTest`.

**Prerequisites (merged):** PR **#575** (legacy converter writes catalog to `item_group` first;
leftover `items.*` mirrored from group) and PR **#576** (reuse leftover slash-pcode groups when
saving hyphen pcodes so SKUs stay on the parent page). Do not undo those behaviors.

**Deferred:** normalizing legacy **three-segment asset pcodes** (e.g. `BAG-16-03`) down to two
segments — keep them as stored; only rewrite the first segment from TYPE on **new** pcodes.

### `item_group` schema & keys

Production table is `item_group` (not `item_groups`). Legacy snapshot: `database/old.sql`
(`name` was `varchar(50) UNIQUE`); widened by `2026_09_03_150000_widen_item_group_name_drop_unique`:

| Column | Rule |
|--------|------|
| `name` | `varchar(255) NOT NULL`. **Not UNIQUE.** Bare product title, or pcode when no title. **No `legacy_name` column** — never add one. |
| `master` | Colorway identity (see per-type shapes below). |
| `variant` | Color segment: pcode suffix (manufactured) or warna tag code (asset lancar). |
| `description`, `description2`, `url`, `brand`, `genre` | Shared **catalog** for every size in the colorway (`ItemCatalog::applyToGroup`). |

Colorway row key is **`(master, variant)`**, not `name`. Multiple groups may share the same
`name` (e.g. two manufactured colorways both titled `RUNNING SHIRT`).

### Master / variant per item type

| Type | `master` | `variant` | Example |
|------|----------|-----------|---------|
| Manufactured (`ItemType::ITEM`) | Full colorway **pcode** | Color number from pcode suffix | `master=CX90233-23`, `variant=23` |
| Asset lancar (`ItemType::ASSET_LANCAR`) | **TYPE-CODE** pcode | Warna tag `code` | `master=GLOVE-01`, `variant=BLUE` |

Pcode patterns (`ItemIdentityBuilder`): manufactured `[A-Z]{2,3}[0-9]{5}-[0-9]{2,3}` (slashes
normalized to hyphens); asset `[segment]-[segment]` or three segments for legacy rows
(`GLOVE-01`, `BAG-16-03`).

Legacy manufactured groups may still have `master=CX00122` or `CX00122/03` with empty
`variant` — `findCanonicalGroup()` / `reuseLeftoverColorwayGroup()` merge those onto the
canonical hyphen pcode on save; **new writes** should store the full colorway on `master`.

### Product name (`item_group.name`)

- **Blank product name → `name` = pcode** (manufactured: normalized hyphen pcode; asset: uppercase pcode).
- **Filled product name → `name` = bare uppercase title** — color is **not** appended to `name`.
- **Edit must update `group.name`** when the user changes product name (`ItemService::resolveGroup`,
  `updateColorway`, `renameGroupProductName`). Empty / pcode-like input on update means
  `name` tracks pcode again.
- **`uniqueStoredGroupName()` does not suffix for uniqueness** — it only trims to 255 chars.
  Strip legacy disambiguators like ` (CX90233-23)` via `productDisplayName()` / `stripUniquenessSuffix()`.

### SKU code & display name (`items`)

- **`items.code`:** manufactured `{TYPE}-{pcode}-{size?}` (e.g. `AJD-CX90324-05-S`); asset
  `{pcode}-{warna}-{size?}` (e.g. `GLOVE-01-BLACK-S`). All-size (`AS`) omits the size segment.
- **`items.name`:** `{product title} - {warna} - {size}` via `ItemIdentityBuilder::buildName()`.
  All-size omits size: `ELBOW STRAP - BLACKWHITE`. Regenerated for every item in the group when
  catalog/name changes (`ItemService::syncItemNamesForGroup()`).
- **`items.legacy_code`:** see **Do NOT remove `items.legacy_code`** — snapshot old `code` on
  first identity change; converter tooling is temporary, the column is not.

### Colorway-only edit page & per-item price

Route: `items-group/colorway/{group}/edit` (`ItemsController@colorwayEdit` /
`colorwayUpdate`). View: `resources/views/items/colorway-edit.blade.php`.

- **Read-only on this page:** pcode, tags, SKU `code` (identity block).
- **Editable per colorway (stored on `item_group`):** product name → `name`, description,
  description2, url, brand, genre, image.
- **Editable per size (stored on each `items` row):** price, cost, restock urgent threshold.
  Create forms set a default price; matrix edits happen here.

Single-SKU create/edit forms mark shared fields with “Shared across this colorway”; price on
create is a default for new sizes only.

### Asset create UX: TYPE → pcode autofill & preview layout

On asset lancar **create** (`resources/views/items/create.blade.php`):

- Layout: basic + details (left), **attributes/tags (right)**, then **Item Summary Preview
  below the grid** (`form-preview` after the 3-column section — preview sits under tags, not beside pcode).
- Selecting **Type** rewrites a **new** pcode’s first segment to the TYPE tag code
  (`applyAssetTypePrefixToPcode`: `gloves-03` + `GLOVE` → `GLOVE-03`). Skipped when the pcode
  already exists on another asset row. **Three-segment pcodes are preserved** (`bag-16-03` →
  `BAG-16-03`, not `BAG-03`).
- Pcode blur may AJAX-load an existing product title (`items.pcode-name` → `productNameForPcode`).

### Key files

- `app/Services/Items/ItemIdentityBuilder.php` — pcode validation, group master/variant, code/name builders.
- `app/Services/ItemService.php` — create/update/colorway, group resolution, legacy_code preserve.
- `app/Services/Items/LegacyItemConverterService.php` — one-off SKU migration (group-first catalog).
- `resources/views/items/partials/form-{basic,attributes,preview,scripts}.blade.php` — create/edit UX.
- `database/migrations/2026_09_03_150000_widen_item_group_name_drop_unique.php` — name width + drop UNIQUE.

## Reporting (L12)

**Status:** Core reporting is **implemented and merged**. The maintainer is **still validating**
figures against production expectations — **expect follow-up tasks** to adjust mappings, filters,
labels, or formulas. Do **not** assume the current output is final; do **not** refactor reporting
code unprompted.

### Architecture (read before changing reports)

- **Reporting entities** (`ReportingEntity`, `/reports/entities`) — PKP flag, bank mapping
  (`reporting_entity_banks`), tax accounts, ledger roles, warehouse fulfillment overrides.
  Cash-in PPN / PPh final gating uses `ReportingEntity::is_pkp` via entity banks (see **Domain rules**).
- **Summary layer** — incremental + rebuild paths write to `reporting_*_monthly_summaries`,
  `reporting_monthly_tax_summaries`, `reporting_monthly_inventory_values`, balance snapshots.
  Cutover: `config('reporting.cutover_date')` (default `2025-01-01`). Live path:
  `ReportingSummaryRecorder` (from `UpdateTransactionSummaries` / transaction observers).
  Batch rebuild: `php artisan reporting:rebuild-summaries`, `reporting:rebuild-inventory`,
  `reporting:snapshot-balances`.
- **Stored tax amounts** — reports use **`transactions.ppn`** and **`transactions.total`** as persisted;
  do not infer 11% from contact PKP flags when reconstructing (see **Do NOT use `real_total`** and
  **PPN is not always calculated** in Domain rules).
- **Permissions** — `report-*` Spatie names; sidebar in `resources/views/partials/sidebar-nav.blade.php`.
  Obsolete L10 names cleaned via `ObsoleteReportPermissions` / `app:remove-obsolete-report-permissions`.

### Shipped report pages (`/reports/*`)

| Area | Route / permission | Notes |
|------|-------------------|--------|
| Warehouse | `warehouse-item`, `warehouse-arrangement`, `product-performance`, `inventory-health` | Arrangement uses `warehouse_item_monthly_stats` + refresh jobs |
| Finance | `nett-cash-sby`, `neraca`, `laba-rugi`, `channel-pnl`, `receivables`, `payables`, `asset-tetap` | Neraca/laba rugi use summary + snapshot tables |
| Tax | `tax/ppn`, `tax/pph`, `tax/faktur/*` | Faktur import → review → link sells / post sell; DPP on `total` |
| Produksi | `produksi-potong`, `produksi-jahit`, `produksi-qc`, `produksi-pritil` | Date/status filters on worker totals |
| Admin | `entities/*` | Entity + ledger role + fulfillment setup |
| Export | `report-export-sell` → `/transactions/export-sell` | Replaces removed item-sales / purchase reports |

Removed / do not restore: legacy cash-flow, expense, purchase report pages (permissions in
`ObsoleteReportPermissions`).

### Key files

- `app/Http/Controllers/Reports/*`, `resources/views/reports/*`
- `app/Services/Reporting/*` — `TaxReportService`, `PphFinalReportService`, `AgingReportService`,
  `ReportingSummaryRecorder`, `BalanceAsOfService`, `InventoryRollForwardService`, `ReportingExcelExport`
- `config/reporting.php` — cutover, PPh rate, supplier umum needle, channel matchers
- Tests: `tests/Feature/*Report*`, `tests/Feature/TaxFaktur*`, `tests/Feature/ChannelPnl*`

### Agent workflow for reporting tasks

1. **Wait for maintainer brief** — which report, which period, expected vs actual number.
2. Trace **summary recorder → report query → Blade**; check cutover date and entity/bank mapping.
3. Prefer **minimal diffs**; add/adjust Pest coverage for the changed formula or filter.
4. **Do not** change signed `transactions.total` convention or reintroduce `real_total` to fix reports.
5. After changes, note what the maintainer should re-verify on production data.

## Jubelio stock sync (read before touching a_*/b_* columns)

Canonical map: `App\Services\Jubelio\JubelioStockSync` and the docblock on `App\Models\Transaction`.
**Do not rename** `a_submit_by`, `b_submit_by`, `a_reference_id`, `b_reference_id`,
`submit_a_count`, `submit_b_count` — they are live L10 production columns.

Two integrations:

1. **Inbound (Jubelio → Aria).** Webhooks / cron create SELL or RETURN with `submit_type = 2`.
   Already in Jubelio. Never push those back.
2. **Outbound (Aria → Jubelio).** Manual txn (`submit_type = 1`) + **Push to Jubelio**.
   `AdjustStock` POSTs `https://api2.jubelio.com/inventory/adjustments/warehouse`.
   Success = a positive Jubelio **`item_adj_id`**, not a bare HTTP 200.

Column meanings (A/B is sender/receiver, not debit/credit):

- **Side A / `a_*` = sender warehouse** (deduct). Sell, return-supplier, move-from.
- **Side B / `b_*` = receiver warehouse** (add). Buy, return, move-to.
- `*_submit_by` = users.id who successfully pushed that side (or confirmed with a real adj id).
- `*_reference_id` = Jubelio `item_adj_id`.
- `submit_*_count` > 0 and `*_submit_by` null = **warning** (POST sent, result unclear).
  Confirm only with a real Jubelio adj number; otherwise clear and retry.

A **move** is two independent adjustments, not a Jubelio transfer. Mapping lives in
`jubeliosyncs` (Aria `warehouse_id` → `jubelio_location_id`); items need `jubelio_item_id`.

HTTP 200 with `{message: "..."}` or a listing `{data, totalCount}` means **nothing was created**.
The Aug 2026 move incident: Aria showed "status tidak jelas" and allowed confirm-as-success
without an adj id. We **did not persist Jubelio's response body**, so the exact API reject
reason is unknown. Next failure: read `laravel.log` lines `Jubelio stock adjustment`.

## Warehouse item monthly stats (three-layer updates)

`warehouse_item_monthly_stats` powers the warehouse arrangement report, product performance, and
item stats. Rows are keyed by `(warehouse_id, item_id, year, month)` with sell/return qty/value
plus item dimensions (group, brand, tags, size, etc.).

**Do not rebuild the full history in one run on production** — the old unbounded cron OOM'd at
512 MB. All batch rebuild paths go through `WarehouseItemStatsRebuilder`, which aggregates **one
month at a time directly from `transaction_details`** (sell/return types, filtered by date) rather
than walking the `items` table.

Three layers keep stats current:

1. **Live incremental (every transaction).** `UpdateTransactionSummaries` (queued job) calls
   `WarehouseItemStatsRecorder::recordDetail()` for each sell/return line on completed
   transactions. This is the "last minute" path — stats update as soon as the queue drains.
2. **Daily reconcile (recent months).** Cron: `app:recalculate-warehouse-item-stats --months=2`
   rebuilds the current and previous month from scratch, correcting any drift from the live path.
3. **Historical backfill (archive, resumable).** Cron: `app:backfill-warehouse-item-stats --months=3`
   processes older months in batches (newest first). It is **idle until started** from **System
   Settings → Warehouse Stats Backfill** (`/warehouse-stat-backfill`). Progress is stored in
   `warehouse_stat_backfills`; pause/resume is supported. The hourly cron is just a worker — the
   page (or `php artisan app:backfill-warehouse-item-stats --restart`) kicks it off.

Manual / on-demand tools:

- **Warehouse Arrangement** report has a "Rebuild stats & refresh" button (runs recalculate +
  `app:sync-warehouse-arrangement` for the selected destination).
- **CLI:** `app:recalculate-warehouse-item-stats --months=N` or `--since=Y-m-d` for bounded
  rebuilds; `app:backfill-warehouse-item-stats --status` to inspect backfill state.
- **Arrangement cache:** `app:sync-warehouse-arrangement` (daily cron) reads the stats table and
  pre-computes arrangement candidates — run after stats are populated.

Legacy-data gotchas (already handled — don't regress):

- Load items through `ItemDimensionResolver::findItem()` (not `Item::find()`) when resolving
  dimensions for stats — legacy `items.type` values (e.g. `4`) are not valid `ItemType` enum cases.
- Ignore `transaction_details.date = '0000-00-00'` and `warehouse_id = 0` when aggregating.

Key files: `app/Services/{WarehouseItemStatsRebuilder,WarehouseItemStatsRecorder,
WarehouseStatBackfillService,WarehouseArrangementSyncService}.php`,
`app/Jobs/UpdateTransactionSummaries.php`, `app/Console/Commands/{RecalculateWarehouseItemStats,
BackfillWarehouseItemStats}.php`, `database/seeders/ScheduledTaskSeeder.php`.

## Testing / known caveats

- `pest`: `tests/Feature/BladePagesRenderTest.php` is the fast Blade smoke test — run it after touching
  a Blade view.
- Some reports/stats use MySQL `DATE_FORMAT`, which errors on the SQLite **dev** DB only (works on
  production MySQL) — e.g. `items/{id}/stats`.
- Tabulator.js is intended only for the restock page; other list tables are plain server-rendered HTML.

## Starting the next task (new chat + new branch)

To keep chats short and reviews clean, do **one task (or a small related cluster) per chat**, on its
own branch, with its own PR.

- **Start a fresh chat for each new task.** Each cloud agent runs on a clean VM and re-reads this file,
  so you don't need to re-explain the project — just give the task brief.
- **Branch naming:** `cursor/<short-kebab-name>-4b37` (lowercase; the `cursor/` prefix and environment
  suffix are required).
- **Base each new branch off `main` _after_ the previous PR merges.** Do not stack new work on an
  already-merged branch (it causes messy rebases). If task B truly depends on unmerged task A, say so
  explicitly and I'll branch B off A.
- **One PR per branch.** I'll commit each logical change separately, run `./vendor/bin/pest` (and the
  `BladePagesRenderTest` smoke test after touching Blade), and open a draft PR. No demo videos (see
  **AI agent restrictions**).
- **Kickoff prompt template** to paste into the new chat:
  ```
  Goal: <what to build / fix>
  Where: <page / route / file, if known>
  Acceptance: <how we'll know it's done>
  Notes: <constraints, edge cases, anything unusual>
  ```

## Roadmap & open work

**Done (do not reopen unless fixing a regression):** transaction backend, expand items / `ItemCatalog`,
item group identity (PRs #575–#587), **L12 reporting stack** (see **Reporting (L12)** — pages +
summary tables shipped; **maintainer may still request modifications**).

**Next major cleanup (two phases):** legacy **tables** first (audit + maintainer manual drop), then
legacy **code and permissions**. See `database/legacy-table-audit.md` for the living drop candidate list.

**Ongoing maintainer review:** **Reporting** — numbers and entity mappings need production confirmation;
expect targeted fix/refinement chats from the maintainer. Do not treat reporting as frozen or schedule
large unprompted refactors.

### Phase 1 — Legacy table audit & manual drop

**Goal:** Produce a maintainer-approved list of MySQL tables safe to drop. **No `DROP TABLE` in agent
migrations or scripts** — the maintainer drops manually on production after sign-off.

**Steps (each chat may cover one step):**

1. **Pull table structure** — refresh `database/old.sql` from production (or export table list + `SHOW CREATE TABLE`).
2. **Map models** — every `app/Models/**` `protected $table` → physical name (e.g. `customers` → `Addrbook`).
3. **Ripgrep L12 usage** — for each `old.sql` table, search `app/`, `routes/`, `resources/views/`,
   `database/migrations/`, `tests/` for model usage, `DB::table()`, raw SQL, `Schema::` references.
   Exclude `database/old.sql` from hits.
4. **Build the list** — update `database/legacy-table-audit.md`:
   - **Strong candidates:** no model + no L12 code ref (first pass ~43 tables — Desty, ideas, promos, etc.).
   - **Manual review:** referenced in L12 but no Eloquent model (e.g. `acl`, `roles`, `produksi` legacy names).
   - **Active / do not drop:** has model, partitioned txn tables, Spatie tables in use (`aria_permissions`, …).
5. **L10 check** — shared MySQL: confirm the legacy L10 app no longer reads a table before moving it to
   **Approved to drop**.
6. **Maintainer drops manually** — no automated drop script in the repo.

Branch hint: `cursor/legacy-table-audit-4b37` (refresh audit) or per-table verification chats.

### Phase 2 — Legacy code & permissions removal

**Goal:** Remove L12 code paths that only existed for L10 migration / one-off conversion, and prune
obsolete permissions — **after** Phase 1 tables are dropped or confirmed unused.

**Likely removals (maintainer confirms conversion complete first):**

| Area | Examples | Keep |
|------|----------|------|
| Item identity converter | `LegacyItemConverterService`, `LegacyItemIdentityParser`, `/items/legacy-converter`, `convert-identity` routes, `items-convert-legacy` permission | `items.legacy_code`, `preserveLegacyCode()`, Jubelio/barcode legacy lookups |
| Legacy ACL import | `app:import-legacy-acl`, `LegacyAclMapper`, `database/acl/old_acl.sql` tests | Spatie `aria_permissions` / `aria_roles` (production permission store) |
| Obsolete permissions | Unused `stuff-*` / L10-mapped permissions after role audit | Permissions still referenced in `Gate::`, sidebar, controllers |

**Steps:**

1. Ripgrep each legacy module; list routes, controllers, views, tests, permissions.
2. Remove code + tests in focused PRs; run `./vendor/bin/pest`.
3. Document removed permissions; maintainer may prune rows in `aria_permissions` / role pivots manually.
4. Update this roadmap when Phase 2 is complete.

Branch hint: `cursor/remove-legacy-converter-4b37` (converter first), then `cursor/remove-legacy-acl-4b37`.

### Maintainer ops (not agent code)

- **Production migration:** run individually on live MySQL when ready:
  `php artisan migrate --path=database/migrations/2026_09_03_150000_widen_item_group_name_drop_unique.php`
  (and `2026_09_03_130000_add_brand_and_genre_to_item_group_table.php` if not applied). Until then,
  prod still has `item_group.name` varchar(50) UNIQUE.
- **Stale group names:** no backfill migration. Rows where `item_group.name` still equals pcode while
  items show a real title are fixed by editing via **colorway edit**
  (`/items-group/colorway/{group}/edit`) or single-item edit with Product Name filled.

### Other deferred / not built

| Task | Notes |
|------|-------|
| **Reporting validation / tweaks** | Maintainer-driven — see **Reporting (L12)**; expect formula, filter, mapping, or UI changes |
| **Remove legacy identity converter** | After conversion complete — branch `cursor/remove-legacy-converter-4b37`; **keep** `items.legacy_code`, `preserveLegacyCode()`, Jubelio/barcode legacy lookups |
| 3-segment asset pcode normalization | Optional/deferred — `cursor/asset-pcode-two-segment-4b37`; see **Item group & item identity** |
| Restock sheet Tabulator UI | Not built; other lists stay server-rendered HTML |

### Reference: transaction write path (for regressions only)

`Store*Request` → `TransactionsController@store*` → `app/Actions/Transactions/*` →
`Transaction` + `TransactionDetail` inside a DB transaction. `TransactionObserver` +
`UpdateTransactionSummaries` (queue) keep balances and aggregates in sync. Shared logic:
`TransactionService`, `BookClosingService`, `InventoryService`. Tests:
`tests/Feature/TransactionBalanceIntegrityTest.php`, `tests/Feature/*Transaction*`.

### Reference: items schema (for new columns)

Read **Item group & item identity** first — shared catalog fields belong on `item_group`, not new
duplicate columns on `items` unless mirroring leftovers. Per-warehouse stock is `warehouse_items`
(quantity + note), not `items.qty`. Some reports use MySQL `DATE_FORMAT` (SQLite dev errors only).

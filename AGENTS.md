# AGENTS.md

Aria Core — a Laravel 12 inventory / accounting / transaction ERP, server-rendered with
**Blade + Alpine.js** (Tailwind + Alpine load from CDN; the old React/Inertia SPA has been
removed). Indonesian domain terms throughout.

## Project state — already done (do NOT redo or worry about)

The migration from the React/Inertia SPA to Blade+Alpine and a batch of UI/bug fixes are
**complete and merged**. A new chat should build on this, not revisit it:

- **React / Inertia / Vite / TypeScript stack is fully removed.** There is no `package.json`,
  `vite.config.ts`, `resources/js`, or Node build; the frontend is pure Blade + Alpine + Tailwind
  (CDN). CI (`.github/workflows/*`) no longer builds/lints JS. **Do not reintroduce a SPA or a JS
  build step.**
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
  return-supplier): barcode/autocomplete lookup, discount in %, PPN 11%, AJAX submit that keeps
  inputs + highlights invalid rows on validation error, and a submit button gated by client-side
  validation (see `transactions/create.blade.php`, `transactions/cash.blade.php`).
- **Palette normalized to `gray-*`** (journals/produksi were `zinc-*`); page-load slide-in animation
  removed.

Already-fixed gotchas — don't reintroduce them:
- Read query params with `request()->query('x')`, **not** `request('x')`, on routes that also have a
  route segment named `x` (e.g. `{type}`) — otherwise the segment leaks into the query.
- Never call `->wherePivot(...)` inside a `when()` closure that receives the **base** builder; use the
  qualified column instead (e.g. `where('warehouse_items.quantity', '>', 0)`).
- PHP **8.5 is not supported** by `phpoffice/phpspreadsheet`; the CI matrix is `8.3`/`8.4`.

## Production database safety (MUST follow in every PR)

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

## AI agent restrictions (MUST follow)

These override generic Laravel / accounting assumptions. **Do not "fix" or refactor these unless the
user explicitly asks.**

### No demo videos or GUI walkthroughs

- **Do NOT record demo videos, screen recordings, or walkthrough artifacts** (including
  `RecordScreen`, `computerUse` demos, and browser-based "prove it works" recordings).
- The maintainer tests manually. Implement, run `./vendor/bin/pest` / `curl` / tinker as needed, **commit**,
  and open a PR. Avoid burning tokens on GUI demos.

### Do NOT change signed transaction totals

`transactions.total` and `transactions.real_total` are **signed integers/decimals by design** — not
unsigned amounts with sign inferred elsewhere.

- **Sign convention (do not flip):** positive = sender owes receiver; negative = receiver owes sender.
  **Buy / Return / CashIn → positive.** **Sell / ReturnSupplier / CashOut / Transfer → negative.**
  Authoritative helper: `Transaction::signedAmount($type, $amount)` in `app/Models/Transaction.php`.
- **When writing new transactions**, store totals through `Transaction::signedAmount()` (see
  `CreateItemTransaction`, `CreateCashTransaction`, `CreateTransferTransaction`). Do **not** store
  `abs($grandTotal)` and apply sign later.
- **When reading totals for balances**, use `TransactionService::balanceAmount()` — it already
  normalizes legacy rows via `signedAmount(abs($stored))`. Do **not** replace this with bare
  `abs()`, `*-1` flips, debit/credit logic, or "always store positive" refactors.
- **Display-only** formatting may use `abs()` for human-readable currency; **do not** change what is
  persisted based on display needs.
- **Do not edit** `Transaction::signedAmount()`, `Transaction::typeIsNegative()`, or tests such as
  `tests/Unit/TransactionSignedAmountTest.php` and `tests/Feature/TransactionBalanceIntegrityTest.php`
  unless the task **explicitly** requests a signed-total convention change.
- **Balance bugs:** fix running-balance recalculation, observer/job ordering, or back-dated row
  updates — **not** the sign convention. If a row looks "wrong", check whether you are comparing
  against legacy unsigned data before rewriting the signing rules.

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
- Alpine: use **methods** (e.g. `canSubmit()`) rather than getters for `:disabled`/`:class` bindings —
  getters have been unreliable here. Inside a nested `x-data` (e.g. the `asyncCombobox`), reference the
  parent component with **bare, scope‑inherited names**, never `$root.*`. Inside a double‑quoted
  `x-data="..."` attribute, build endpoints with **`@js(...)`**, never `@json(...)`.

## Environment / running

- Tooling in the VM image: **PHP 8.3** (+ sqlite3, mbstring, xml, curl, zip, gd, bcmath, intl, mysql),
  **Composer 2**, **Node 22**. The startup update script only refreshes deps (`composer install`).
- Config in `.env` (from `.env.example`), DB is SQLite at `database/database.sqlite` (both gitignored,
  persist in the VM snapshot). Migrate + seed: `php artisan migrate`, then `SuperAdminSeeder`,
  `SettingSeeder`, `DemoDataSeeder`. Grant the superadmin role its permissions once via
  `PermissionGenerator::generateAll()` + `syncPermissions(...)` (see `replit.md`).
- Serve: `php artisan serve --host=0.0.0.0 --port=5000`. Run a queue worker
  (`php artisan queue:listen`) so `UpdateTransactionSummaries` jobs process.
- Preview login: `superadmin` / `password`. **Login is by username, not email** (`config/fortify.php`).

## Domain rules that affect code

- **User id 1 is the only superadmin** — it bypasses all authorization (`Gate::before` +
  `User::getIsSuperadminAttribute()`) and all ACL/location/hidden-balance restrictions. Every other user
  is subject to ACL.
- Balances use **signed values**, not debit/credit. Parties are sender/receiver; a positive value/balance
  means the sender owes the receiver, negative means the receiver owes the sender. buy/return → total
  positive; sell/return-supplier → total negative. The double-entry is handled in the background.
  **Agents: do not change this convention** — see **AI agent restrictions** above.
- Transaction types (`App\Enums\TransactionType`): Buy=1, Sell=2, Move=3, Transfer=6, CashOut=7, Use=8,
  CashIn=9, Adjust=12, Return=15, Production=16, ReturnSupplier=17, Depreciation=18. Legal sender/receiver
  types per transaction live in `config/transaction_rules.php`.
- Addrbook `type` is polymorphic: 1 customer, 2 warehouse, 3 bank, 4 supplier, 5 v_warehouse,
  6 v_account, 7 reseller, 8 account, 99 other.
- Connects to **Jubelio** (Indonesian omnichannel) for online stock; dormant while `JUBELIO_ACTIVE=false`.

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
- **Branch naming:** `cursor/<short-kebab-name>-e924` (lowercase; the `cursor/` prefix and `-e924`
  suffix are required by this environment).
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

## Roadmap: the next two branches

### 1. Transaction backend (`cursor/transaction-backend-e924`)

How a transaction is written today (trace this flow before changing it):
`Store*Request` (validation) → `TransactionsController@store*` → an action in
`app/Actions/Transactions/` (`CreateItemTransaction`, `CreateCashTransaction`,
`CreateTransferTransaction`, `CreateAdjustTransaction`; shared bits under `Concerns/`) which writes the
`Transaction` + `TransactionDetail` rows inside a DB transaction. `app/Observers/TransactionObserver.php`
and the `app/Jobs/UpdateTransactionSummaries.php` queued job keep balances/aggregates in sync (run
`php artisan queue:listen`). Shared logic lives in `app/Services/TransactionService.php`;
`BookClosingService` enforces the book-closing cutoff date; `InventoryService` adjusts
`warehouse_items` stock. Batch recompute lives in `app/Console/Commands/Recalculate*`.
- Balances are **signed** (not debit/credit): +total = sender owes receiver; buy/return are positive,
  sell/return-supplier negative. Types are in `App\Enums\TransactionType`; legal sender/receiver types
  per type are in `config/transaction_rules.php`.
- Likely goals here: solidify signed double-entry posting; **recalculate balances when a back-dated
  transaction is inserted/edited/deleted** (later rows must re-derive their running balance); wire up
  **Jubelio stock-sync buttons** (`app/Services/JubelioService.php`,
  `TransactionsController@hydrateJubelioSyncData`, dormant while `JUBELIO_ACTIVE=false`).
- Key files: `app/Http/Controllers/TransactionsController.php`, `app/Actions/Transactions/*`,
  `app/Services/{TransactionService,BookClosingService,InventoryService,JubelioService}.php`,
  `app/Observers/TransactionObserver.php`, `app/Jobs/UpdateTransactionSummaries.php`,
  `app/Models/{Transaction,TransactionDetail,WarehouseItem}.php`. Cover changes with Pest feature tests
  (see existing `tests/Feature/*Transaction*`, `TransferTest`).

### 2. Expand the items table (`cursor/expand-items-table-e924`)

- Add columns with a **new dated migration** in `database/migrations/` (e.g.
  `add_<cols>_to_items_table`) — do **not** edit the original `create_items_table` migration.
- Then update `App\Models\Item` `$fillable` (and `$casts` if typed), surface the fields in the item
  forms (`resources/views/items/*` — create/edit) and add validation in
  `app/Http/Controllers/ItemsController.php`. Touch `app/Services/{ItemService,InventoryService}.php`
  if a new column affects stock or pricing.
- Current `items` columns: `id, group_id, name, code, pcode, brand, type, price, cost, qty, tag_ids,
  description, description2, url, image_path, size, genre, jubelio_item_id, timestamps, deleted_at`.
  Per-warehouse stock lives in `warehouse_items` (quantity + note), not on `items`.
- Caveat: some item reports/stats use MySQL `DATE_FORMAT` and error on the SQLite dev DB only.

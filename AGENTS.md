# AGENTS.md

Aria Core — a Laravel 12 inventory / accounting / transaction ERP, server-rendered with
**Blade + Alpine.js** (Tailwind + Alpine load from CDN; the old React/Inertia SPA has been
removed). Indonesian domain terms throughout.

## Testing & workflow preferences (IMPORTANT)

- **Do NOT record demo videos or screen recordings, and do NOT run computerUse "demo" walkthroughs.**
  The maintainer tests manually. Just implement and **commit** the code for review. Avoid burning
  tokens on GUI demos.
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
- Transaction types (`App\Enums\TransactionType`): Buy=1, Sell=2, Move=3, Transfer=6, CashOut=7, Use=8,
  CashIn=9, Adjust=12, Return=15, Production=16, ReturnSupplier=17, Depreciation=18. Legal sender/receiver
  types per transaction live in `config/transaction_rules.php`.
- Addrbook `type` is polymorphic: 1 customer, 2 warehouse, 3 bank, 4 supplier, 5 v_warehouse,
  6 v_account, 7 reseller, 8 account, 99 other.
- Connects to **Jubelio** (Indonesian omnichannel) for online stock; dormant while `JUBELIO_ACTIVE=false`.

## Testing / known caveats

- `pest`: `tests/Feature/BladePagesRenderTest.php` is the fast Blade smoke test — run it after touching
  a Blade view.
- Some reports/stats use MySQL `DATE_FORMAT`, which errors on the SQLite **dev** DB only (works on
  production MySQL) — e.g. `items/{id}/stats`.
- Tabulator.js is intended only for the restock page; other list tables are plain server-rendered HTML.

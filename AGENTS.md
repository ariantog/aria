# AGENTS.md

## Cursor Cloud specific instructions

Aria Core is a Laravel 12 inventory/accounting ERP. The active UI is server-rendered
**Blade + Alpine.js** (Tailwind + Alpine load from CDN); an older **Inertia + React** stack
still lives in `resources/js` but no controller renders it anymore. See `replit.md` for the
domain runbook and `docs/blade-migration-conventions.md` for Blade conventions.

### Environment / dependencies
- System tooling already provisioned in the VM image: **PHP 8.3 CLI** (with `sqlite3`,
  `mbstring`, `xml`, `curl`, `zip`, `gd`, `bcmath`, `intl`, `mysql`), **Composer 2**, and
  **Node 22 / npm**. The startup update script only refreshes project deps
  (`composer install`, `npm install`).
- App config lives in `.env` (copied from `.env.example`) with a generated `APP_KEY`, and the
  database is SQLite at `database/database.sqlite`. Both are gitignored and persist in the VM
  snapshot, so they do not need recreating each session.

### Running the app (dev)
- Serve: `php artisan serve --host=0.0.0.0 --port=5000`.
- Queue worker: `php artisan queue:listen --tries=1`. Transaction create/update dispatches an
  `UpdateTransactionSummaries` job; without a worker, summary/report figures lag until it runs.
- `composer run dev` also starts `npm run dev` (Vite), but Vite is **not** required for the Blade
  pages (they use CDN assets + prebuilt `public/build/`). See the build caveat below.

### Database / login
- Migrate + seed: `php artisan migrate` then seeders `SuperAdminSeeder`, `SettingSeeder`,
  `DemoDataSeeder`. `DemoDataSeeder` regenerates a placeholder image at `public/asset/01/1.jpg`
  (shows as a git diff — ignore/revert it).
- The `superadmin` role has **no gate bypass by itself**; grant it permissions once via
  `PermissionGenerator::generateAll()` + `syncPermissions(...)` (snippet in `replit.md`).
- Preview login: username `superadmin` / password `password`. Fortify authenticates by
  **username, not email** (`config/fortify.php`).
- **User id 1 is the one and only superadmin** and bypasses all authorization
  (`User::getIsSuperadminAttribute()` + `Gate::before` in `AppServiceProvider`). Do not rely on a
  role for full access.

### Lint / test
- Lint: `composer lint` (Pint). Note: `composer test:lint` / `pint --test` currently reports many
  **pre-existing** style violations in `tests/` and seeders — not caused by your changes.
- Tests: `./vendor/bin/pest`. Three tests fail on a clean checkout
  (`AddrbookTest`, `ReportTest` assert Inertia responses for pages already migrated to Blade) —
  known stale tests, unrelated to new work. `tests/Feature/BladePagesRenderTest.php` is the
  fast smoke test for Blade pages; run it after touching any Blade view.

### Known caveats
- `npm run build` currently **fails** on a legacy React import (`@/components/pagination` vs the
  case-sensitive file `Pagination.tsx`). This does not affect the Blade app, which is what runs.
- `/addrbook/create` throws "Undefined variable $addrbook" on a clean checkout (the controller's
  `create` doesn't pass `$addrbook` to the shared form partial) — a pre-existing bug in the
  migrated view, unrelated to autocomplete work.

### Alpine autocomplete gotchas (`asyncCombobox` in `layouts/app.blade.php`)
- Comboboxes are nested `x-data` components. To read/write the parent form component, use **bare,
  scope-inherited names** (`form.sender_id`, `errors`, `recalcTotals()`), **not** `$root.*` —
  `$root` resolves to the combobox element itself, so `$root.form` is `undefined`.
- Inside a double-quoted `x-data="..."` attribute, build endpoints with **`@js(...)`**, never
  `@json(...)`: `@json` emits a double-quoted string that prematurely closes the attribute and
  breaks the whole component.

# Aria Core

Laravel inventory, accounting and transaction management system. Originally an Inertia.js + React
SPA, currently mid-migration to server-rendered Blade + Alpine.js.

## Running the app

The **Start application** workflow runs `php artisan serve --host=0.0.0.0 --port=5000`.

### Preview login

A superadmin account is seeded for previewing the app:

| Username     | Password   |
| ------------ | ---------- |
| `superadmin` | `password` |

Note that the login form authenticates on **username**, not email (`config/fortify.php` sets
`'username' => 'username'`).

## Database

SQLite at `database/database.sqlite`. Seed a working dataset with:

```bash
php artisan migrate
php artisan db:seed --class=SuperAdminSeeder    # superadmin user + role
php artisan db:seed --class=SettingSeeder       # app settings (incl. ppn_rate)
php artisan db:seed --class=DemoDataSeeder      # contacts, items, sample transactions
```

The superadmin role needs permissions explicitly granted — it has no gate bypass:

```bash
php artisan tinker --execute="
app(App\Services\PermissionGenerator::class)->generateAll();
\$r = Spatie\Permission\Models\Role::where('name','superadmin')->first();
\$r->syncPermissions(Spatie\Permission\Models\Permission::all());
"
```

`attached_assets/*.sql` is a structure-only dump of the production MySQL schema, kept as reference.
It contains no data.

## Architecture

### Frontend: two rendering stacks side by side

The app is being migrated from React to Blade one page group at a time. Both stacks currently run:

**Blade + Alpine.js** (migrated) — dashboard and all transactions pages.
- Layout: `resources/views/layouts/app.blade.php` — sidebar, breadcrumbs, flash toasts, and the
  shared `asyncCombobox()` Alpine component used by every autocomplete field.
- Sidebar nav: `resources/views/partials/sidebar-nav.blade.php`, permission-gated.
- Sidebar data is supplied by `app/View/Composers/AppComposer.php`, bound to `layouts.app`.
- Tailwind and Alpine load from CDN in the Blade layout, so Blade pages don't require a Vite build.

**Inertia + React** (not yet migrated) — welcome page, auth, settings, address book, items, reports,
user management. These use the compiled bundle in `public/build/`.

Because the two stacks style themselves differently (CDN Tailwind vs. compiled Tailwind), keep Blade
pages self-contained rather than sharing CSS with the React side.

### Backend

Controllers changed during the migration are **presentation-layer only** — `Inertia::render(...)`
swapped for `view(...)`. Business logic (Actions, Services, Requests) is untouched and shared by both
stacks.

Transaction creation is orchestrated by `app/Actions/Transactions/*`, with the legal sender/receiver
contact types for each transaction type declared in `config/transaction_rules.php`.

## Testing

```bash
# Smoke tests run against the real seeded DB rather than the default in-memory one
DB_CONNECTION=sqlite DB_DATABASE=/home/runner/workspace/database/database.sqlite \
  ./vendor/bin/pest tests/Feature/BladePagesRenderTest.php
```

`tests/Feature/BladePagesRenderTest.php` asserts every Blade-migrated page renders for a superadmin.
Run it after touching any Blade view — it catches missing view variables and layout regressions that
manual clicking misses.

## User preferences

- Keep tables as plain server-rendered HTML. Tabulator.js was tried on the transactions index and
  rejected; it is reserved for a few specific pages later. Readability matters more than table
  features.
- Migrate away from React incrementally; the eventual goal is full Blade + Alpine.
- Do not change backend business logic when migrating a page's frontend.

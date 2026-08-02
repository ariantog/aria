# Blade + Alpine.js Migration Conventions

We are migrating this Laravel app from Inertia/React to server-rendered Blade + Alpine.js. Dashboard and transactions index/create pages are already migrated — use them as the reference implementations:

- `resources/views/layouts/app.blade.php` — shared layout (Tailwind CDN, Alpine CDN, Tabulator, sidebar, flash messages, `asyncCombobox` helper). All pages MUST `@extends('layouts.app')`.
- `resources/views/transactions/index.blade.php` — reference for a Tabulator-based list page with remote pagination/sorting and filters.
- `resources/views/transactions/create.blade.php` — reference for a complex Alpine form page.
- `resources/views/partials/sidebar-nav.blade.php` — sidebar; uses `$_sidebar` (shared via `App\View\Composers\AppComposer`, gives `user`, `permissions`, `roles`, `addrbook_types`, `flash`).

## Rules

1. **Controller changes**: replace every `Inertia::render(...)` / `inertia(...)` with `return view('...', [...])`. Keep authorization, eager-loading, and data shaping logic intact. When the React page fetched paginated data as props, prefer returning JSON for AJAX (`$request->expectsJson() || $request->ajax()`) + a Blade view that loads data via Tabulator remote ajax — exactly like `TransactionsController::index`. For simple pages, just pass the data to the view directly and render with Blade loops.
2. **View location**: kebab/lowercase directories mirroring routes, e.g. `resources/views/addrbook/index.blade.php`, `resources/views/items/show.blade.php`, `resources/views/settings/profile.blade.php`.
3. **Layout usage**:
   ```blade
   @extends('layouts.app')
   @section('title', 'Page Title')
   @section('content') ... @endsection
   ```
   Breadcrumbs: set `$breadcrumbs` via `@php` at top of the content section (array of `['title' => ..., 'href' => ...]`) — see transactions/index.
4. **Flash**: layout reads `$flash`; controllers keep using `->with('success', ...)` redirects. In views you can pass `'flash' => ['success' => session('success'), 'error' => session('error')]` — the layout also has `$_sidebar['flash']`; do NOT build your own toast system.
5. **Interactivity**: Alpine.js only (`x-data` components in `@push('scripts')`). No React, no build step, no imports — everything via CDN already in the layout. Use the layout's `asyncCombobox(config)` helper for autocomplete fields.
6. **Tables**: use Tabulator (already loaded) for data-heavy lists; plain Blade `<table>` for small static lists.
7. **Forms**: classic `<form method="POST">` with `@csrf` (+ `@method('PUT'|'PATCH'|'DELETE')`), server-side validation errors render via the layout's `$errors` block. Repopulate with `old(...)`.
8. **Fidelity**: reproduce the React page's functionality faithfully (columns, filters, permission-gated buttons, links, badges, print, etc.). Tailwind utility classes are fine to copy directly from the tsx. Icons: inline SVGs (copy small lucide-style SVGs like existing blade pages do).
9. **Routes**: don't rename routes or change URLs. Frontend links must use `route(...)` or the literal paths.
10. **No Ziggy/wayfinder**: React route helpers (`@/routes/...`) become `route('name', params)` in Blade or plain URL strings in JS.
11. **Currency/dates**: `Number(x).toLocaleString('id-ID')` in JS or `number_format($x, 0, ',', '.')` in Blade; dates dd/mm/yyyy as existing pages do.
12. Do NOT touch `vite.config`, `package.json`, or delete tsx files — cleanup happens later centrally.
13. After your edits run `php -l` on every changed PHP file and `php artisan route:list > /dev/null` to verify nothing is broken. Do not restart workflows.

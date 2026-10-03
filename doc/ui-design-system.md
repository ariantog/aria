# UI design system (Refactoring UI–inspired)

Aria’s Blade UI uses **Tailwind via CDN** plus a small set of shared CSS classes in
`resources/views/partials/ui-design-system.blade.php` (included from `layouts/app.blade.php`
and `layouts/guest.blade.php`).

## Principles

- **Hierarchy:** page titles (`ui-page-title`) + muted leads (`ui-page-lead`); table headers as small caps, not heavy gray bars.
- **Elevation:** panels use `ui-panel` (soft shadow + hairline border) instead of thick borders alone.
- **Spacing:** wrap page content in `ui-page` for consistent padding and vertical rhythm.
- **Actions:** `ui-btn-primary` / `ui-btn-secondary` for buttons; avoid one-off blue-600 combinations on new pages.
- **Forms:** `ui-label`, `ui-input`, `ui-select`, `ui-textarea` for accessible focus rings and consistent fields.
- **Tables:** wrap tables in `ui-panel` and add class `ui-data-table` on the `<table>`.

## Page header partial

```blade
@include('partials.ui.page-header', [
    'title' => 'Item List',
    'lead' => 'Optional subtitle.',
    'actionsHtml' => '<a href="..." class="ui-btn ui-btn-primary">Add</a>', // optional
])
```

## Migrating existing pages

1. Replace outer `p-4` / `gap-3` wrappers with `ui-page`.
2. Replace `rounded-xl border border-gray-200 bg-white shadow-sm` blocks with `ui-panel`.
3. Replace list `<table class="w-full text-sm">` + gray `<thead>` with `ui-data-table` (drop redundant thead utility classes).
4. Primary CTAs: `ui-btn ui-btn-primary`; secondary: `ui-btn ui-btn-secondary`.

Legacy pages keep working; global focus styles and sidebar active states are improved even without migration.

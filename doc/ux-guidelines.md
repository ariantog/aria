# Aria UI / UX guidelines

Server-rendered Blade + Tailwind (CDN). No SPA build step. These notes target **readability for long ERP sessions** (lists, ledgers, transaction entry).

## External references (good starting points)

| Resource | Use for |
|----------|---------|
| [Refactoring UI](https://www.refactoringui.com/) (book / previews) | Type scale, hierarchy, spacing — especially “fewer font sizes, more weight/contrast” |
| [Tailwind UI — Application UI → Tables](https://tailwindui.com/components/application-ui/lists/tables) | Dense but legible table patterns (sticky header, zebra optional, aligned numerics) |
| [GOV.UK — Tables](https://design-system.service.gov.uk/components/table/) | When in doubt: simple `<table>`, clear headers, minimal decoration |
| [WCAG 1.4.4 Resize text](https://www.w3.org/WAI/WCAG22/Understanding/resize-text.html) | Body text should remain usable when users zoom or raise root font size |

Aria is not adopting a full design system (Material, Polaris, etc.); borrow **patterns**, not a wholesale theme swap.

## Typography (current issues)

**Symptoms:** Mixed sizes (`text-xs` in chrome and table headers), 14px HTML root (`--app-font-size` in `resources/views/layouts/app.blade.php`), sidebar labels at `text-xs`.

**Direction:**

1. **One family:** Inter (already loaded in `layouts/app.blade.php`). Guest layout uses system UI — align guest with Inter when touching auth screens.
2. **Root size:** Prefer **16px** default (`--app-font-size: 16px` or user preference via existing font-size cookie). Reserve 14px only for secondary meta, not primary table body.
3. **Scale (Tailwind):** Body `text-base`; table body `text-sm` minimum (not `text-xs`); headers `text-sm font-medium` with **sentence case**, not uppercase + tracking (see transactions list thead today).
4. **User control:** Keep the appearance/font-size preference path; changing the default helps everyone who never opens settings.

## Data tables (current issues)

**Symptoms:** `w-full` + wide `min-w-[…]` + `max-w-[180px]` truncation on party columns; header row smaller than body; inconsistent column intent (dates vs money vs names).

**Direction:**

1. **Shared partial:** Prefer one wrapper for list tables (e.g. extend patterns from `resources/views/transactions/partials/list-table.blade.php`) with documented utility classes — avoid one-off table markup per page.
2. **Layout model:**
   - `table-fixed` + explicit `<colgroup>` **or** auto layout with **few** width constraints.
   - **Numbers/dates:** `text-right tabular-nums whitespace-nowrap`.
   - **Names/descriptions:** allow wrap (`whitespace-normal`), avoid aggressive `max-w-*` unless the column is optional on mobile (`hidden lg:table-cell`).
3. **Header row:** Same size as body or one step smaller — not `text-xs uppercase` for primary grids.
4. **Density:** Target ~40–44px row padding (`py-2.5` / `py-3`), not spreadsheet-tight `py-1`.
5. **Horizontal scroll:** Keep overflow wrapper; `min-w-*` on `<table>` is fine for scroll regions, but column rules inside should still follow (2).

**Sanity check:** If stripping Tailwind classes produces a more readable table, the layout rules are wrong — fix structure (`colgroup`, alignment, wrap) before adding more utilities.

## Scope for agents

- **Do not** mix a repo-wide typography/table pass with Laravel upgrades or accounting fixes.
- **Do** apply these rules when editing a Blade file for other reasons (leave the table better than you found it).
- After global changes, run `tests/Feature/BladePagesRenderTest.php`.

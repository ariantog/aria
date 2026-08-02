---
name: Plain server-rendered tables, not client-side data grids
description: The owner rejected Tabulator.js on the transactions index; a merged task agent reverted this once.
---

The transactions index must be a **plain server-rendered HTML table** with Laravel pagination and
sortable header links — not Tabulator.js or any other client-side data grid.

**Why:** Tabulator was tried first and the owner rejected it: *"tabulator is only for a few certain
pages, for now, just use normal table, as long as the whole page is readable."* Readability at
narrow widths matters more to them than grid features. The plain table achieves it by hiding the
sender/receiver columns below `lg` (showing them inline under the invoice instead) and the items
column below `xl`.

Keeping it server-rendered also removes a whole class of stored-XSS risk: Blade escapes by default,
whereas the grid version needed a hand-rolled `esc()` helper in every HTML formatter string.

**How to apply:** If the index ever comes back with a client-side grid, that is a regression, not an
improvement — restore the plain table. This has already happened once via a merged task branch that
had forked from a pre-migration state, so re-check the index view after any merge that touches it.
Removing the grid also means removing its JSON/AJAX branch from the controller's `index()` and any
tests written against that JSON endpoint (rewrite them to assert against rendered HTML).

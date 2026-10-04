# Rollback: Refactoring UI polish (PR #825)

Branch: `cursor/refactoring-ui-polish-28d6`

This change is **presentation-only** (Blade + CSS in layout). Rolling back restores prior styling; **no migrations**, **no config**, and **no PHP business logic** except two extra assertions in `BladePagesRenderTest.php`.

## Files touched (12)

| File | Role |
|------|------|
| `resources/views/partials/ui-design-system.blade.php` | **New** — remove entirely on rollback |
| `resources/views/partials/ui/page-header.blade.php` | **New** — remove entirely on rollback |
| `doc/ui-design-system.md` | **New** — optional to keep for reference |
| `resources/views/layouts/app.blade.php` | Includes design system; shell classes |
| `resources/views/layouts/guest.blade.php` | Guest shell + design system |
| `resources/views/auth/login.blade.php` | `ui-input` / `ui-btn` classes |
| `resources/views/dashboard.blade.php` | `ui-page`, `ui-panel` |
| `resources/views/items/index.blade.php` | List page only (not create form) |
| `resources/views/transactions/index.blade.php` | Index only (not `create.blade.php`) |
| `resources/views/transactions/partials/list-table.blade.php` | Panel + `ui-data-table` class on same `<table>` |
| `resources/views/partials/pagination.blade.php` | Button classes |
| `tests/Feature/BladePagesRenderTest.php` | Table markup assertions |

**Not touched:** `transactions/create.blade.php`, `items/create.blade.php`, item form partials, transaction store actions, observers, posting.

---

## Option A — Not merged yet

Close or do not merge [PR #825](https://github.com/ariantog/aria/pull/825). `main` stays unchanged.

---

## Option B — Merged as one squash commit on `main`

Find the merge commit on `main` (GitHub squash title usually mentions “Refactoring UI” or PR #825):

```bash
git fetch origin main
git checkout main
git pull origin main
git log -1 --oneline   # note the squash SHA, call it SQUASH

git revert SQUASH --no-edit
git push origin main
```

Deploy as you normally would after any `main` push.

---

## Option C — Merged with all commits (merge commit, not squash)

Revert **newest first** (three commits on the feature branch):

```bash
git fetch origin main
git checkout main
git pull origin main

git revert 8376ca05 --no-edit   # table test assertions
git revert 172ada43 --no-edit   # design system + layouts
git revert 6fcd99c2 --no-edit   # page ui-* adoption

git push origin main
```

One combined revert commit instead:

```bash
git revert --no-commit 8376ca05 172ada43 6fcd99c2
git commit -m "Revert Refactoring UI polish (PR #825)."
git push origin main
```

---

## Option D — Hotfix on `main` without revert commits (reset branch to pre-merge)

**Only if** no one else has pulled the bad merge and you accept rewriting `main` (coordinate with team):

```bash
git checkout main
git reset --hard <SHA-before-merge>
git push origin main --force-with-lease
```

Prefer **Option B or C** unless maintainer policy allows force-push.

---

## Option E — Partial rollback (emergency)

If a global style causes pain but you want to keep some pages:

1. In `resources/views/layouts/app.blade.php` and `guest.blade.php`, remove the line:
   `@include('partials.ui-design-system')`
2. Restore pre-PR body/sidebar/header classes from git:
   ```bash
   git show main~1:resources/views/layouts/app.blade.php > resources/views/layouts/app.blade.php
   ```
   (Adjust `main~1` to the commit before the UI merge.)

3. Revert or manually restore the 6 page partials listed above.

Partial rollback is error-prone; **full revert (B or C) is recommended.**

---

## After rollback — verify

```bash
./vendor/bin/pest tests/Feature/BladePagesRenderTest.php
./vendor/bin/pest tests/Feature/ItemCreateFormTest.php tests/Feature/LargeSellTransactionTest.php
```

Manual smoke (maintainer): item create, sell, move — should behave as before; only styling differs when UI is present.

---

## Commits on feature branch (reference)

```
8376ca05 Assert list pages render HTML tables for copy-to-clipboard.
172ada43 Add Refactoring UI-inspired design tokens and layout shell polish.
6fcd99c2 Apply ui-* components to dashboard, lists, auth, and pagination.
```

SHAs may differ if history was rebased; use `git log --grep="Refactoring UI"` or `git log -- doc/ui-design-system.md` on `main` after merge.

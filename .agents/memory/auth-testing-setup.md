---
name: Auth & testing constraints
description: Durable auth/testing constraints for this Laravel + Fortify app
---
- Superadmin is exclusively user id 1 (owner's explicit rule) — never role-based. Permission-denial tests must create a filler user first so the tested user isn't id 1.
- **Why:** With RefreshDatabase on in-memory sqlite, ids restart at 1 each test; the bypass silently makes "cannot access" assertions pass/fail wrongly.
- Tests must use RefreshDatabase, never DatabaseTransactions — the in-memory test DB is never migrated otherwise.
- MySQL-only SQL cannot run on the sqlite test connection; skip such tests when the driver is sqlite.
- Values embedded in inline JS in Blade must use `@json(...)`, not `{{ }}` — HTML escaping corrupts `&` in query strings and fatals on arrays.

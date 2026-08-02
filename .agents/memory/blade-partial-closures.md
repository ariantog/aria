---
name: Blade partial helper functions
description: Why helpers inside Blade @php blocks must be closures, not named functions.
---

Never declare a named PHP function (`function hasPerm(...) {}`) inside a Blade `@php` block in a
partial or layout. Use a closure assigned to a variable instead (`$hasPerm = function (...) {...};`).

**Why:** Blade compiles the partial to a plain PHP file that is `include`d. In a long-lived PHP
process that renders the same partial more than once — the test suite, Octane, or any queue worker —
the `include` runs again and PHP throws a fatal `Cannot redeclare <fn>()`. It does *not* reproduce
under `php artisan serve`, where each request is a fresh process, so it slips through manual
browser testing and only surfaces once a feature test suite renders two pages in one run.

**How to apply:** Any time a Blade view needs a reusable helper, reach for a closure, a view
composer, or a Blade component. If you inherit a partial that declares named functions and tests
start failing with "Cannot redeclare", this is the cause — convert them and update every call site
(`hasPerm($perms, 'x')` becomes `$hasPerm('x')` when `$perms` is captured via `use`).

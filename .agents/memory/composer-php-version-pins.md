---
name: Composer version pins for this environment
description: Two packages are deliberately held back to match the environment's PHP version — do not "upgrade" them.
---

Two Composer constraints in this project are intentionally lower than what the upstream project
would otherwise use:

- `spatie/laravel-permission` is pinned to `^6.0`. Version 7.x requires PHP 8.4.
- `phpoffice/phpspreadsheet` is pinned to `^1.29`. Newer majors pull a `zipstream-php` that
  requires PHP 8.3.

**Why:** The Replit container runs an older PHP than either package's newest major. Bumping either
one makes `composer install` fail to resolve, which looks like a broken lockfile rather than a
version conflict.

**How to apply:** If `composer install` starts failing on platform requirements, check these two
first before touching the lockfile. Only raise the pins after confirming the container's PHP version
has actually moved (`php -v`).

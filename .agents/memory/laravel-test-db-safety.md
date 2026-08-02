---
name: Never point the test suite at the dev database
description: RefreshDatabase is global on Feature tests here, so overriding DB_DATABASE wipes real dev data.
---

Never run the test suite with `DB_DATABASE` overridden to point at the real dev SQLite file.

**Why:** `tests/Pest.php` applies `RefreshDatabase` to everything in `Feature`, which migrates
*fresh* — it drops all tables first. Pointing the suite at the working database therefore silently
destroys all seeded data. `phpunit.xml` already forces an in-memory SQLite DB precisely to prevent
this; overriding it on the command line defeats that guard. This bites hardest when a test needs
seeded data and the "obvious" fix looks like aiming the suite at the seeded database.

**How to apply:** Let tests build their own fixtures with factories and let `phpunit.xml` supply the
in-memory connection. If a test needs data, create it in `beforeEach`/`setUp`. If dev data does get
wiped, re-run the project's seeders rather than hand-rebuilding rows.

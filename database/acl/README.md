# ACL import data (one-time migration only)

This folder holds a **SQL dump from the Laravel 5 app** used to seed the new
Spatie permission system. Nothing here is loaded or executed at runtime.

## What gets converted

| Old (L5 dump) | New (L12) |
|---------------|-----------|
| `acl` table | `permissions` + `role_has_permissions` |
| `users.role_id` | `model_has_roles` (Spatie) |
| `roles` (name only; sidebar/sidenav ignored) | `roles` + Spatie permissions |
| `location_customer` | `addrbook_location` pivot |
| `locations` (id + name only) | `locations` |

## How to run

```bash
php artisan migrate
app(App\Services\PermissionGenerator::class)->generateAll();  # Phase A
php artisan app:import-legacy-acl --dry-run
php artisan app:import-legacy-acl
```

After a successful import on production, this dump can be archived or removed.
The live app never reads the old `acl` table.

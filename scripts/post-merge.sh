#!/bin/bash
# Runs automatically after a task is merged. Must be idempotent, non-interactive and fast.
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> Installing PHP dependencies"
# --no-interaction: stdin is closed during post-merge runs.
composer install --no-interaction --prefer-dist --no-progress

echo "==> Ensuring SQLite database file exists"
# The dev database is gitignored, so a fresh checkout after a merge may not have it.
if [ ! -f database/database.sqlite ]; then
    mkdir -p database
    touch database/database.sqlite
    echo "    created database/database.sqlite"
fi

echo "==> Ensuring APP_KEY is set"
if [ -f .env ] && ! grep -qE '^APP_KEY=.+' .env; then
    php artisan key:generate --force
fi

echo "==> Running migrations"
php artisan migrate --force

echo "==> Ensuring the dev database has preview data"
# The dev SQLite file is not tracked in git, so a merge can leave it empty. Re-seed only when
# there are no users at all — never overwrite an existing dataset.
USER_COUNT=$(php artisan tinker --execute="echo App\Models\User::count();" 2>/dev/null | tr -dc '0-9' | tail -c 4)
if [ -z "$USER_COUNT" ] || [ "$USER_COUNT" = "0" ]; then
    echo "    empty database, seeding preview data"
    php artisan db:seed --class=SuperAdminSeeder --force
    php artisan db:seed --class=SettingSeeder --force
    php artisan db:seed --class=DemoDataSeeder --force
else
    echo "    database already has $USER_COUNT user(s), skipping seed"
fi

echo "==> Clearing stale caches"
# Compiled Blade views and cached config/routes can reference code that the merge changed.
php artisan view:clear
php artisan config:clear
php artisan route:clear
php artisan cache:clear || true

echo "==> Post-merge setup complete"

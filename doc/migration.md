php artisan app:migrate-legacy-users
php artisan app:migrate-legacy-addrbook
php artisan app:migrate-legacy-items
php artisan migrate:legacy-journals
php artisan migrate:legacy-transactions --year=2025
php artisan report:recalculate
php artisan db:seed --class=SuperAdminSeeder
php artisan app:generate-stock-intelligence
php artisan app:sync-stat-sells --refresh

php artisan app:generate-stock-intelligence --date=2026-04-12
php artisan app:truncate-items
php artisan app:reset-legacy-items-migration --force

php artisan app:delete-transactions --year=2026

php artisan app:recalculate-nett-cash
php artisan app:recalculate-cash-flow
php artisan app:recalculate-inventory-health
php artisan app:recalculate-item-sales

php artisan db:truncate-transactions --force

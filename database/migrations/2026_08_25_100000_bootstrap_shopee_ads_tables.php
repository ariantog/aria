<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Self-contained Shopee Ads schema bootstrap for production.
 *
 * Runs the base install and GMV/item-ads expand migrations in order. Safe when
 * only the expand migration was applied first (base tables missing) or when
 * neither has run yet. All steps are guarded with hasTable/hasColumn checks.
 *
 *   php artisan migrate --path=database/migrations/2026_08_25_100000_bootstrap_shopee_ads_tables.php --force
 */
return new class extends Migration
{
    public function up(): void
    {
        (require __DIR__.'/2026_08_22_100000_install_shopee_ads_tables.php')->up();
        (require __DIR__.'/2026_08_22_110000_expand_shopee_ads_gmv_and_item_ads.php')->up();
        (require __DIR__.'/2026_08_26_120000_widen_shopee_ads_item_id_column.php')->up();
        (require __DIR__.'/2026_08_28_100000_add_shopee_ads_item_performance_and_topup.php')->up();
    }

    public function down(): void
    {
        (require __DIR__.'/2026_08_22_110000_expand_shopee_ads_gmv_and_item_ads.php')->down();
        (require __DIR__.'/2026_08_22_100000_install_shopee_ads_tables.php')->down();
    }
};

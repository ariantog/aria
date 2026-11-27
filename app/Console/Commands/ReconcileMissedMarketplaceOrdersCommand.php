<?php

namespace App\Console\Commands;

use App\Services\Marketplace\MarketplaceMissedPushReconcileService;
use Illuminate\Console\Command;

class ReconcileMissedMarketplaceOrdersCommand extends Command
{
    protected $signature = 'marketplace:reconcile-missed-orders
                            {--channel=shopee : shopee|tiktok|all}
                            {--shop-id= : Hanya shop_id tertentu (Shopee shopee_syncs)}
                            {--days= : Window update_time dalam hari (maks dari config)}
                            {--detect-only : Hanya deteksi gap; tidak ingest otomatis}';

    protected $description = 'Reconcile missed marketplace push orders (platform ada, marketplace_channel_orders kosong)';

    public function handle(MarketplaceMissedPushReconcileService $reconcile): int
    {
        if (! (bool) config('marketplace.missed_push_reconcile.enabled', false)) {
            $this->comment('MARKETPLACE_MISSED_PUSH_RECONCILE_ENABLED=false — tidak ada yang dijalankan.');

            return self::SUCCESS;
        }

        $channel = strtolower(trim((string) $this->option('channel')));
        $shopId = $this->option('shop-id');
        $shopId = is_numeric($shopId) ? (int) $shopId : null;
        $days = $this->option('days');
        $days = is_numeric($days) ? (int) $days : null;
        $ingestGaps = ! $this->option('detect-only');

        $results = $reconcile->reconcile($channel, $shopId, $days, $ingestGaps);

        if ($results === []) {
            $this->warn('Tidak ada channel reconcile aktif (periksa MARKETPLACE_MISSED_PUSH_RECONCILE_ENABLED dan channel).');

            return self::SUCCESS;
        }

        $hadFailure = false;

        foreach ($results as $ch => $result) {
            $this->info(sprintf(
                '[%s] %d shop, %d order terlihat, %d gap, %d ingest, %d lewati.',
                $ch,
                $result['shops'],
                $result['orders_seen'],
                $result['gaps_queued'],
                $result['ingested'],
                $result['skipped'],
            ));

            foreach ($result['errors'] as $error) {
                $this->warn($error);
            }

            if ($result['errors'] !== [] && ($result['ingested'] ?? 0) === 0) {
                $hadFailure = true;
            }
        }

        return $hadFailure ? self::FAILURE : self::SUCCESS;
    }
}
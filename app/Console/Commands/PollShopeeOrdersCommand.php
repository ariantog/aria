<?php

namespace App\Console\Commands;

use App\Services\Marketplace\ShopeeOrderPollService;
use Illuminate\Console\Command;

class PollShopeeOrdersCommand extends Command
{
    protected $signature = 'shopee:poll-orders
                            {--shop-id= : Hanya shop_id tertentu (shopee_syncs)}
                            {--days= : Window update_time dalam hari (maks 15)}';

    protected $description = 'Poll Shopee Open API untuk shop direct_order_ingest (FLEXI/MY tanpa Jubelio)';

    public function handle(ShopeeOrderPollService $poll): int
    {
        if (! $poll->enabled()) {
            $this->comment('SHOPEE_ORDER_POLL_ENABLED=false — tidak ada yang dijalankan.');

            return self::SUCCESS;
        }

        $shopId = $this->option('shop-id');
        $shopId = is_numeric($shopId) ? (int) $shopId : null;
        $days = $this->option('days');
        $days = is_numeric($days) ? (int) $days : null;

        $result = $poll->poll($shopId, $days);

        $this->info(sprintf(
            'Shopee poll: %d shop, %d order terlihat, %d ingest, %d lewati.',
            $result['shops'],
            $result['orders_seen'],
            $result['ingested'],
            $result['skipped'],
        ));

        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
<?php

namespace App\Console\Commands;

use App\Models\MarketplaceWebhookEvent;
use App\Models\ShopeeFlexiShop;
use App\Services\Marketplace\ShopeeOrderPollService;
use Illuminate\Console\Command;

class PullShopeeOrderCommand extends Command
{
    protected $signature = 'shopee:pull-order
                            {ordersn : Shopee order_sn / ordersn}
                            {--shop-id= : shop_id (wajib; lihat Omnichannel → Shopee Admin OAuth)}';

    protected $description = 'Tarik satu order Shopee lewat Open API (FLEXI/MY — tidak lewat webhook Jubelio atau push Console).';

    public function handle(ShopeeOrderPollService $poll): int
    {
        $orderSn = trim((string) $this->argument('ordersn'));
        $shopId = $this->option('shop-id');
        $shopId = is_numeric($shopId) ? (int) $shopId : 0;

        if ($shopId <= 0) {
            $this->error('Berikan --shop-id= (Shop ID Malaysia/FLEXI dari tab Connections).');

            return self::FAILURE;
        }

        if ($orderSn === '') {
            $this->error('ordersn kosong.');

            return self::FAILURE;
        }

        $flexi = ShopeeFlexiShop::query()->where('shop_id', $shopId)->first();
        if ($flexi === null) {
            $this->warn("Shop {$shopId} tidak ada di /shopee-flexi — ingest tetap dijalankan, prefix invoice fallback dari shopee_syncs/config.");
        } elseif (! $flexi->direct_order_ingest) {
            $this->warn("Shop {$shopId}: direct_order_ingest=false di FLEXI — cron poll melewati shop ini, pull manual tetap boleh.");
        }

        if (! config('marketplace.webhook.unified_storage', false)) {
            $this->warn('MARKETPLACE_WEBHOOK_UNIFIED_STORAGE=false — baris tidak masuk marketplace_webhook_events / omnichannel.');
        }

        $result = $poll->pullAndIngestOrder($shopId, $orderSn);

        if ($result['error'] !== null) {
            $this->error($result['error']);

            return self::FAILURE;
        }

        $this->info("Order {$orderSn} @ shop {$shopId} — status: ".($result['order_status'] ?? '—'));
        $this->line('Shadow: '.($result['shadow'] ? 'ya' : 'tidak'));
        if ($flexi !== null) {
            $this->line('Jubelio placeholder: tidak (shop FLEXI — gunakan /shopee-flexi/orders untuk form SELL)');
        } else {
            $this->line('Jubelio placeholder: '.($result['jubelio_placeholder'] ? 'ya' : 'tidak'));
        }
        $this->line('Unified webhook event: '.($result['webhook_event'] ? 'ya' : 'tidak'));

        if ($result['webhook_event'] && \Illuminate\Support\Facades\Schema::hasTable('marketplace_webhook_events')) {
            $event = MarketplaceWebhookEvent::query()
                ->where('channel', 'shopee')
                ->where('order_ref', $orderSn)
                ->orderByDesc('id')
                ->first();
            if ($event !== null) {
                $this->line("Webhook event id {$event->id} (status: ".($event->order_status ?? '—').')');
            }
        }

        if (! $result['ingested']) {
            $this->warn('Tidak ada layer ingest aktif (cek SHOPEE_PUSH_SHADOW_ENABLED, SHOPEE_PRECREATE_JUBELIO_QUEUE, MARKETPLACE_WEBHOOK_UNIFIED_STORAGE).');
        }

        return self::SUCCESS;
    }
}
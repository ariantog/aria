<?php

namespace App\Console\Commands;

use App\Models\MarketplaceWebhookEvent;
use App\Services\Marketplace\MarketplaceOrderRefPullService;
use App\Services\Marketplace\MarketplaceWebhookRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PullMarketplaceOrderCommand extends Command
{
    protected $signature = 'marketplace:pull-order
                            {order_ref : Order ref (Shopee ordersn / TikTok order_id)}
                            {--channel=shopee : shopee, tiktok, or tokopedia}
                            {--shop-ref= : Shopee shop_id or TikTok shop_ref}
                            {--shop-id= : Alias for --shop-ref (Shopee)}';

    protected $description = 'Tarik satu order marketplace lewat Open API dan simpan seperti webhook (unified storage).';

    public function handle(MarketplaceOrderRefPullService $pullService): int
    {
        $orderRef = trim((string) $this->argument('order_ref'));
        $channel = strtolower(trim((string) $this->option('channel')));
        $shopRef = $this->option('shop-ref');
        if (! filled($shopRef)) {
            $shopRef = $this->option('shop-id');
        }
        $shopRef = filled($shopRef) ? trim((string) $shopRef) : null;

        if ($orderRef === '') {
            $this->error('order_ref kosong.');

            return self::FAILURE;
        }

        if (! config('marketplace.webhook.unified_storage', false)) {
            $this->warn('MARKETPLACE_WEBHOOK_UNIFIED_STORAGE=false — baris tidak masuk marketplace_webhook_events / omnichannel.');
        }

        $result = $pullService->pull($channel, $orderRef, $shopRef);

        if ($result['error'] !== null) {
            $this->error($result['error']);

            return self::FAILURE;
        }

        $this->info("Order {$orderRef} ({$result['channel']}) — status: ".($result['order_status'] ?? '—'));
        if (filled($result['commerce_platform'] ?? null)) {
            $this->line('Commerce platform: '.$result['commerce_platform']);
        }
        $this->line('Unified webhook: '.($result['webhook_event'] ? 'ya' : 'tidak'));
        if ($result['channel'] === MarketplaceWebhookRecorder::CHANNEL_SHOPEE) {
            $this->line('Shadow: '.($result['shadow'] ? 'ya' : 'tidak'));
            $this->line('Jubelio placeholder: '.($result['jubelio_placeholder'] ? 'ya' : 'tidak'));
        } else {
            $this->line('TikTok inbox: '.($result['inbox_event'] ? 'ya' : 'tidak'));
        }

        if ($result['return_signal'] ?? false) {
            $this->warn('Sinyal return terdeteksi — verifikasi fisik di Marketplace returns sebelum post.');
        }

        if (filled($result['warning'] ?? null)) {
            $this->warn($result['warning']);
        }

        if ($result['webhook_event'] && Schema::hasTable('marketplace_webhook_events')) {
            $event = MarketplaceWebhookEvent::query()
                ->where('channel', $result['channel'])
                ->where('order_ref', $orderRef)
                ->orderByDesc('id')
                ->first();
            if ($event !== null) {
                $this->line("Webhook event id {$event->id}");
            }
        }

        return self::SUCCESS;
    }
}
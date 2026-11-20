<?php

namespace App\Console\Commands;

use App\Jobs\RecordShopeePushShadowJob;
use App\Services\Marketplace\ShopeeOrderShadowRecorder;
use App\Services\Shopee\ShopeePushSignatureVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Maintainer ops: exercise code-3 shadow path without HTTP or transaction writes.
 */
class SimulateShopeeOrderStatusPushCommand extends Command
{
    protected $signature = 'shopee:simulate-order-status-push
                            {--ordersn=220810QSK8S7BX : Shopee ordersn in push data}
                            {--status=PROCESSED : Order status in push data}
                            {--shop-id=727720655 : shop_id in push payload}
                            {--dispatch-job : Queue RecordShopeePushShadowJob after stub upsert}';

    protected $description = 'Simulate Shopee order_status_push (code 3) into marketplace_order_shadows (read-only; console only).';

    public function handle(
        ShopeeOrderShadowRecorder $recorder,
        ShopeePushSignatureVerifier $verifier,
    ): int {
        if (! Schema::hasTable('marketplace_order_shadows')) {
            $this->error('Table marketplace_order_shadows missing — run omnichannel migrations first.');

            return self::FAILURE;
        }

        if (! config('services.shopee_push.shadow_enabled')) {
            $this->warn('SHOPEE_PUSH_SHADOW_ENABLED=false — stub will still be written by this command.');
        }

        $orderSn = (string) $this->option('ordersn');
        $status = (string) $this->option('status');
        $shopId = (int) $this->option('shop-id');
        $timestamp = time();

        $data = [
            'items' => [],
            'ordersn' => $orderSn,
            'status' => $status,
            'update_time' => $timestamp,
        ];

        $payload = [
            'data' => $data,
            'shop_id' => $shopId,
            'code' => 3,
            'timestamp' => $timestamp,
        ];

        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $callbackUrl = trim((string) config('services.shopee_push.callback_url', ''));
        if ($callbackUrl === '') {
            $callbackUrl = rtrim((string) config('app.url'), '/').'/shopee/push';
        }

        $authorization = $verifier->sign($callbackUrl, $rawBody);

        $shadow = $recorder->upsertStubFromPush($shopId, $orderSn, $status, 3, $data, $payload);

        if ($this->option('dispatch-job')) {
            RecordShopeePushShadowJob::dispatch($shopId, $orderSn, $status, 3);
            $this->line('Dispatched RecordShopeePushShadowJob (read-only enrich).');
        }

        $this->info("Shadow stub upserted for ordersn {$orderSn} (id {$shadow->id}, compare_note=".($shadow->compare_note ?? '—').').');
        $this->newLine();
        $this->line('Canonical code-3 body (for manual curl against /shopee/push):');
        $this->line($rawBody);
        $this->newLine();
        $this->line('Authorization (HMAC of callbackUrl|body with Live Push Partner Key):');
        $this->line($authorization);
        $this->newLine();
        $this->line("Callback URL used for signature base string: {$callbackUrl}");

        return self::SUCCESS;
    }
}
<?php

use App\Models\Addrbook;
use App\Models\Jubelioorder;
use App\Models\Shopeesync;
use App\Services\Marketplace\ShopeeJubelioQueuePrecreator;
use App\Services\Marketplace\ShopeeSyncInvoicePrefix;
use App\Services\Shopee\ShopeePushSignatureVerifier;

it('resolves SPMY invoice from shopee_sync shop mapping', function () {
    $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);

    Shopeesync::create([
        'warehouse_id' => $warehouse->id,
        'shop_id' => 1925605693,
        'invoice_prefix' => 'SPMY',
        'direct_order_ingest' => true,
        'shopee_location_id' => 'LOC1',
        'shopee_warehouse_id' => 100028423,
        'shopee_warehouse_name' => 'CITOS',
    ]);

    $prefix = app(ShopeeSyncInvoicePrefix::class);

    expect($prefix->invoiceForOrderSn(1925605693, 'MY123'))->toBe('SPMY-MY123')
        ->and($prefix->invoiceForOrderSn(999, 'X'))->toBe('SP-X')
        ->and(app(ShopeeJubelioQueuePrecreator::class)->invoiceForOrderSn('MY123', 1925605693))->toBe('SPMY-MY123');
});

it('precreates jubelio row with flexi prefix on push', function () {
    $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);

    Shopeesync::create([
        'warehouse_id' => $warehouse->id,
        'shop_id' => 1925605693,
        'invoice_prefix' => 'SPMY',
        'direct_order_ingest' => true,
        'shopee_location_id' => 'LOC1',
        'shopee_warehouse_id' => 100028423,
        'shopee_warehouse_name' => 'CITOS',
    ]);

    config([
        'services.shopee_push.partner_key' => 'test-partner-key',
        'services.shopee_push.verify_signature' => true,
        'services.shopee_push.shadow_enabled' => false,
        'services.shopee_push.store_inbox' => false,
        'services.shopee_push.precreate_jubelio_queue' => true,
    ]);

    $body = '{"data":{"ordersn":"FLEXI_MY_1","status":"PROCESSED","update_time":1660123127},"shop_id":1925605693,"code":3,"timestamp":1660123127}';
    $callbackUrl = url(route('shopee.push', [], false));
    $authorization = app(ShopeePushSignatureVerifier::class)->sign($callbackUrl, $body);

    $this->call('POST', route('shopee.push'), [], [], [], [
        'HTTP_AUTHORIZATION' => $authorization,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    expect(Jubelioorder::where('invoice', 'SPMY-FLEXI_MY_1')->count())->toBe(1)
        ->and(Jubelioorder::where('invoice', 'SP-FLEXI_MY_1')->exists())->toBeFalse();
});
<?php

use App\Models\Jubelioorder;
use App\Models\OperationalAlert;
use App\Models\Setting;
use App\Services\Jubelio\JubelioOrderSyncStatus;
use App\Services\Jubelio\JubelioWebhookOversellScanner;
use App\Services\Lark\JubelioErrorBacklogScanService;
use App\Services\Lark\LarkSettingsService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Config::set('lark.enabled', true);
    Config::set('lark.dry_run', false);
    Config::set('lark.bot_webhook_url', 'https://open.larksuite.com/open-apis/bot/v2/hook/test-token');
});

test('jubelio error backlog scanner dispatches once when count meets threshold', function () {
    Http::fake(['*' => Http::response(['StatusCode' => 0])]);

    Setting::query()->updateOrCreate(
        ['slug' => LarkSettingsService::SETTING_SLUG],
        [
            'name' => 'Lark Ops Settings',
            'group' => 'lark',
            'value' => [
                'jubelio' => ['error_backlog_threshold' => 2],
                'events' => [],
            ],
            'location_id' => 0,
        ],
    );

    foreach (['INV-ERR-1', 'INV-ERR-2'] as $invoice) {
        Jubelioorder::create([
            'jubelio_order_id' => 'err-'.$invoice,
            'source' => 1,
            'invoice' => $invoice,
            'type' => 'SELL',
            'order_status' => 'SHIPPED',
            'run_count' => 1,
            'status' => 1,
            'error_type' => JubelioOrderSyncStatus::ERROR_SKU,
        ]);
    }

    $scanner = app(JubelioErrorBacklogScanService::class);
    $scanner->run();
    $scanner->run();

    Http::assertSentCount(1);
    expect(OperationalAlert::query()->where('event_key', 'jubelio.error.backlog')->count())->toBe(1);
    expect(OperationalAlert::query()->where('fingerprint', JubelioErrorBacklogScanService::FINGERPRINT)->first()?->sent_at)->not->toBeNull();
});

test('jubelio error backlog re-arms when count drops below threshold', function () {
    Http::fake(['*' => Http::response(['StatusCode' => 0])]);

    Setting::query()->updateOrCreate(
        ['slug' => LarkSettingsService::SETTING_SLUG],
        [
            'name' => 'Lark Ops Settings',
            'group' => 'lark',
            'value' => [
                'jubelio' => ['error_backlog_threshold' => 2],
                'events' => [],
            ],
            'location_id' => 0,
        ],
    );

    Jubelioorder::create([
        'jubelio_order_id' => 'err-only',
        'source' => 1,
        'invoice' => 'INV-ERR-ONLY',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 1,
        'status' => 1,
        'error_type' => JubelioOrderSyncStatus::ERROR_SKU,
    ]);
    Jubelioorder::create([
        'jubelio_order_id' => 'err-second',
        'source' => 1,
        'invoice' => 'INV-ERR-2ND',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 1,
        'status' => 1,
        'error_type' => JubelioOrderSyncStatus::ERROR_SKU,
    ]);

    $scanner = app(JubelioErrorBacklogScanService::class);
    $scanner->run();

    Jubelioorder::query()->where('invoice', 'INV-ERR-2ND')->delete();

    $scanner->run();

    $alert = OperationalAlert::query()->where('fingerprint', JubelioErrorBacklogScanService::FINGERPRINT)->first();
    expect($alert?->status)->toBe('rearmed');

    Jubelioorder::create([
        'jubelio_order_id' => 'err-third',
        'source' => 1,
        'invoice' => 'INV-ERR-3RD',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 1,
        'status' => 1,
        'error_type' => JubelioOrderSyncStatus::ERROR_SKU,
    ]);
    Jubelioorder::create([
        'jubelio_order_id' => 'err-fourth',
        'source' => 1,
        'invoice' => 'INV-ERR-4TH',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 1,
        'status' => 1,
        'error_type' => JubelioOrderSyncStatus::ERROR_SKU,
    ]);

    $scanner->run();

    Http::assertSentCount(2);
});

test('jubelio error backlog scanner respects send disabled toggle', function () {
    Http::fake(['*' => Http::response(['StatusCode' => 0])]);

    $defaults = app(LarkSettingsService::class)->defaults();
    $events = $defaults['events'];
    $events['jubelio.error.backlog']['send_enabled'] = false;

    Setting::query()->updateOrCreate(
        ['slug' => LarkSettingsService::SETTING_SLUG],
        [
            'name' => 'Lark Ops Settings',
            'group' => 'lark',
            'value' => [
                'jubelio' => ['error_backlog_threshold' => 1],
                'events' => $events,
            ],
            'location_id' => 0,
        ],
    );

    Jubelioorder::create([
        'jubelio_order_id' => 'err-disabled',
        'source' => 1,
        'invoice' => 'INV-DISABLED',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 1,
        'status' => 1,
        'error_type' => JubelioOrderSyncStatus::ERROR_SKU,
    ]);

    app(JubelioErrorBacklogScanService::class)->run();

    Http::assertNothingSent();
    expect(OperationalAlert::query()->where('event_key', 'jubelio.error.backlog')->exists())->toBeFalse();
});

test('webhook oversell scanner skips dispatch when send disabled', function () {
    Http::fake(['*' => Http::response(['StatusCode' => 0])]);

    $defaults = app(LarkSettingsService::class)->defaults();
    $events = $defaults['events'];
    $events['jubelio.oversell.webhook']['send_enabled'] = false;

    Setting::query()->updateOrCreate(
        ['slug' => LarkSettingsService::SETTING_SLUG],
        [
            'name' => 'Lark Ops Settings',
            'group' => 'lark',
            'value' => ['events' => $events],
            'location_id' => 0,
        ],
    );

    $item = \App\Models\Item::factory()->create(['jubelio_item_id' => 88801, 'code' => 'OFF-SKU']);
    $warehouse = \App\Models\Addrbook::factory()->warehouse()->create();
    $customer = \App\Models\Addrbook::factory()->create(['type' => \App\Models\Addrbook::TYPE_CUSTOMER]);

    \App\Models\WarehouseItem::query()->create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => \App\Models\Addrbook::TYPE_WAREHOUSE,
        'quantity' => 0,
    ]);

    \App\Models\Jubeliosync::create([
        'jubelio_store_id' => 20,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => -1,
        'jubelio_location_name' => 'Pusat',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    app(JubelioWebhookOversellScanner::class)->scanAndNotify([
        'salesorder_no' => 'INV-SEND-OFF',
        'status' => 'DRAFT',
        'store_id' => 20,
        'location_id' => -1,
        'items' => [
            ['item_id' => 88801, 'qty' => 3],
        ],
    ]);

    Http::assertNothingSent();
});

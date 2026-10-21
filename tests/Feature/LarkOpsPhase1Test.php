<?php

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubeliosync;
use App\Models\LarkRestockRequest;
use App\Models\OperationalAlert;
use App\Models\RestockCell;
use App\Models\RestockSheet;
use App\Models\Tag;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\Jubelio\JubelioWebhookOversellScanner;
use App\Services\Lark\LarkCardBuilder;
use App\Services\Lark\LarkRestockSubmitService;
use App\Services\Lark\OperationalAlertDispatcher;
use App\Services\Restock\RestockSheetService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Config::set('lark.enabled', true);
    Config::set('lark.dry_run', false);
    Config::set('lark.bot_webhook_url', 'https://open.larksuite.com/open-apis/bot/v2/hook/test-token');
    Config::set('services.jubelio.webhook_secret', 'test-secret');
});

test('lark url buttons request system browser', function () {
    $button = app(LarkCardBuilder::class)->urlButton('Test', 'https://aria.test/items/1?foo=bar');

    expect($button['url'])
        ->toContain('lk_jump_to_browser=true')
        ->toContain('lk_mobile_jump_to_browser=true')
        ->toContain('foo=bar');
});

test('lark url verification returns challenge', function () {
    $this->postJson(route('webhooks.lark.events'), [
        'type' => 'url_verification',
        'challenge' => 'challenge-xyz',
    ])->assertSuccessful()
        ->assertJson(['challenge' => 'challenge-xyz']);
});

test('operational alert dispatcher sends card once per fingerprint', function () {
    Http::fake(['*' => Http::response(['StatusCode' => 0])]);

    $dispatcher = app(OperationalAlertDispatcher::class);
    $dispatcher->dispatch('stock.low_cover', 'fp:test:1', 'info', 'Test', ['SKU' => 'ABC']);
    $dispatcher->dispatch('stock.low_cover', 'fp:test:1', 'info', 'Test', ['SKU' => 'ABC']);

    expect(OperationalAlert::query()->where('fingerprint', 'fp:test:1')->count())->toBe(1);
    Http::assertSentCount(1);
});

test('restock lark callback applies qty to restock sheet', function () {
    Http::fake(['*' => Http::response(['StatusCode' => 0])]);

    $user = User::factory()->create();
    $item = Item::factory()->create(['type' => ItemType::ASSET_LANCAR->value, 'code' => 'LARK-RS-1']);
    $typeTag = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'code' => 'LRK',
        'name' => 'Lark Type',
        'item_type' => ItemType::ASSET_LANCAR->value,
    ]);
    $item->tags()->attach($typeTag->id);

    $sheet = RestockSheet::create([
        'name' => 'Lark Type',
        'type_tag_id' => $typeTag->id,
        'created_by' => $user->id,
    ]);
    app(RestockSheetService::class)->syncSkus($sheet, $user);

    $cell = RestockCell::query()
        ->where('restock_sheet_id', $sheet->id)
        ->where('item_id', $item->id)
        ->first();

    expect($cell)->not->toBeNull();

    $request = LarkRestockRequest::createForItem($item->id, 5, ['code' => $item->code]);

    $result = app(LarkRestockSubmitService::class)->applyFromCallback($request->token, 12, $user);

    expect($result['ok'])->toBeTrue();
    expect($cell->fresh()->qty_restock)->toBe(12);
    expect($request->fresh()->status)->toBe(LarkRestockRequest::STATUS_APPLIED);
});

test('jubelio webhook oversell scanner notifies when stock insufficient', function () {
    Http::fake(['*' => Http::response(['StatusCode' => 0])]);

    $item = Item::factory()->create(['jubelio_item_id' => 99901, 'code' => 'TST-SKU']);
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    WarehouseItem::query()->create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => 1,
    ]);

    Jubeliosync::create([
        'jubelio_store_id' => 10,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => -1,
        'jubelio_location_name' => 'Pusat',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    app(JubelioWebhookOversellScanner::class)->scanAndNotify([
        'salesorder_no' => 'INV-OS-1',
        'status' => 'DRAFT',
        'store_id' => 10,
        'location_id' => -1,
        'items' => [
            ['item_id' => 99901, 'qty' => 5],
        ],
    ]);

    expect(OperationalAlert::query()->where('event_key', 'jubelio.oversell.webhook')->exists())->toBeTrue();
});

test('jubelio webhook oversell sends one lark card per invoice despite stock snapshot changes', function () {
    Http::fake(['*' => Http::response(['StatusCode' => 0])]);

    $item = Item::factory()->create(['jubelio_item_id' => 99902, 'code' => 'TST-SKU-2']);
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    WarehouseItem::query()->create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => 1,
    ]);

    Jubeliosync::create([
        'jubelio_store_id' => 11,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => -1,
        'jubelio_location_name' => 'Pusat',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    $scanner = app(JubelioWebhookOversellScanner::class);
    $payload = [
        'salesorder_no' => 'INV-OS-DEDUP',
        'status' => 'SHIPPED',
        'store_id' => 11,
        'location_id' => -1,
        'items' => [
            ['item_id' => 99902, 'qty' => 5],
        ],
    ];

    $scanner->scanAndNotify($payload);

    WarehouseItem::query()
        ->where('warehouse_id', $warehouse->id)
        ->where('item_id', $item->id)
        ->update(['quantity' => 0]);

    $scanner->scanAndNotify($payload);

    Http::assertSentCount(1);
    expect(OperationalAlert::query()->where('event_key', 'jubelio.oversell.webhook')->count())->toBe(1);
});

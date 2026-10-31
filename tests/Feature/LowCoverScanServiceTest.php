<?php

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubeliosync;
use App\Models\OperationalAlert;
use App\Models\Transaction;
use App\Models\WarehouseItem;
use App\Services\Items\ItemJubelioStockService;
use App\Services\Lark\LowCoverScanService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Config::set('lark.enabled', true);
    Config::set('lark.dry_run', false);
    Config::set('lark.bot_webhook_url', 'https://open.larksuite.com/open-apis/bot/v2/hook/test-token');
});

test('low cover scan uses jubelio mapped warehouse stock only for aria qty', function () {
    Http::fake(['*' => Http::response(['StatusCode' => 0])]);

    $onlineWarehouse = Addrbook::factory()->warehouse()->create(['name' => 'Online - Test']);
    $offlineWarehouse = Addrbook::factory()->warehouse()->create(['name' => 'Offline - Test']);
    Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    Jubeliosync::create([
        'jubelio_store_id' => 50,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => -1,
        'jubelio_location_name' => 'Pusat',
        'warehouse_id' => $onlineWarehouse->id,
        'customer_id' => 1,
        'bin_id' => 0,
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR->value,
        'code' => 'GYMHANDLE-13-BLACK',
        'jubelio_item_id' => 88001,
    ]);

    WarehouseItem::query()->create([
        'warehouse_id' => $onlineWarehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => 0,
    ]);

    WarehouseItem::query()->create([
        'warehouse_id' => $offlineWarehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => 7,
    ]);

    $sellDate = now()->subDays(5)->toDateString();
    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'date' => $sellDate,
        'sender_id' => $onlineWarehouse->id,
        'receiver_id' => 1,
    ]);

    DB::table('transaction_details')->insert([
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
        'transaction_type' => Transaction::TYPE_SELL,
        'quantity' => 30,
        'date' => $sellDate,
        'total' => 300000,
        'price' => 10000,
        'discount' => 0,
        'transaction_disc' => 0,
        'sender_id' => $onlineWarehouse->id,
        'receiver_id' => 1,
    ]);

    $this->mock(ItemJubelioStockService::class, function ($mock) use ($item, $onlineWarehouse) {
        $mock->shouldReceive('detailStockForItem')
            ->once()
            ->andReturnUsing(function ($passedItem, $warehouseItems) use ($item, $onlineWarehouse) {
                expect($passedItem->id)->toBe($item->id);
                expect($warehouseItems->pluck('warehouse_id')->all())->toBe([$onlineWarehouse->id]);

                return [
                    'total' => [
                        'linked' => true,
                        'on_hand' => 99,
                        'available' => 99,
                        'fetch_failed' => false,
                    ],
                    'by_warehouse' => [
                        $onlineWarehouse->id => [
                            'linked' => true,
                            'location_name' => 'Pusat',
                            'available' => 0.0,
                            'mismatch' => false,
                        ],
                    ],
                ];
            });
    });

    app(LowCoverScanService::class)->run();

    $alert = OperationalAlert::query()
        ->whereIn('event_key', ['stock.low_cover', 'stock.low_cover.both'])
        ->where('payload->SKU', $item->code)
        ->first();

    expect($alert)->not->toBeNull();
    expect($alert->payload['Qty Aria (gudang online)'] ?? null)->toBeIn([0, 0.0, '0']);
    expect($alert->payload['Jubelio avail'] ?? null)->toBeIn([0, 0.0, '0']);
    expect($alert->payload['Mismatch'] ?? null)->toBe('tidak');
});

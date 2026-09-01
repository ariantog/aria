<?php

use App\Exceptions\InsufficientWarehouseStockException;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\WarehouseItem;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

test('physical warehouse items cannot be saved with a negative quantity', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $item = Item::factory()->create(['code' => 'SKU-PHYS']);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => $warehouse->type,
        'quantity' => -1,
    ]);
})->throws(InsufficientWarehouseStockException::class, 'SKU-PHYS cuma ada 0, mau diambil 1');

test('virtual warehouse items can be saved with a negative quantity', function () {
    $virtual = Addrbook::factory()->create(['type' => Addrbook::TYPE_V_WAREHOUSE]);
    $item = Item::factory()->create();

    $row = WarehouseItem::create([
        'warehouse_id' => $virtual->id,
        'item_id' => $item->id,
        'warehouse_type' => $virtual->type,
        'quantity' => -20,
    ]);

    expect((float) $row->quantity)->toBe(-20.0);
});

test('supplier warehouse_item rows can stay negative', function () {
    $supplier = Addrbook::factory()->supplier()->create();
    $item = Item::factory()->create();

    $row = WarehouseItem::create([
        'warehouse_id' => $supplier->id,
        'item_id' => $item->id,
        'warehouse_type' => $supplier->type,
        'quantity' => -10,
    ]);

    expect((float) $row->quantity)->toBe(-10.0);
});

test('applyDelta locks and rejects an oversell on a physical warehouse', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $item = Item::factory()->create(['code' => 'SKU-DELTA']);

    WarehouseItem::applyDelta($warehouse->id, $item->id, 3, (int) $warehouse->type);

    expect(fn () => WarehouseItem::applyDelta($warehouse->id, $item->id, -4, (int) $warehouse->type))
        ->toThrow(InsufficientWarehouseStockException::class, 'SKU-DELTA cuma ada 3, mau diambil 4');

    expect((float) WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->value('quantity'))
        ->toBe(3.0);
});

test('applyDelta allows a virtual warehouse to go negative', function () {
    $virtual = Addrbook::factory()->create(['type' => Addrbook::TYPE_V_WAREHOUSE]);
    $item = Item::factory()->create();

    $row = WarehouseItem::applyDelta($virtual->id, $item->id, -7, (int) $virtual->type);

    expect((float) $row->quantity)->toBe(-7.0);
});

test('saving a physical row without changing quantity leaves a legacy negative untouched', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $item = Item::factory()->create();

    $row = new WarehouseItem([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => $warehouse->type,
        'quantity' => -4,
    ]);
    $row->saveQuietly();

    $row->warehouse_type = (string) $warehouse->type;
    $row->save();

    expect((float) $row->fresh()->quantity)->toBe(-4.0);
});

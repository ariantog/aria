<?php

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Services\InventoryService;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->inventoryService = new InventoryService;
    $this->item = Item::factory()->create(['type' => ItemType::ITEM]);

    // InventoryService operates on the customers table (warehouses are customers).
    $this->addrbook = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
});

test('it adds stock to addrbook', function () {
    $wi = $this->inventoryService->add($this->addrbook->id, $this->item, 10);

    expect($wi->quantity)->toEqual(10);
    $this->assertDatabaseHas('warehouse_item', [
        'item_id' => $this->item->id,
        'warehouse_id' => $this->addrbook->id, // Addrbook is the warehouse
        'quantity' => 10,
    ]);
});

test('it deducts stock from addrbook', function () {
    // Add first
    $this->inventoryService->add($this->addrbook->id, $this->item, 20);

    $wi = $this->inventoryService->deduct($this->addrbook->id, $this->item, 5);

    expect($wi->quantity)->toEqual(15);
});

test('it prevents negative stock by default', function () {
    $this->inventoryService->add($this->addrbook->id, $this->item, 5);

    $this->inventoryService->deduct($this->addrbook->id, $this->item, 10);
})->throws(Exception::class); // Expects "cuma ada X..."

test('it allows negative stock when flag is true', function () {
    $wi = $this->inventoryService->deduct($this->addrbook->id, $this->item, 5, true);

    expect($wi->quantity)->toEqual(-5);
});

test('it works for any addrbook regardless of type', function () {
    // InventoryService::add only checks that the Addrbook exists; the warehouse
    // type check is a no-op, so a non-warehouse addrbook also works.
    $addrbook = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    $result = $this->inventoryService->add($addrbook->id, $this->item, 10);
    expect($result)->not->toBeNull();
});

test('it throws exception if addrbook not found', function () {
    $this->inventoryService->add(999999, $this->item, 10);
})->throws(Exception::class, 'Addrbook (Warehouse) not found.');

test('it throws exception for service items', function () {
    $serviceItem = Item::factory()->create(['type' => ItemType::SERVICE]);

    $this->inventoryService->add($this->addrbook->id, $serviceItem, 10);
})->throws(Exception::class, 'Inventory tracking not applicable for Service items.');

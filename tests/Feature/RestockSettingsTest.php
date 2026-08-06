<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Setting;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\Restock\RestockSettingsService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    foreach (['restock-list', 'restock-edit'] as $perm) {
        Permission::firstOrCreate(['name' => $perm]);
    }
    $this->user->givePermissionTo(['restock-list', 'restock-edit']);
});

test('restock settings page renders for authorized users', function () {
    $this->actingAs($this->user)
        ->get(route('restock.settings.edit'))
        ->assertOk()
        ->assertSee('Restock Settings')
        ->assertSee('Default supplier');
});

test('restock settings can be saved', function () {
    $supplier = Addrbook::factory()->supplier()->create(['name' => 'Supplier Umum']);
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Online']);
    $displayWh = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Display']);

    $this->actingAs($this->user)
        ->put(route('restock.settings.update'), [
            'default_supplier_id' => $supplier->id,
            'default_receiver_id' => $warehouse->id,
            'default_warehouse_ids' => [$displayWh->id],
        ])
        ->assertRedirect(route('restock.settings.edit'))
        ->assertSessionHas('success');

    expect(Setting::getValue('restock.default_supplier_id'))->toBe($supplier->id);
    expect(Setting::getValue('restock.default_receiver_id'))->toBe($warehouse->id);
    expect(Setting::getValue('restock.default_warehouse_ids'))->toBe([$displayWh->id]);
});

test('restock settings reject non-supplier default sender', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $receiver = Addrbook::factory()->warehouse()->create();

    $this->actingAs($this->user)
        ->from(route('restock.settings.edit'))
        ->put(route('restock.settings.update'), [
            'default_supplier_id' => $warehouse->id,
            'default_receiver_id' => $receiver->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('error');
});

test('stock display uses configured warehouses only', function () {
    $item = Item::factory()->create();
    $whA = Addrbook::factory()->warehouse()->create();
    $whB = Addrbook::factory()->warehouse()->create();

    WarehouseItem::create([
        'warehouse_id' => $whA->id,
        'warehouse_type' => $whA->type,
        'item_id' => $item->id,
        'quantity' => 10,
    ]);
    WarehouseItem::create([
        'warehouse_id' => $whB->id,
        'warehouse_type' => $whB->type,
        'item_id' => $item->id,
        'quantity' => 25,
    ]);

    $item->load('warehouseItems');
    $service = app(RestockSettingsService::class);

    expect($service->stockQuantityForItem($item))->toBe(35);

    Setting::updateOrCreate(['slug' => 'restock.default_warehouse_ids'], [
        'group' => 'Restock',
        'name' => 'Stock Display Warehouses',
        'value' => [$whA->id],
    ]);

    expect(app(RestockSettingsService::class)->stockQuantityForItem($item))->toBe(10);
});

test('settings lookup returns suppliers and warehouses', function () {
    $supplier = Addrbook::factory()->supplier()->create(['name' => 'Alpha Supplier']);

    $this->actingAs($this->user)
        ->getJson(route('restock.settings.lookup', ['type' => 'supplier', 'search' => 'Alpha']))
        ->assertSuccessful()
        ->assertJsonFragment(['id' => $supplier->id, 'name' => 'Alpha Supplier']);
});

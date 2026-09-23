<?php

use App\Enums\AddrbookType;
use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\WarehouseItem;
use App\Services\PermissionGenerator;
use App\Services\WarehouseCompare\WarehouseCompareService;
use App\Support\UserPreferenceRegistry;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    app(PermissionGenerator::class)->generateForModule('Report');
    Permission::findOrCreate('report-warehouse-compare', 'web');

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-warehouse-compare');
});

test('warehouse compare page requires permission', function () {
    $denied = User::factory()->create();

    $this->actingAs($denied)
        ->get(route('reports.warehouse-compare'))
        ->assertForbidden();
});

test('user can save warehouse compare display defaults', function () {
    $wh1 = Addrbook::factory()->create(['type' => AddrbookType::Warehouse, 'name' => 'Pivot WH']);
    $wh2 = Addrbook::factory()->create(['type' => AddrbookType::Warehouse, 'name' => 'Other WH']);

    $this->actingAs($this->user)
        ->put(route('warehouse-compare-settings.update'), [
            'warehouse_ids' => [$wh1->id, $wh2->id, '', null],
            'item_type' => (string) ItemType::ITEM->value,
            'sort' => WarehouseCompareService::SORT_SOLD,
        ])
        ->assertRedirect(route('warehouse-compare-settings.edit'))
        ->assertSessionHas('success');

    $stored = UserPreference::query()
        ->where('user_id', $this->user->id)
        ->where('slug', UserPreferenceRegistry::WAREHOUSE_COMPARE_SLUG)
        ->value('value');

    expect($stored)->toMatchArray([
        'warehouse_ids' => [$wh1->id, $wh2->id],
        'item_type' => (string) ItemType::ITEM->value,
        'sort' => WarehouseCompareService::SORT_SOLD,
    ]);
});

test('user can save report display with fewer than ten warehouses', function () {
    $wh1 = Addrbook::factory()->create(['type' => AddrbookType::Warehouse, 'name' => 'Pivot WH']);
    $wh2 = Addrbook::factory()->create(['type' => AddrbookType::Warehouse, 'name' => 'Second WH']);

    $this->actingAs($this->user)
        ->post(route('reports.warehouse-compare.save'), [
            'warehouse_ids' => [(string) $wh1->id, '', (string) $wh2->id],
            'item_type' => (string) ItemType::ASSET_LANCAR->value,
            'sort' => WarehouseCompareService::SORT_SKU,
        ])
        ->assertRedirect(route('reports.warehouse-compare', [
            'warehouse_ids' => [$wh1->id, $wh2->id],
            'item_type' => (string) ItemType::ASSET_LANCAR->value,
            'sort' => WarehouseCompareService::SORT_SKU,
        ]))
        ->assertSessionHas('success');

    expect(UserPreference::query()
        ->where('user_id', $this->user->id)
        ->where('slug', UserPreferenceRegistry::WAREHOUSE_COMPARE_SLUG)
        ->value('value')['warehouse_ids'])
        ->toBe([$wh1->id, $wh2->id]);
});

test('warehouse compare grid shows stock across selected warehouses', function () {
    $pivot = Addrbook::factory()->create(['type' => AddrbookType::Warehouse, 'name' => 'Pivot']);
    $other = Addrbook::factory()->create(['type' => AddrbookType::Warehouse, 'name' => 'Branch']);

    $group = ItemGroup::factory()->create([
        'name' => 'GLOVE TEST',
        'master' => 'GLOVE-01',
        'variant' => 'BLACK',
    ]);

    $item = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ASSET_LANCAR,
        'pcode' => 'GLOVE-01',
        'code' => 'GLOVE-01-BLACK-AS',
    ]);

    WarehouseItem::create(['warehouse_id' => $pivot->id, 'item_id' => $item->id, 'quantity' => 1]);
    WarehouseItem::create(['warehouse_id' => $other->id, 'item_id' => $item->id, 'quantity' => 7]);

    $this->actingAs($this->user)
        ->get(route('reports.warehouse-compare', [
            'warehouse_ids' => [$pivot->id, $other->id],
            'item_type' => ItemType::ASSET_LANCAR->value,
            'sort' => WarehouseCompareService::SORT_SKU,
        ]))
        ->assertOk()
        ->assertSee('Warehouse stock compare', false)
        ->assertSee('data-warehouse-compare-table', false)
        ->assertSee('GLOVE TEST', false)
        ->assertSee('Pivot', false)
        ->assertSee('Branch', false);
});

test('warehouse compare manufactured items use flat sku list sorted by code', function () {
    $pivot = Addrbook::factory()->create(['type' => AddrbookType::Warehouse, 'name' => 'Pivot WH']);
    $other = Addrbook::factory()->create(['type' => AddrbookType::Warehouse, 'name' => 'Branch WH']);

    $groupA = ItemGroup::factory()->create([
        'name' => 'SHIRT A',
        'master' => 'CX90001-01',
        'variant' => '01',
    ]);
    $groupB = ItemGroup::factory()->create([
        'name' => 'SHIRT B',
        'master' => 'CX90002-02',
        'variant' => '02',
    ]);

    $itemZ = Item::factory()->create([
        'group_id' => $groupB->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX90002-02',
        'code' => 'AJD-CX90002-02-M',
    ]);
    $itemA = Item::factory()->create([
        'group_id' => $groupA->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX90001-01',
        'code' => 'AJD-CX90001-01-S',
    ]);

    WarehouseItem::create(['warehouse_id' => $pivot->id, 'item_id' => $itemZ->id, 'quantity' => 3]);
    WarehouseItem::create(['warehouse_id' => $pivot->id, 'item_id' => $itemA->id, 'quantity' => 5]);
    WarehouseItem::create(['warehouse_id' => $other->id, 'item_id' => $itemA->id, 'quantity' => 2]);

    $this->actingAs($this->user)
        ->get(route('reports.warehouse-compare', [
            'warehouse_ids' => [$pivot->id, $other->id],
            'item_type' => ItemType::ITEM->value,
            'sort' => WarehouseCompareService::SORT_ITEM_CODE,
        ]))
        ->assertOk()
        ->assertSee('data-testid="warehouse-compare-list-table"', false)
        ->assertSee('data-testid="warehouse-compare-copy"', false)
        ->assertSeeInOrder(['AJD-CX90001-01-S', 'AJD-CX90002-02-M'], false);
});

test('warehouse compare paginates large pivot sku lists', function () {
    $pivot = Addrbook::factory()->create(['type' => AddrbookType::Warehouse, 'name' => 'Pivot WH']);

    $group = ItemGroup::factory()->create([
        'name' => 'BULK TEST',
        'master' => 'GLOVE-99',
        'variant' => 'BLACK',
    ]);

    for ($i = 1; $i <= 105; $i++) {
        $item = Item::factory()->create([
            'group_id' => $group->id,
            'type' => ItemType::ASSET_LANCAR,
            'pcode' => 'GLOVE-99',
            'code' => 'GLOVE-99-BLK-'.sprintf('%03d', $i),
        ]);
        WarehouseItem::create(['warehouse_id' => $pivot->id, 'item_id' => $item->id, 'quantity' => $i]);
    }

    $this->actingAs($this->user)
        ->get(route('reports.warehouse-compare', [
            'warehouse_ids' => [$pivot->id],
            'item_type' => ItemType::ASSET_LANCAR->value,
            'sort' => WarehouseCompareService::SORT_ITEM_CODE,
            'per_page' => 100,
            'page' => 1,
        ]))
        ->assertOk()
        ->assertSee('data-testid="warehouse-compare-pagination"', false)
        ->assertSee('SKUs 1–100 of 105', false);
});

<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemParent;
use App\Support\ItemPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('resolve falls back from sku to parent row', function () {
    $parent = ItemParent::query()->create([
        'parent_key' => '1:AJD:CX90233',
        'price' => 90000,
        'cost' => 45000,
        'reseller_price' => 80000,
        'cost_cnh' => 12,
    ]);

    $group = ItemGroup::query()->create([
        'parent_id' => $parent->id,
        'master' => 'CX90233-23',
        'variant' => '23',
        'name' => 'TEST SHIRT',
        'description' => '',
        'description2' => '',
        'price' => 111111,
    ]);

    $item = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ITEM,
        'price' => 0,
        'cost' => 0,
        'reseller_price' => 0,
        'cost_cnh' => 0,
    ]);

    expect(ItemPricing::resolve($item, 'price'))->toBe(90000.0)
        ->and(ItemPricing::resolve($item, 'cost'))->toBe(45000.0)
        ->and(ItemPricing::resolve($item, 'reseller_price'))->toBe(80000.0)
        ->and(ItemPricing::resolve($item, 'cost_cnh'))->toBe(12.0);

    $item->update(['price' => 95000]);

    expect(ItemPricing::resolve($item->fresh(['group.parent']), 'price'))->toBe(95000.0);

    $item->update(['price' => 0]);

    expect(ItemPricing::resolve($item->fresh(['group.parent']), 'price'))->toBe(90000.0);
});

test('apply colorway scope writes all skus in the colorway', function () {
    $parent = ItemParent::query()->create(['parent_key' => '2:GLOVE-01', 'price' => 80000]);
    $group = ItemGroup::query()->create([
        'parent_id' => $parent->id,
        'master' => 'GLOVE-01',
        'variant' => 'BLUE',
        'name' => 'GLOVE',
        'description' => '',
        'description2' => '',
        'price' => 0,
    ]);
    $first = Item::factory()->create(['group_id' => $group->id, 'price' => 50000, 'type' => ItemType::ASSET_LANCAR]);
    $second = Item::factory()->create(['group_id' => $group->id, 'price' => 60000, 'type' => ItemType::ASSET_LANCAR]);

    ItemPricing::apply($first, 'price', ItemPricing::SCOPE_COLORWAY, 120000);

    $first->refresh();
    $second->refresh();

    expect((float) $first->price)->toBe(120000.0)
        ->and((float) $second->price)->toBe(120000.0)
        ->and(ItemPricing::resolve($first->fresh(['group.parent']), 'price'))->toBe(120000.0);
});

test('formStateForSkuEdit prefills inherited parent amounts as sku scope values', function () {
    $parent = ItemParent::query()->create([
        'parent_key' => '1:AJD:CX90233',
        'price' => 275000,
        'reseller_price' => 250000,
    ]);
    $group = ItemGroup::query()->create([
        'parent_id' => $parent->id,
        'master' => 'CX90233-23',
        'variant' => '23',
        'name' => 'TEST',
        'description' => '',
        'description2' => '',
    ]);

    $item = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ITEM,
        'price' => 0,
        'reseller_price' => 0,
    ]);

    $state = ItemPricing::formStateForSkuEdit($item->fresh(['group.parent']));

    expect($state['price']['scope'])->toBe(ItemPricing::SCOPE_SIZE)
        ->and($state['price']['value'])->toBe(275000.0)
        ->and($state['reseller_price']['value'])->toBe(250000.0);

    $item->update(['price' => 280000]);
    $state = ItemPricing::formStateForSkuEdit($item->fresh(['group.parent']));

    expect($state['price']['value'])->toBe(280000.0)
        ->and($state['reseller_price']['value'])->toBe(250000.0);
});

test('parentGroupHasStoredPricing detects parent row amounts', function () {
    $parent = ItemParent::query()->create(['parent_key' => '1:AJD:CX80023', 'price' => 100000]);

    expect(ItemPricing::parentGroupHasStoredPricing($parent->id))->toBeTrue();
    expect(ItemPricing::parentGroupHasStoredPricing(0))->toBeFalse();
});

test('apply group scope clears sku pricing under parent', function () {
    $typeTag = \App\Models\Tag::factory()->create(['type' => \App\Models\Tag::TYPE_TYPE, 'code' => 'AJD', 'name' => 'Jacket']);

    $parent = ItemParent::query()->create(['parent_key' => '1:AJD:CX90032', 'price' => 0]);
    $group = ItemGroup::query()->create([
        'parent_id' => $parent->id,
        'master' => 'CX90032-01',
        'variant' => '01',
        'name' => 'SHIRT',
        'description' => '',
        'description2' => '',
    ]);
    $item = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX90032-01',
        'price' => 90000,
    ]);
    $item->tags()->attach($typeTag->id);

    ItemPricing::apply($item, 'price', ItemPricing::SCOPE_GROUP, 70000);

    $parent->refresh();
    $item->refresh();

    expect((float) $parent->price)->toBe(70000.0)
        ->and((float) $item->price)->toBe(0.0)
        ->and(ItemPricing::resolve($item->fresh(['group.parent']), 'price'))->toBe(70000.0);
});

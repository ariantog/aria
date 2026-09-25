<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemParentPrice;
use App\Support\ItemPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('resolve falls back size then colorway then group', function () {
    $group = ItemGroup::query()->create([
        'master' => 'CX90233-23',
        'variant' => '23',
        'name' => 'TEST SHIRT',
        'description' => '',
        'description2' => '',
        'price' => 90000,
        'cost' => 45000,
        'reseller_price' => 80000,
        'cost_cnh' => 12,
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

    expect(ItemPricing::resolve($item->fresh(), 'price'))->toBe(95000.0);

    $parentKey = app(\App\Services\Items\ItemIdentityBuilder::class)->itemParentKey($item);
    ItemParentPrice::query()->create([
        'parent_key' => $parentKey,
        'price' => 70000,
    ]);

    $item->update(['price' => 0]);
    $group->update(['price' => 0]);

    expect(ItemPricing::resolve($item->fresh(['group']), 'price'))->toBe(70000.0);
});

test('apply colorway scope clears size overrides in the colorway', function () {
    $group = ItemGroup::query()->create([
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

    $group->refresh();
    $first->refresh();
    $second->refresh();

    expect((float) $group->price)->toBe(120000.0)
        ->and((float) $first->price)->toBe(0.0)
        ->and((float) $second->price)->toBe(0.0)
        ->and(ItemPricing::resolve($first, 'price'))->toBe(120000.0);
});

test('apply group scope clears colorway and size pricing under parent', function () {
    $typeTag = \App\Models\Tag::factory()->create(['type' => \App\Models\Tag::TYPE_TYPE, 'code' => 'AJD', 'name' => 'Jacket']);

    $group = ItemGroup::query()->create([
        'master' => 'CX90032-01',
        'variant' => '01',
        'name' => 'SHIRT',
        'description' => '',
        'description2' => '',
        'price' => 80000,
    ]);
    $item = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX90032-01',
        'price' => 90000,
    ]);
    $item->tags()->attach($typeTag->id);

    ItemPricing::apply($item, 'price', ItemPricing::SCOPE_GROUP, 70000);

    $group->refresh();
    $item->refresh();

    expect((float) $group->price)->toBe(0.0)
        ->and((float) $item->price)->toBe(0.0)
        ->and(ItemPricing::resolve($item, 'price'))->toBe(70000.0);
});

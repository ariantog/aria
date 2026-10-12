<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemParentPrice;
use App\Models\Tag;
use App\Support\ItemProductTitle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('resolveBareTitle falls back sku alias then colorway name then parent product name', function () {
    $group = ItemGroup::query()->create([
        'master' => 'CX90233-23',
        'variant' => '23',
        'name' => 'COLORWAY TITLE',
        'description' => '',
        'description2' => '',
    ]);

    $item = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX90233-23',
        'alias' => '',
    ]);

    expect(ItemProductTitle::resolveBareTitle($item))->toBe('COLORWAY TITLE');

    $parentKey = app(\App\Services\Items\ItemIdentityBuilder::class)->itemParentKey($item);
    ItemParentPrice::query()->create([
        'parent_key' => $parentKey,
        'product_name' => 'PARENT TITLE',
    ]);
    $group->update(['name' => 'CX90233-23']);

    expect(ItemProductTitle::resolveBareTitle($item->fresh(['group'])))->toBe('PARENT TITLE');

    $item->update(['alias' => 'SKU TITLE']);

    expect(ItemProductTitle::resolveBareTitle($item->fresh(['group'])))->toBe('SKU TITLE');
});

test('buildDisplayName appends warna and size for manufactured items', function () {
    $group = ItemGroup::factory()->create(['name' => 'RUNNING SHIRT']);
    $warna = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLUE', 'name' => 'Blue']);
    $size = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'M', 'name' => 'M']);

    $item = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX90233-23',
    ]);
    $item->tags()->sync([$warna->id, $size->id]);

    expect(ItemProductTitle::buildDisplayName($item->fresh(['group', 'tags'])))
        ->toBe('RUNNING SHIRT - BLUE - M');
});

test('syncParentProductName refreshes in-request parent cache for rebuilds', function () {
    $group = ItemGroup::factory()->create([
        'master' => 'CX90233-23',
        'variant' => '23',
        'name' => 'CX90233-23',
    ]);

    $item = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX90233-23',
    ]);

    $parentKey = app(\App\Services\Items\ItemIdentityBuilder::class)->itemParentKey($item);
    ItemParentPrice::query()->create([
        'parent_key' => $parentKey,
        'product_name' => 'OLD PARENT',
    ]);

    expect(ItemProductTitle::parentProductNameForKey($parentKey))->toBe('OLD PARENT');

    ItemProductTitle::syncParentProductName($parentKey, 'NEW PARENT');

    expect(ItemProductTitle::resolveBareTitle($item->fresh(['group'])))->toBe('NEW PARENT');
});

test('resolveBareTitle uses type-parent master for manufactured pcode fallback not colorway pcode', function () {
    $group = ItemGroup::factory()->create([
        'master' => 'CX93023-01',
        'variant' => '01',
        'name' => 'CX93023-01',
    ]);
    $type = Tag::factory()->create(['type' => Tag::TYPE_TYPE, 'code' => 'AJD', 'name' => 'Jacket']);
    $warna = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => '01', 'name' => 'Mint']);

    $item = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX93023-01',
        'code' => 'AJD-CX93023-01-M',
    ]);
    $item->tags()->sync([$type->id, $warna->id]);

    expect(ItemProductTitle::resolveBareTitle($item->fresh(['group', 'tags'])))->toBe('AJD-CX93023');
});

test('resolveBareTitle uses item pcode for asset lancar fallback', function () {
    $group = ItemGroup::factory()->create([
        'master' => 'GLOVE-01',
        'variant' => 'BLUE',
        'name' => 'GLOVE-01',
    ]);
    $warna = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLUE', 'name' => 'Blue']);

    $item = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ASSET_LANCAR,
        'pcode' => 'GLOVE-01',
        'code' => 'GLOVE-01-BLUE-S',
    ]);
    $item->tags()->sync([$warna->id]);

    expect(ItemProductTitle::resolveBareTitle($item->fresh(['group', 'tags'])))->toBe('GLOVE-01');
});

test('buildDisplayNameWithBareTitle matches colorway save naming for manufactured items', function () {
    $group = ItemGroup::factory()->create(['name' => 'RUNNING SHIRT']);
    $warna = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLACK', 'name' => 'Black']);
    $size = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'M', 'name' => 'M']);

    $item = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX90032-06',
    ]);
    $item->tags()->sync([$warna->id, $size->id]);

    expect(ItemProductTitle::buildDisplayNameWithBareTitle($item->fresh(['group', 'tags']), 'Renamed Shirt'))
        ->toBe('RENAMED SHIRT - BLACK - M');
});

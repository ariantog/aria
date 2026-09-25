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

test('buildDisplayName appends warna and size tags', function () {
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

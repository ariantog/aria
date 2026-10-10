<?php

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemParentPrice;
use App\Models\Tag;
use App\Services\Items\ItemIdentityBuilder;
use App\Support\ItemCatalogTitleForm;
use App\Support\ItemProductTitle;

test('colorway title form shows empty stored title when inheriting parent', function () {
    $builder = app(ItemIdentityBuilder::class);
    $typeTag = Tag::factory()->create(['type' => Tag::TYPE_TYPE, 'code' => 'AJD', 'name' => 'Jacket']);
    $sizeTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'S', 'name' => 'Small']);
    $warnaTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLUE', 'name' => 'BLUE']);

    $group = ItemGroup::factory()->create([
        'master' => 'CX90233-23',
        'variant' => '23',
        'name' => 'CX90233-23',
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ITEM,
        'group_id' => $group->id,
        'pcode' => 'CX90233-23',
        'code' => 'AJD-CX90233-23-S',
        'tag_ids' => implode(',', [$typeTag->id, $sizeTag->id, $warnaTag->id]),
    ]);
    $item->tags()->sync([$typeTag->id, $sizeTag->id, $warnaTag->id]);

    $parentKey = $builder->itemParentKey($item);
    ItemProductTitle::syncParentProductName($parentKey, 'PARENT RUNNING SHIRT');

    $state = ItemCatalogTitleForm::forItem($item->fresh(['group', 'tags']), $builder);

    expect($state['stored_title'])->toBe('')
        ->and($state['parent_title'])->toBe('PARENT RUNNING SHIRT')
        ->and($state['effective_title'])->toBe('PARENT RUNNING SHIRT')
        ->and($state['uses_placeholder'])->toBeTrue()
        ->and($state['inherits_parent'])->toBeTrue();
});

test('colorway title form exposes stored custom colorway title', function () {
    $builder = app(ItemIdentityBuilder::class);
    $group = ItemGroup::factory()->create([
        'master' => 'CX90233-23',
        'variant' => '23',
        'name' => 'UNIQUE COLORWAY',
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ITEM,
        'group_id' => $group->id,
        'pcode' => 'CX90233-23',
        'code' => 'AJD-CX90233-23-S',
    ]);

    $state = ItemCatalogTitleForm::forItem($item->fresh(['group']), $builder);

    expect($state['stored_title'])->toBe('UNIQUE COLORWAY')
        ->and($state['effective_title'])->toBe('UNIQUE COLORWAY')
        ->and($state['uses_placeholder'])->toBeFalse();
});

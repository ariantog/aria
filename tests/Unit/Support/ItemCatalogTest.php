<?php

use App\Enums\ItemBrand;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Support\ItemCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('leftover item columns are listed so they can be dropped later', function () {
    expect(ItemCatalog::MIRROR_ITEM_COLUMNS)->toBeTrue()
        ->and(ItemCatalog::LEFTOVER_ITEM_COLUMNS)->toContain('description', 'description2', 'brand', 'genre', 'variant');
});

test('sync writes the group and mirrors leftover item columns', function () {
    $group = ItemGroup::factory()->create([
        'description' => 'OLD GROUP',
        'brand' => ItemBrand::NO_BRAND,
        'genre' => 0,
    ]);
    $item = Item::factory()->create([
        'group_id' => $group->id,
        'description' => 'OLD ITEM',
        'brand' => ItemBrand::NO_BRAND,
        'genre' => 0,
    ]);

    ItemCatalog::applyToGroup($group, [
        'description' => 'mikro motif navy',
        'description2' => 'nb',
        'brand' => ItemBrand::CX9,
        'genre' => 77,
    ]);
    ItemCatalog::mirrorToItem($item, [
        'description' => 'mikro motif navy',
        'description2' => 'nb',
        'brand' => ItemBrand::CX9,
        'genre' => 77,
    ]);
    $item->save();

    $group->refresh();
    $item->refresh();

    expect($group->description)->toBe('MIKRO MOTIF NAVY')
        ->and($group->description2)->toBe('NB')
        ->and($group->brand)->toBe(ItemBrand::CX9)
        ->and($group->genre)->toBe(77)
        ->and($item->description)->toBe('mikro motif navy')
        ->and($item->description2)->toBe('nb')
        ->and($item->brand)->toBe(ItemBrand::CX9)
        ->and($item->genre)->toBe(77);
});

test('catalog reads keep working when leftover item columns are empty', function () {
    $group = ItemGroup::factory()->make([
        'description' => 'GROUP DESC',
        'description2' => 'GROUP NB',
        'brand' => ItemBrand::CX0,
        'genre' => 42,
    ]);
    $item = Item::factory()->make([
        'group_id' => 11,
        'description' => '',
        'description2' => '',
        'brand' => ItemBrand::NO_BRAND,
        'genre' => 0,
    ]);
    $item->setRelation('group', $group);

    expect(ItemCatalog::description($item))->toBe('GROUP DESC')
        ->and(ItemCatalog::description2($item))->toBe('GROUP NB')
        ->and(ItemCatalog::brand($item))->toBe(ItemBrand::CX0)
        ->and(ItemCatalog::genre($item))->toBe(42);
});

test('scanText prefers group catalog and falls back to leftover item text', function () {
    $group = ItemGroup::factory()->make([
        'description' => 'MIKRO MOTIF CAMO HIJAU',
        'description2' => '',
    ]);
    $item = Item::factory()->make([
        'group_id' => 11,
        'description' => 'PUTIH',
        'description2' => '',
    ]);
    $item->setRelation('group', $group);

    expect(ItemCatalog::scanText($item))->toBe('MIKRO MOTIF CAMO HIJAU');

    $group->description = '';
    $item->setRelation('group', $group);

    expect(ItemCatalog::scanText($item))->toBe('PUTIH')
        ->and(ItemCatalog::leftoverDescription($item))->toBe('PUTIH');
});

test('seedEmptyDescriptions fills blank group text and never overwrites catalog', function () {
    $source = ItemGroup::factory()->create([
        'description' => 'MIKRO MOTIF CAMO HIJAU',
        'description2' => 'NOTE',
    ]);
    $emptyGroup = ItemGroup::factory()->create([
        'description' => '',
        'description2' => '',
    ]);
    $item = Item::factory()->create([
        'group_id' => $emptyGroup->id,
        'description' => 'MIKRO MOTIF HIJAU',
        'description2' => 'STALE',
    ]);

    ItemCatalog::seedEmptyDescriptions($emptyGroup, $item, $source);
    $emptyGroup->refresh();

    expect($emptyGroup->description)->toBe('MIKRO MOTIF CAMO HIJAU')
        ->and($emptyGroup->description2)->toBe('NOTE');

    $item->description = 'PUTIH';
    $item->description2 = 'OTHER';
    ItemCatalog::seedEmptyDescriptions($emptyGroup->fresh(), $item, null);
    $emptyGroup->refresh();

    expect($emptyGroup->description)->toBe('MIKRO MOTIF CAMO HIJAU')
        ->and($emptyGroup->description2)->toBe('NOTE');
});

test('brand constraint matches group brand when the item mirror is stale', function () {
    $group = ItemGroup::factory()->create(['brand' => ItemBrand::CX9]);
    $item = Item::factory()->create([
        'group_id' => $group->id,
        'code' => 'AJD-BRAND-GROUP-S',
        'brand' => ItemBrand::NO_BRAND,
    ]);
    $other = Item::factory()->create([
        'code' => 'AJD-BRAND-OTHER-S',
        'brand' => ItemBrand::HJ,
    ]);

    $ids = Item::query()->filterBrand(ItemBrand::CX9->value)->pluck('id');

    expect($ids)->toContain($item->id)
        ->and($ids)->not->toContain($other->id);
});

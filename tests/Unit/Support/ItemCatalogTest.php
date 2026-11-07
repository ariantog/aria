<?php

use App\Enums\ItemBrand;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemParentPrice;
use App\Support\ItemCatalog;
use App\Services\Items\ItemIdentityBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

function attachParentCatalog(ItemGroup $group, Item $item, array $parentAttrs = []): \App\Models\ItemParent
{
    $parent = ensureItemParentForGroup($group, $item);
    if ($parentAttrs !== []) {
        foreach ($parentAttrs as $key => $value) {
            $parent->{$key} = $value;
        }
        $parent->save();
    }
    $group->load('parent');
    $item->load('group.parent');
    ItemCatalog::flushRequestCache();

    return $parent;
}

test('leftover item columns are listed so they can be dropped later', function () {
    expect(ItemCatalog::MIRROR_ITEM_COLUMNS)->toBeTrue()
        ->and(ItemCatalog::LEFTOVER_ITEM_COLUMNS)->toContain('description', 'description2', 'reseller_price', 'brand', 'genre', 'variant');
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

test('catalog description does not inherit parent when colorway text is empty', function () {
    $group = ItemGroup::factory()->create([
        'master' => 'CX90001',
        'variant' => '01',
        'description' => '',
        'description2' => '',
    ]);
    $item = Item::factory()->create([
        'group_id' => $group->id,
        'type' => 1,
        'pcode' => 'CX90001-01',
        'description' => '',
        'description2' => '',
    ]);

    $parentKey = app(ItemIdentityBuilder::class)->itemParentKey($item->fresh(['group', 'tags']));
    ItemParentPrice::query()->create([
        'parent_key' => $parentKey,
        'description' => 'PARENT DESC',
        'description2' => 'PARENT NB',
    ]);
    ItemCatalog::flushRequestCache();

    $item = $item->fresh(['group']);

    expect(ItemCatalog::description($item))->toBe('')
        ->and(ItemCatalog::description2($item))->toBe('');
});

test('catalog reads keep working when leftover item columns are empty', function () {
    $group = ItemGroup::factory()->create();
    $item = Item::factory()->create([
        'group_id' => $group->id,
        'description' => '',
        'description2' => '',
        'brand' => ItemBrand::NO_BRAND,
        'genre' => 0,
    ]);
    attachParentCatalog($group, $item, [
        'description' => 'GROUP DESC',
        'description2' => 'GROUP NB',
        'brand' => ItemBrand::CX0,
        'genre' => 42,
    ]);

    expect(ItemCatalog::description($item))->toBe('GROUP DESC')
        ->and(ItemCatalog::description2($item))->toBe('GROUP NB')
        ->and(ItemCatalog::brand($item))->toBe(ItemBrand::CX0)
        ->and(ItemCatalog::genre($item))->toBe(42);
});

test('scanText prefers item description and falls back to parent catalog', function () {
    $group = ItemGroup::factory()->create();
    $item = Item::factory()->create([
        'group_id' => $group->id,
        'description' => 'PUTIH',
        'description2' => '',
    ]);
    attachParentCatalog($group, $item, [
        'description' => 'MIKRO MOTIF CAMO HIJAU',
        'description2' => '',
    ]);

    expect(ItemCatalog::scanText($item))->toBe('PUTIH');

    $item->description = '';
    $item->save();
    $item->load('group.parent');

    expect(ItemCatalog::scanText($item))->toBe('MIKRO MOTIF CAMO HIJAU')
        ->and(ItemCatalog::leftoverDescription($item))->toBe('');
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
        'description' => '',
        'description2' => '',
    ]);

    ItemCatalog::seedEmptyDescriptions($emptyGroup, $item, $source);
    $item->refresh()->load('group.parent');

    expect($item->catalogDescription())->toBe('MIKRO MOTIF CAMO HIJAU')
        ->and($item->catalogDescription2())->toBe('NOTE');

    $item->description = 'PUTIH';
    $item->description2 = 'OTHER';
    ItemCatalog::seedEmptyDescriptions($emptyGroup->fresh(), $item, null);
    $item->refresh()->load('group.parent');

    expect($item->catalogDescription())->toBe('MIKRO MOTIF CAMO HIJAU')
        ->and($item->catalogDescription2())->toBe('NOTE');
});

test('clearStaleDescriptionMirrorsForColorway clears mirrors but keeps empty and local sku text', function () {
    $group = ItemGroup::factory()->create([
        'description' => 'SHARED DESC',
        'description2' => 'SHARED NB',
    ]);
    $mirrorItem = Item::factory()->create([
        'group_id' => $group->id,
        'description' => 'SHARED DESC',
        'description2' => 'SHARED NB',
    ]);
    $inheritItem = Item::factory()->create([
        'group_id' => $group->id,
        'description' => '',
        'description2' => '',
    ]);
    $localItem = Item::factory()->create([
        'group_id' => $group->id,
        'description' => 'LOCAL ONLY',
        'description2' => 'LOCAL NB',
    ]);

    attachParentCatalog($group, $mirrorItem, [
        'description' => 'SHARED DESC',
        'description2' => 'SHARED NB',
    ]);

    ItemCatalog::applyToGroup($group, [
        'description' => 'UPDATED SHARED DESC',
        'description2' => 'UPDATED SHARED NB',
    ], $mirrorItem);

    ItemCatalog::clearStaleDescriptionMirrorsForColorway($group, [
        'description' => 'SHARED DESC',
        'description2' => 'SHARED NB',
    ]);

    $mirrorItem->refresh();
    $inheritItem->refresh();
    $localItem->refresh();
    $group->refresh();

    expect((string) $mirrorItem->description)->toBe('')
        ->and((string) $mirrorItem->description2)->toBe('')
        ->and(ItemCatalog::description($mirrorItem))->toBe('UPDATED SHARED DESC')
        ->and((string) $inheritItem->description)->toBe('')
        ->and(ItemCatalog::description($inheritItem))->toBe('UPDATED SHARED DESC')
        ->and($localItem->description)->toBe('LOCAL ONLY')
        ->and($localItem->description2)->toBe('LOCAL NB')
        ->and(ItemCatalog::description($localItem))->toBe('LOCAL ONLY');
});

test('syncDescriptionMirrorsForGroup clears mirrored sku text when override is cleared on save', function () {
    $group = ItemGroup::factory()->create();
    $item = Item::factory()->create([
        'group_id' => $group->id,
        'description' => 'FLEECE DARK GREY',
        'description2' => 'OLD NB',
    ]);
    attachParentCatalog($group, $item, [
        'description' => 'FLEECE DARK GREY',
        'description2' => '',
    ]);

    $input = (object) [
        'description' => 'FLEECE DARK GREY',
        'description2' => '',
        'item_description' => '',
        'item_description2' => '',
    ];

    ItemCatalog::syncDescriptionMirrorsForGroup($group, $input, $item);

    $item->refresh();

    expect((string) $item->description)->toBe('')
        ->and((string) $item->description2)->toBe('')
        ->and(ItemCatalog::description($item))->toBe('FLEECE DARK GREY')
        ->and($item->catalogDescription())->toBe('FLEECE DARK GREY');
});

test('clearStaleDescriptionMirrorsForColorway only processes fields included in the save', function () {
    $group = ItemGroup::factory()->create([
        'description' => 'SHARED DESC',
        'description2' => 'SHARED NB',
    ]);
    $item = Item::factory()->create([
        'group_id' => $group->id,
        'description' => 'SHARED DESC',
        'description2' => 'LOCAL NB',
    ]);

    attachParentCatalog($group, $item, [
        'description' => 'SHARED DESC',
        'description2' => 'SHARED NB',
    ]);

    ItemCatalog::applyToGroup($group, [
        'description' => 'NEW SHARED DESC',
    ], $item);

    ItemCatalog::clearStaleDescriptionMirrorsForColorway($group, [
        'description' => 'SHARED DESC',
        'description2' => 'SHARED NB',
    ], ['description']);

    $item->refresh();
    $item->load('group.parent');

    expect((string) $item->description)->toBe('')
        ->and($item->description2)->toBe('LOCAL NB')
        ->and(ItemCatalog::description($item))->toBe('NEW SHARED DESC')
        ->and(ItemCatalog::description2($item))->toBe('LOCAL NB');
});

test('syncDescriptionMirrorsForGroup keeps local sku override when item_description is omitted', function () {
    $group = ItemGroup::factory()->create([
        'description' => 'FLEECE DARK GREY',
        'description2' => '',
    ]);
    $item = Item::factory()->create([
        'group_id' => $group->id,
        'description' => 'PS ABU',
        'description2' => 'OLD NB',
    ]);

    $input = (object) [
        'description' => 'FLEECE DARK GREY',
        'description2' => '',
    ];

    ItemCatalog::syncDescriptionMirrorsForGroup($group, $input, $item);

    $item->refresh();

    expect($item->description)->toBe('PS ABU')
        ->and($item->description2)->toBe('OLD NB')
        ->and(ItemCatalog::description($item))->toBe('PS ABU');
});

test('syncDescriptionMirrorsForGroup keeps local sku NB when item_description2 is omitted', function () {
    $group = ItemGroup::factory()->create();
    $item = Item::factory()->create([
        'group_id' => $group->id,
        'description' => '',
        'description2' => 'SIZE NB',
    ]);
    attachParentCatalog($group, $item, [
        'description' => 'FLEECE DARK GREY',
        'description2' => 'SHARED NB',
    ]);

    $input = (object) [
        'description' => 'FLEECE DARK GREY',
        'description2' => 'UPDATED SHARED NB',
    ];

    ItemCatalog::applyToGroup($group, ['description2' => 'UPDATED SHARED NB'], $item);
    ItemCatalog::syncDescriptionMirrorsForGroup($group, $input, $item, [
        'description' => 'FLEECE DARK GREY',
        'description2' => 'SHARED NB',
    ]);

    $item->refresh();

    expect($item->description2)->toBe('SIZE NB')
        ->and(ItemCatalog::description2($item))->toBe('SIZE NB');
});

test('catalog description prefers parent over mirrored duplicate item text', function () {
    $parent = new \App\Models\ItemParent([
        'description' => 'FLEECE DARK GREY',
        'description2' => 'SHARED NB',
    ]);
    $group = ItemGroup::factory()->make();
    $group->setRelation('parent', $parent);
    $item = Item::factory()->make([
        'group_id' => 11,
        'description' => 'FLEECE DARK GREY',
        'description2' => 'SHARED NB',
    ]);
    $item->setRelation('group', $group);

    expect(ItemCatalog::description($item))->toBe('FLEECE DARK GREY')
        ->and(ItemCatalog::description2($item))->toBe('SHARED NB');
});

test('dedupeItemDescriptionsFromGroup clears mirrored text but keeps local overrides', function () {
    $group = ItemGroup::factory()->create();
    $item = Item::factory()->create([
        'group_id' => $group->id,
        'description' => 'SHARED DESC',
        'description2' => 'LOCAL NB',
    ]);
    ensureItemParentForGroup($group, $item);
    ItemCatalog::applyToGroup($group, [
        'description' => 'SHARED DESC',
        'description2' => 'SHARED NB',
    ], $item);
    $group->load('parent');

    ItemCatalog::dedupeItemDescriptionsFromGroup($item, $group);

    expect((string) $item->description)->toBe('')
        ->and($item->description2)->toBe('LOCAL NB');
});

test('sellPriceForReseller falls back sku, colorway, then normal price', function () {
    $typeTag = \App\Models\Tag::factory()->create([
        'type' => \App\Models\Tag::TYPE_TYPE,
        'code' => 'AJD',
        'name' => 'Jacket',
    ]);
    $group = ItemGroup::factory()->create([
        'master' => 'CX90001-01',
        'variant' => '01',
    ]);
    $item = Item::factory()->create([
        'type' => \App\Enums\ItemType::ITEM,
        'group_id' => $group->id,
        'pcode' => 'CX90001-01',
        'price' => 150000,
        'reseller_price' => 95000,
    ]);
    $item->tags()->attach($typeTag->id);
    attachParentCatalog($group, $item, ['reseller_price' => 120000]);

    expect(ItemCatalog::sellPriceForReseller($item))->toBe(95000.0);

    $item->update(['reseller_price' => 0]);

    expect(ItemCatalog::sellPriceForReseller($item->fresh(['group.parent', 'tags'])))->toBe(120000.0);

    $parent = $group->fresh('parent')->parent;
    $parent->reseller_price = 0;
    $parent->save();
    \App\Support\ItemPricing::flushRequestCache();

    expect(ItemCatalog::sellPriceForReseller($item->fresh(['group.parent', 'tags'])))->toBe(150000.0);
});

test('resellerPrice prefers item override then colorway default', function () {
    $typeTag = \App\Models\Tag::factory()->create([
        'type' => \App\Models\Tag::TYPE_TYPE,
        'code' => 'AJD',
        'name' => 'Jacket',
    ]);
    $group = ItemGroup::factory()->create([
        'master' => 'CX90002-01',
        'variant' => '01',
    ]);
    $item = Item::factory()->create([
        'type' => \App\Enums\ItemType::ITEM,
        'group_id' => $group->id,
        'pcode' => 'CX90002-01',
        'reseller_price' => 95000,
    ]);
    $item->tags()->attach($typeTag->id);
    attachParentCatalog($group, $item, ['reseller_price' => 120000]);

    expect(ItemCatalog::resellerPrice($item))->toBe(95000.0);

    $item->update(['reseller_price' => 0]);

    expect(ItemCatalog::resellerPrice($item->fresh(['group.parent', 'tags'])))->toBe(120000.0);
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

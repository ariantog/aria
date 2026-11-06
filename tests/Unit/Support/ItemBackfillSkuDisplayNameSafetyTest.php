<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Support\ItemBackfillSkuDisplayNameSafety;

it('allows overwrite when display name is empty', function () {
    $item = new Item(['type' => ItemType::ITEM, 'name' => '  ']);

    expect(ItemBackfillSkuDisplayNameSafety::mayOverwriteDisplayName($item, ''))->toBeTrue()
        ->and(ItemBackfillSkuDisplayNameSafety::mayOverwriteDisplayName($item, '   '))->toBeTrue();
});

it('allows overwrite when the first segment matches a validated pcode', function () {
    $item = new Item(['type' => ItemType::ITEM, 'name' => 'CX90233-23 - M']);
    expect(ItemBackfillSkuDisplayNameSafety::mayOverwriteDisplayName($item, $item->name))->toBeTrue();
});

it('allows overwrite for manufactured master-only leading segment', function () {
    $item = new Item(['type' => ItemType::ITEM, 'name' => 'CX90032 - M']);
    expect(ItemBackfillSkuDisplayNameSafety::mayOverwriteDisplayName($item, $item->name))->toBeTrue();
});

it('allows overwrite for TYPE-hyphen-pcode style leading segment', function () {
    $item = new Item(['type' => ItemType::ITEM, 'name' => 'AJD-CX90233 - M']);
    expect(ItemBackfillSkuDisplayNameSafety::mayOverwriteDisplayName($item, $item->name))->toBeTrue();
});

it('allows overwrite when leading segment is TYPE plus full colorway pcode', function () {
    $item = new Item(['type' => ItemType::ITEM, 'name' => 'AJD-CX90032-01 - M']);
    expect(ItemBackfillSkuDisplayNameSafety::mayOverwriteDisplayName($item, $item->name))->toBeTrue();
});

it('always allows overwrite for asset lancar (gate skipped)', function () {
    $item = new Item(['type' => ItemType::ASSET_LANCAR, 'name' => 'GLOVE-01 - BLACK']);
    expect(ItemBackfillSkuDisplayNameSafety::mayOverwriteDisplayName($item, $item->name))->toBeTrue();
});

it('allows asset lancar overwrite even when the first segment looks like a marketing title', function () {
    $item = new Item(['type' => ItemType::ASSET_LANCAR, 'name' => 'CUSTOM TITLE - BLACK']);
    expect(ItemBackfillSkuDisplayNameSafety::mayOverwriteDisplayName($item, $item->name))->toBeTrue();
});

it('blocks overwrite when the first segment is a custom marketing title', function () {
    $item = new Item(['type' => ItemType::ITEM, 'name' => 'RUNNING SHIRT - MINT - M']);
    expect(ItemBackfillSkuDisplayNameSafety::mayOverwriteDisplayName($item, $item->name))->toBeFalse();
});

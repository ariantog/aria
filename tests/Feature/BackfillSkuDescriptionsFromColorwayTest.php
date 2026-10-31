<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use Illuminate\Support\Facades\Artisan;

test('backfill sku descriptions from colorway dry-run does not write', function () {
    $group = ItemGroup::factory()->create([
        'description' => 'SHARED DESC',
        'description2' => 'SHARED NB',
    ]);
    $item = Item::factory()->create([
        'type' => ItemType::ITEM,
        'group_id' => $group->id,
        'description' => '',
        'description2' => '',
    ]);

    Artisan::call('app:backfill-sku-descriptions-from-colorway', [
        '--group-id' => $group->id,
    ]);

    $item->refresh();

    expect(trim((string) $item->description))->toBe('')
        ->and(trim((string) $item->description2))->toBe('');
});

test('backfill sku descriptions from colorway copies only empty sku fields', function () {
    $group = ItemGroup::factory()->create([
        'description' => 'SHARED DESC',
        'description2' => 'SHARED NB',
    ]);
    $emptySku = Item::factory()->create([
        'type' => ItemType::ITEM,
        'group_id' => $group->id,
        'code' => 'AJD-BF-01-S',
        'description' => '',
        'description2' => '',
    ]);
    $overrideSku = Item::factory()->create([
        'type' => ItemType::ITEM,
        'group_id' => $group->id,
        'code' => 'AJD-BF-01-M',
        'description' => 'SIZE M ONLY',
        'description2' => '',
    ]);

    Artisan::call('app:backfill-sku-descriptions-from-colorway', [
        '--execute' => true,
        '--group-id' => $group->id,
    ]);

    $emptySku->refresh();
    $overrideSku->refresh();
    $group->refresh();

    expect($emptySku->description)->toBe('SHARED DESC')
        ->and($emptySku->description2)->toBe('SHARED NB')
        ->and($overrideSku->description)->toBe('SIZE M ONLY')
        ->and($overrideSku->description2)->toBe('SHARED NB')
        ->and($group->description)->toBe('SHARED DESC')
        ->and($group->description2)->toBe('SHARED NB');
});

<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\Tag;

test('normalize identity dry run reports name changes without writing', function () {
    $warnaTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLUE', 'name' => 'BLUE']);
    $sizeTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'S', 'name' => 'S']);

    $group = ItemGroup::factory()->create([
        'name' => 'CX90233-23',
        'master' => 'CX90233',
        'variant' => '23',
    ]);

    $item = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX90233-23',
        'code' => 'AJD-CX90233-23-S',
        'name' => 'OLD NAME',
    ]);

    $item->tags()->sync([$warnaTag->id, $sizeTag->id]);

    $this->artisan('items:normalize-identity', ['--dry-run' => true])
        ->assertSuccessful();

    expect($item->fresh()->name)->toBe('OLD NAME');
});

test('normalize identity regenerates item display names', function () {
    $warnaTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLUE', 'name' => 'BLUE']);
    $sizeTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'S', 'name' => 'S']);

    $group = ItemGroup::factory()->create([
        'name' => 'SLASH RUNNING SHIRT',
        'master' => 'CX90233',
        'variant' => '23',
    ]);

    $item = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX90233-23',
        'code' => 'AJD-CX90233-23-S',
        'name' => 'WRONG NAME',
    ]);

    $item->tags()->sync([$warnaTag->id, $sizeTag->id]);

    $this->artisan('items:normalize-identity')
        ->assertSuccessful();

    expect($item->fresh()->name)->toBe('SLASH RUNNING SHIRT - BLUE - S');
});

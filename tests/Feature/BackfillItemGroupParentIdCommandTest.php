<?php

use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemParent;
use App\Models\Tag;
use App\Enums\ItemType;

it('copies legacy group catalog when parent_id is already linked', function () {
    $typeTag = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ITEM->value,
        'code' => 'AJD',
        'name' => 'Jacket',
    ]);
    $sizeTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'S', 'name' => 'S']);
    $warnaTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLUE', 'name' => 'BLUE']);
    $jahitTag = Tag::factory()->create(['type' => Tag::TYPE_JAHIT, 'code' => 'J1', 'name' => 'J1']);

    $group = ItemGroup::factory()->create([
        'master' => 'CX93249-03',
        'variant' => '03',
        'name' => 'Legacy Product Title',
        'description' => 'Legacy group desc',
        'url' => 'https://legacy.example/item',
    ]);
    $item = Item::factory()->create([
        'group_id' => $group->id,
        'pcode' => 'CX93249-03',
        'code' => 'AJD-CX93249-03-S',
    ]);
    $item->tags()->attach([$typeTag->id, $sizeTag->id, $warnaTag->id, $jahitTag->id]);

    $parent = ensureItemParentForGroup($group, $item);
    expect($group->fresh()->parent_id)->toBe($parent->id);

    $parent->description = '';
    $parent->url = '';
    $parent->product_name = '';
    $parent->save();

    $this->artisan('items:backfill-group-parent-id', ['--copy-catalog' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('catalog parents updated: 1');

    $parent->refresh();
    expect($parent->description)->toBe('LEGACY GROUP DESC');
    expect($parent->url)->toBe('https://legacy.example/item');
    expect($parent->product_name)->toBe('LEGACY PRODUCT TITLE');
});

it('reports already-linked groups when nothing needs parent_id', function () {
    $group = ItemGroup::factory()->create(['parent_id' => 0]);
    Item::factory()->create(['group_id' => $group->id]);

    $this->artisan('items:backfill-group-parent-id', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Groups needing parent_id link: 1');
});

<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\Tag;
use App\Models\User;
use App\Services\Items\ItemGroupHierarchyService;
use App\Services\Items\ItemIdentityBuilder;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->builder = app(ItemIdentityBuilder::class);
    $this->hierarchy = app(ItemGroupHierarchyService::class);

    $this->typeTag = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ITEM->value,
        'code' => 'AJD',
        'name' => 'Jacket',
    ]);

    $this->sizeS = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'S', 'name' => 'S']);
    $this->sizeM = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'M', 'name' => 'M']);
    $this->pinkTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'PINK', 'name' => 'PINK']);
    $this->blackTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLACK', 'name' => 'BLACK']);
});

it('lists parent groups by type and master pcode', function () {
    $pinkGroup = ItemGroup::factory()->create(['master' => 'CX93024', 'variant' => '05', 'name' => 'RUNNING SHIRT']);
    $blackGroup = ItemGroup::factory()->create(['master' => 'CX93024', 'variant' => '06', 'name' => 'RUNNING SHIRT']);

    $pinkS = Item::factory()->create([
        'group_id' => $pinkGroup->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX93024-05',
        'code' => 'AJD-CX93024-05-S',
    ]);
    $pinkS->tags()->attach([$this->typeTag->id, $this->pinkTag->id, $this->sizeS->id]);

    $blackM = Item::factory()->create([
        'group_id' => $blackGroup->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX93024-06',
        'code' => 'AJD-CX93024-06-M',
    ]);
    $blackM->tags()->attach([$this->typeTag->id, $this->blackTag->id, $this->sizeM->id]);

    $parents = $this->hierarchy->paginateParents([], 50);

    expect($parents->total())->toBe(1);
    expect($parents->first()['label'])->toBe('AJD CX93024');
});

it('renders parent detail with color sections and size rows', function () {
    $pinkGroup = ItemGroup::factory()->create(['master' => 'CX93024', 'variant' => '05', 'name' => 'RUNNING SHIRT']);

    $pinkS = Item::factory()->create([
        'group_id' => $pinkGroup->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX93024-05',
        'code' => 'AJD-CX93024-05-S',
        'name' => 'RUNNING SHIRT - PINK - S',
    ]);
    $pinkS->tags()->attach([$this->typeTag->id, $this->pinkTag->id, $this->sizeS->id]);

    $parentKey = $this->builder->itemParentKey($pinkS->load('tags', 'group'));
    $detail = $this->hierarchy->parentDetail($parentKey, fetchJubelio: false);

    expect($detail['label'])->toBe('AJD CX93024');
    expect($detail['colors'])->toHaveCount(1);
    expect($detail['colors'][0]['code'])->toBe('PINK');
    expect($detail['colors'][0]['name'])->toBe('PINK');
    expect($detail['colors'][0]['size_rows'])->toHaveCount(1);
    expect($detail['colors'][0]['size_rows'][0]['size'])->toBe('S');
    expect($detail['warehouse_breakdown'])->toBeArray();
    expect($detail['colors'][0]['warehouse_breakdown'])->toBeArray();
});

it('renders group list and parent detail pages', function () {
    $group = ItemGroup::factory()->create(['master' => 'GLOVE-01', 'variant' => 'BLACK', 'name' => 'BOXING GLOVE']);

    $item = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ASSET_LANCAR,
        'pcode' => 'GLOVE-01',
        'code' => 'GLOVE-01-BLACK-S',
    ]);
    $item->tags()->attach([$this->blackTag->id, $this->sizeS->id]);

    $slug = $this->builder->parentKeyToSlug($this->builder->itemParentKey($item->load('tags', 'group')));

    $this->actingAs($this->user)
        ->get(route('items.group'))
        ->assertOk()
        ->assertSee('GLOVE-01', false);

    $this->actingAs($this->user)
        ->get(route('items.group-parent-detail', $slug))
        ->assertOk()
        ->assertSee('BLACK', false)
        ->assertSee('per warehouse', false)
        ->assertSee('all channels', false)
        ->assertSee('How to read quantities', false);
});

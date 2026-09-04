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

it('paginates parent groups without breaking the count query', function () {
    foreach (['05', '06'] as $suffix) {
        $group = ItemGroup::factory()->create([
            'master' => 'CX94001-'.$suffix,
            'variant' => $suffix,
            'name' => 'PAGINATED SHIRT',
        ]);

        $item = Item::factory()->create([
            'group_id' => $group->id,
            'type' => ItemType::ITEM,
            'pcode' => 'CX94001-'.$suffix,
            'code' => 'AJD-CX94001-'.$suffix.'-S',
        ]);
        $item->tags()->attach([$this->typeTag->id, $this->pinkTag->id, $this->sizeS->id]);
    }

    $assetGroup = ItemGroup::factory()->create(['master' => 'GLOVE-02', 'variant' => 'RED', 'name' => 'GLOVE']);
    $asset = Item::factory()->create([
        'group_id' => $assetGroup->id,
        'type' => ItemType::ASSET_LANCAR,
        'pcode' => 'GLOVE-02',
        'code' => 'GLOVE-02-RED-S',
    ]);
    $asset->tags()->attach([$this->pinkTag->id, $this->sizeS->id]);

    $pageOne = $this->hierarchy->paginateParents([], 1);

    expect($pageOne->total())->toBe(2)
        ->and($pageOne->count())->toBe(1);
});

it('lists parent groups newest item_group id first', function () {
    $olderGroup = ItemGroup::factory()->create(['master' => 'CX95001', 'variant' => '01', 'name' => 'OLDER']);
    $olderItem = Item::factory()->create([
        'group_id' => $olderGroup->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX95001-01',
        'code' => 'AJD-CX95001-01-S',
    ]);
    $olderItem->tags()->attach([$this->typeTag->id, $this->pinkTag->id, $this->sizeS->id]);

    $newerGroup = ItemGroup::factory()->create(['master' => 'CX95002', 'variant' => '02', 'name' => 'NEWER']);
    $newerItem = Item::factory()->create([
        'group_id' => $newerGroup->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX95002-02',
        'code' => 'AJD-CX95002-02-S',
    ]);
    $newerItem->tags()->attach([$this->typeTag->id, $this->blackTag->id, $this->sizeS->id]);

    $parents = $this->hierarchy->paginateParents([], 50);

    expect($parents->pluck('label')->all())->toBe(['AJD CX95002', 'AJD CX95001']);
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
    expect($detail['warehouse_names'])->toBeArray();
    expect($detail['colors'][0]['warehouse_breakdown'])->toBeArray();
});

it('builds export payload with warehouse columns per sku', function () {
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
    $payload = $this->hierarchy->exportPayload($parentKey);

    expect($payload)->not->toBeNull();
    expect($payload['rows'])->toHaveCount(1);
    expect($payload['rows'][0]['item_code'])->toBe('AJD-CX93024-05-S');
    expect($payload['rows'][0]['color_code'])->toBe('PINK');
    expect($payload['warehouse_names'])->toBeArray();
});

it('parent detail finds leftover slash-master groups under the canonical CX00122 page', function () {
    $group = ItemGroup::factory()->create([
        'master' => 'CX00122/03',
        'variant' => '',
        'name' => 'CX00122/03',
    ]);
    $item = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX00122/03',
        'code' => 'AJD-CX00122-03-S',
    ]);
    $item->tags()->attach([$this->typeTag->id, $this->pinkTag->id, $this->sizeS->id]);

    $detail = $this->hierarchy->parentDetail('1:AJD:CX00122', fetchJubelio: false);
    $slashDetail = $this->hierarchy->parentDetail('1:AJD:CX00122/03', fetchJubelio: false);

    expect($detail)->not->toBeNull()
        ->and($slashDetail)->not->toBeNull()
        ->and(collect($detail['colors'])->pluck('group_id'))->toContain($group->id)
        ->and($this->builder->itemParentKey($item->fresh(['tags', 'group'])))->toBe('1:AJD:CX00122');
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
        ->assertSee('Warehouse focus', false)
        ->assertSee('Total only', false)
        ->assertSee('Export Excel', false)
        ->assertSee('all channels', false)
        ->assertSee('How to read quantities', false);
});

it('exports parent group stock to excel', function () {
    $group = ItemGroup::factory()->create(['master' => 'GLOVE-01', 'variant' => 'BLACK', 'name' => 'BOXING GLOVE']);

    $item = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ASSET_LANCAR,
        'pcode' => 'GLOVE-01',
        'code' => 'GLOVE-01-BLACK-S',
    ]);
    $item->tags()->attach([$this->blackTag->id, $this->sizeS->id]);

    $slug = $this->builder->parentKeyToSlug($this->builder->itemParentKey($item->load('tags', 'group')));

    $response = $this->actingAs($this->user)
        ->get(route('items.group-parent-export', $slug));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml.sheet');
});

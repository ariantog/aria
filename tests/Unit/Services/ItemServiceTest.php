<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\Tag;
use App\Services\ImageService;
use App\Services\InventoryService;
use App\Services\Items\ItemIdentityBuilder;
use App\Services\ItemService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->itemService = new ItemService(
        app(ImageService::class),
        new InventoryService,
        new ItemIdentityBuilder,
    );

    $this->typeTag = Tag::factory()->create(['type' => Tag::TYPE_TYPE, 'code' => 'AJD', 'name' => 'Jacket']);
    $this->sizeTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'S', 'name' => 'Small']);
    $this->warnaTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLUE', 'name' => 'BLUE']);
    $this->jahitTag = Tag::factory()->create(['type' => Tag::TYPE_JAHIT, 'code' => 'J1', 'name' => 'Jahit1']);
});

test('it throws exception for invalid pcode', function () {
    $input = (object) [
        'pcode' => 'INVALID',
        'type' => ItemType::ITEM->value,
        'product_name' => 'Test Product',
    ];

    $this->itemService->create($input, [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ]);
})->throws(Exception::class);

test('it creates manufactured item without product name using pcode placeholder', function () {
    $input = (object) [
        'pcode' => 'CX93249-03',
        'type' => ItemType::ITEM->value,
        'price' => 100000,
    ];

    $tags = [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ];

    expect($this->itemService->create($input, $tags))->toBeTrue();

    $this->assertDatabaseHas('item_group', [
        'name' => 'CX93249-03',
        'master' => 'CX93249-03',
        'variant' => '03',
    ]);

    $this->assertDatabaseHas('items', [
        'pcode' => 'CX93249-03',
        'code' => 'AJD-CX93249-03-S',
        'name' => 'CX93249-03 - BLUE - S',
    ]);
});

test('it renames group product name and syncs all item display names', function () {
    $mediumTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'M', 'name' => 'Medium']);

    $input = (object) [
        'pcode' => 'CX90233-23',
        'type' => ItemType::ITEM->value,
        'price' => 100000,
    ];

    $tags = [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id, $mediumTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ];

    $this->itemService->create($input, $tags);

    $group = ItemGroup::where('master', 'CX90233-23')->where('variant', '23')->firstOrFail();

    $this->itemService->renameGroupProductName($group, 'Slash Running Shirt');

    $this->assertDatabaseHas('item_group', ['id' => $group->id, 'name' => 'SLASH RUNNING SHIRT']);
    $this->assertDatabaseHas('items', ['code' => 'AJD-CX90233-23-S', 'name' => 'SLASH RUNNING SHIRT - BLUE - S']);
    $this->assertDatabaseHas('items', ['code' => 'AJD-CX90233-23-M', 'name' => 'SLASH RUNNING SHIRT - BLUE - M']);
});

test('it propagates product name change from item update to all sizes in group', function () {
    $mediumTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'M', 'name' => 'Medium']);

    $input = (object) [
        'pcode' => 'CX90233-23',
        'type' => ItemType::ITEM->value,
        'price' => 100000,
    ];

    $tags = [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id, $mediumTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ];

    $this->itemService->create($input, $tags);

    $item = Item::where('code', 'AJD-CX90233-23-S')->firstOrFail();

    $this->itemService->update($item->id, (object) [
        'pcode' => 'CX90233-23',
        'type' => ItemType::ITEM->value,
        'product_name' => 'Slash Running Shirt',
        'price' => 100000,
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ]);

    $this->assertDatabaseHas('items', ['code' => 'AJD-CX90233-23-S', 'name' => 'SLASH RUNNING SHIRT - BLUE - S']);
    $this->assertDatabaseHas('items', ['code' => 'AJD-CX90233-23-M', 'name' => 'SLASH RUNNING SHIRT - BLUE - M']);
});

test('it creates manufactured item with unified code and display name', function () {
    $input = (object) [
        'pcode' => 'CX90233-23',
        'type' => ItemType::ITEM->value,
        'price' => 100000,
        'description' => 'Test Item',
        'product_name' => 'Slash Running Shirt',
    ];

    $tags = [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ];

    expect($this->itemService->create($input, $tags))->toBeTrue();

    $this->assertDatabaseHas('item_group', [
        'name' => 'SLASH RUNNING SHIRT',
        'master' => 'CX90233-23',
        'variant' => '23',
    ]);

    $this->assertDatabaseHas('items', [
        'pcode' => 'CX90233-23',
        'code' => 'AJD-CX90233-23-S',
        'name' => 'SLASH RUNNING SHIRT - BLUE - S',
    ]);
});

test('it saves image when provided', function () {
    Storage::fake('public');
    $file = UploadedFile::fake()->image('test.jpg');

    $input = (object) [
        'pcode' => 'CX90233-24',
        'type' => ItemType::ITEM->value,
        'price' => 100000,
        'product_name' => 'Slash Running Shirt',
    ];

    $tags = [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ];

    $this->itemService->create($input, $tags, $file);

    expect(ItemGroup::where('master', 'CX90233-24')->where('variant', '24')->exists())->toBeTrue();
});

test('it creates asset lancar variants with cartesian color and size', function () {
    $pinkTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'PINK', 'name' => 'PINK']);
    $mediumTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'M', 'name' => 'Medium']);
    $assetType = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ASSET_LANCAR->value,
        'code' => 'GLOVE',
        'name' => 'Glove',
    ]);

    $input = (object) [
        'pcode' => 'GLOVE-01',
        'type' => ItemType::ASSET_LANCAR->value,
        'product_name' => 'Boxing Gloves',
        'price' => 5000000,
        'cost' => 3000000,
    ];

    $tags = [
        'types' => [$assetType->id],
        'sizes' => [$this->sizeTag->id, $mediumTag->id],
        'warna' => [$this->warnaTag->id, $pinkTag->id],
        'jahit' => [],
    ];

    expect($this->itemService->create($input, $tags))->toBeTrue();

    $this->assertDatabaseHas('items', [
        'code' => 'GLOVE-01-BLUE-S',
        'name' => 'BOXING GLOVES - BLUE - S',
    ]);

    $this->assertDatabaseHas('items', [
        'code' => 'GLOVE-01-PINK-M',
        'name' => 'BOXING GLOVES - PINK - M',
    ]);

    expect(Item::where('code', 'like', 'GLOVE-01-%')->count())->toBe(4);
});

test('it rewrites asset lancar pcode prefix from the selected type tag on create', function () {
    $assetType = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ASSET_LANCAR->value,
        'code' => 'GLOVE',
        'name' => 'Glove',
    ]);

    $input = (object) [
        'pcode' => 'gloves-03',
        'type' => ItemType::ASSET_LANCAR->value,
        'product_name' => 'Boxing Gloves',
        'price' => 500000,
        'cost' => 300000,
    ];

    $this->itemService->create($input, [
        'types' => [$assetType->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [],
    ]);

    $this->assertDatabaseHas('items', [
        'code' => 'GLOVE-03-BLUE-S',
        'pcode' => 'GLOVE-03',
    ]);
});

test('it keeps three-segment asset pcodes when rewriting the type prefix on create', function () {
    $assetType = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ASSET_LANCAR->value,
        'code' => 'BAG',
        'name' => 'Bag',
    ]);

    $input = (object) [
        'pcode' => 'bag-16-03',
        'type' => ItemType::ASSET_LANCAR->value,
        'product_name' => 'Duffel Bag',
        'price' => 250000,
        'cost' => 150000,
    ];

    $this->itemService->create($input, [
        'types' => [$assetType->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [],
    ]);

    $this->assertDatabaseHas('items', [
        'code' => 'BAG-16-03-BLUE-S',
        'pcode' => 'BAG-16-03',
    ]);
});

test('it does not rewrite manufactured item pcode from the type tag on create', function () {
    $input = (object) [
        'pcode' => 'CX90233-23',
        'type' => ItemType::ITEM->value,
        'price' => 150000,
    ];

    $this->itemService->create($input, [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ]);

    $this->assertDatabaseHas('items', [
        'code' => 'AJD-CX90233-23-S',
        'pcode' => 'CX90233-23',
    ]);
});

test('it rejects duplicate sku on create', function () {
    Item::factory()->create(['code' => 'AJD-CX90233-23-S']);

    $input = (object) [
        'pcode' => 'CX90233-23',
        'type' => ItemType::ITEM->value,
        'product_name' => 'Slash Running Shirt',
    ];

    $tags = [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ];

    $this->itemService->create($input, $tags);
})->throws(Exception::class, 'SKU already exists');

test('it preserves legacy_code when updating manufactured item to new sku format', function () {
    $item = Item::factory()->create([
        'type' => ItemType::ITEM,
        'code' => 'OLD-JUBELIO-SKU',
        'legacy_code' => 'OLD-JUBELIO-SKU',
        'pcode' => 'CX90233-23',
        'group_id' => null,
    ]);
    $item->tags()->sync([$this->typeTag->id, $this->sizeTag->id, $this->warnaTag->id]);

    $this->itemService->update($item->id, (object) [
        'pcode' => 'CX90233-23',
        'type' => ItemType::ITEM->value,
        'product_name' => 'Slash Running Shirt',
        'price' => 100000,
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => $this->warnaTag->id,
        'jahit' => [$this->jahitTag->id],
    ]);

    $item->refresh();

    expect($item->code)->toBe('AJD-CX90233-23-S')
        ->and($item->legacy_code)->toBe('OLD-JUBELIO-SKU');
});

test('it snapshots legacy_code from old code on first manufactured item identity update', function () {
    $item = Item::factory()->create([
        'type' => ItemType::ITEM,
        'code' => 'LEGACY-SKU-BEFORE-MIGRATION',
        'legacy_code' => null,
        'pcode' => 'CX90233-23',
        'group_id' => null,
    ]);
    $item->tags()->sync([$this->typeTag->id, $this->sizeTag->id, $this->warnaTag->id]);

    $this->itemService->update($item->id, (object) [
        'pcode' => 'CX90233-23',
        'type' => ItemType::ITEM->value,
        'product_name' => 'Slash Running Shirt',
        'price' => 100000,
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => $this->warnaTag->id,
        'jahit' => [$this->jahitTag->id],
    ]);

    $item->refresh();

    expect($item->code)->toBe('AJD-CX90233-23-S')
        ->and($item->legacy_code)->toBe('LEGACY-SKU-BEFORE-MIGRATION');
});

test('it snapshots legacy_code when updating asset lancar to a new sku', function () {
    $navyTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'NAVY', 'name' => 'NAVY']);
    $assetType = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ASSET_LANCAR->value,
        'code' => 'GLOVE',
        'name' => 'Glove',
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'group_id' => null,
        'code' => 'GLOVE-01-BLUE-S',
        'legacy_code' => null,
        'pcode' => 'GLOVE-01',
        'name' => 'BOXING GLOVES - BLUE - S',
        'price' => 500000,
        'cost' => 300000,
    ]);
    $item->tags()->sync([$assetType->id, $this->sizeTag->id, $this->warnaTag->id]);

    $this->itemService->update($item->id, (object) [
        'pcode' => 'GLOVE-01',
        'type' => ItemType::ASSET_LANCAR->value,
        'product_name' => 'Boxing Gloves',
        'price' => 500000,
        'cost' => 300000,
    ], [
        'types' => [$assetType->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => $navyTag->id,
    ]);

    $item->refresh();

    expect($item->code)->toBe('GLOVE-01-NAVY-S')
        ->and($item->legacy_code)->toBe('GLOVE-01-BLUE-S');
});

test('it does not overwrite an existing asset lancar legacy_code on edit', function () {
    $navyTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'NAVY', 'name' => 'NAVY']);
    $assetType = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ASSET_LANCAR->value,
        'code' => 'GLOVE',
        'name' => 'Glove',
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'group_id' => null,
        'code' => 'GLOVE-01-BLUE-S',
        'legacy_code' => 'OLD-GLOVE-JUBELIO',
        'pcode' => 'GLOVE-01',
        'name' => 'BOXING GLOVES - BLUE - S',
        'price' => 500000,
        'cost' => 300000,
    ]);
    $item->tags()->sync([$assetType->id, $this->sizeTag->id, $this->warnaTag->id]);

    $this->itemService->update($item->id, (object) [
        'pcode' => 'GLOVE-01',
        'type' => ItemType::ASSET_LANCAR->value,
        'product_name' => 'Boxing Gloves',
        'price' => 500000,
        'cost' => 300000,
    ], [
        'types' => [$assetType->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => $navyTag->id,
    ]);

    $item->refresh();

    expect($item->code)->toBe('GLOVE-01-NAVY-S')
        ->and($item->legacy_code)->toBe('OLD-GLOVE-JUBELIO');
});

test('it auto creates parent group when updating legacy asset lancar without group', function () {
    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'group_id' => null,
        'code' => 'GLOVE-01-BLUE-S',
        'pcode' => 'GLOVE-01',
        'name' => 'BOXING GLOVES - BLUE - S',
        'price' => 500000,
        'cost' => 300000,
    ]);
    $item->tags()->sync([$this->sizeTag->id, $this->warnaTag->id]);

    $this->itemService->update($item->id, (object) [
        'pcode' => 'GLOVE-01',
        'type' => ItemType::ASSET_LANCAR->value,
        'price' => 550000,
        'cost' => 320000,
    ], [
        'sizes' => [$this->sizeTag->id],
        'warna' => $this->warnaTag->id,
    ]);

    $item->refresh();

    expect($item->group_id)->not->toBeNull();
    $this->assertDatabaseHas('item_group', [
        'id' => $item->group_id,
        'master' => 'GLOVE-01',
        'variant' => 'BLUE',
        'name' => 'BOXING GLOVES',
    ]);
    expect($item->code)->toBe('GLOVE-01-BLUE-S')
        ->and($item->price)->toBe('550000.00');
});

test('it loads the product name from an existing pcode when the form leaves it blank', function () {
    $assetType = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ASSET_LANCAR->value,
        'code' => 'ELBOW',
        'name' => 'Elbow',
    ]);
    $blackWhite = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'NAVY', 'name' => 'NAVY']);

    $existing = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'pcode' => 'ELBOWSUPPORT-02',
        'code' => 'ELBOWSUPPORT-02-BLACKWHITE',
        'name' => 'ELBOW STRAP - BLACKWHITE',
        'group_id' => ItemGroup::factory()->create([
            'master' => 'ELBOWSUPPORT-02',
            'variant' => 'BLACKWHITE',
            'name' => 'ELBOW STRAP - BLACKWHITE (ELBOWSUPPORT-02)',
        ])->id,
    ]);

    expect($this->itemService->productNameForPcode(ItemType::ASSET_LANCAR, 'ELBOWSUPPORT-02'))
        ->toBe('ELBOW STRAP');

    $this->itemService->create((object) [
        'pcode' => 'ELBOWSUPPORT-02',
        'type' => ItemType::ASSET_LANCAR->value,
        'product_name' => '',
        'price' => 100000,
        'cost' => 50000,
    ], [
        'types' => [$assetType->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => [$blackWhite->id],
    ]);

    $this->assertDatabaseHas('items', [
        'code' => 'ELBOWSUPPORT-02-NAVY-S',
        'name' => 'ELBOW STRAP - NAVY - S',
    ]);

    expect($existing->fresh()->name)->toBe('ELBOW STRAP - BLACKWHITE');
});

test('it does not append color twice when the submitted name is a unique stored group name', function () {
    $assetType = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ASSET_LANCAR->value,
        'code' => 'ELBOW',
        'name' => 'Elbow',
    ]);
    $blackWhite = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLACKWHITE', 'name' => 'BLACKWHITE']);
    $allSize = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'AS', 'name' => 'All Size']);

    $group = ItemGroup::factory()->create([
        'master' => 'ELBOWSUPPORT-02',
        'variant' => 'BLACKWHITE',
        'name' => 'ELBOW STRAP - BLACKWHITE (ELBOWSUPPORT-02)',
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'group_id' => $group->id,
        'pcode' => 'ELBOWSUPPORT-02',
        'code' => 'ELBOWSUPPORT-02-BLACKWHITE',
        'name' => 'ELBOW STRAP - BLACKWHITE',
        'cost' => 50000,
    ]);
    $item->tags()->sync([$assetType->id, $blackWhite->id, $allSize->id]);

    $this->itemService->update($item->id, (object) [
        'pcode' => 'ELBOWSUPPORT-02',
        'type' => ItemType::ASSET_LANCAR->value,
        'product_name' => 'ELBOW STRAP - BLACKWHITE (ELBOWSUPPORT-02)',
        'price' => 100000,
        'cost' => 50000,
    ], [
        'types' => [$assetType->id],
        'sizes' => [$allSize->id],
        'warna' => $blackWhite->id,
    ]);

    $item->refresh();

    expect($item->name)->toBe('ELBOW STRAP - BLACKWHITE')
        ->and($item->name)->not->toBe('ELBOW STRAP - BLACKWHITE (ELBOWSUPPORT-02) - BLACKWHITE');
});

test('it stores brand and genre on the item group and mirrors them on each size', function () {
    $mediumTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'M', 'name' => 'Medium']);

    $this->itemService->create((object) [
        'pcode' => 'CX90233-23',
        'type' => ItemType::ITEM->value,
        'price' => 100000,
        'description' => 'Mikro motif navy',
        'description2' => 'Group nb',
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id, $mediumTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ]);

    $group = ItemGroup::where('master', 'CX90233-23')->where('variant', '23')->firstOrFail();

    expect($group->name)->toBe('CX90233-23')
        ->and($group->brand)->toBe(\App\Enums\ItemBrand::CX9)
        ->and($group->genre)->toBe($this->typeTag->id)
        ->and($group->description)->toBe('MIKRO MOTIF NAVY')
        ->and($group->description2)->toBe('GROUP NB');

    $this->assertDatabaseHas('items', [
        'code' => 'AJD-CX90233-23-S',
        'pcode' => 'CX90233-23',
        'brand' => \App\Enums\ItemBrand::CX9->value,
        'genre' => $this->typeTag->id,
        'price' => 100000,
    ]);
    $this->assertDatabaseHas('items', [
        'code' => 'AJD-CX90233-23-M',
        'pcode' => 'CX90233-23',
        'brand' => \App\Enums\ItemBrand::CX9->value,
        'genre' => $this->typeTag->id,
        'price' => 100000,
    ]);
});

test('it syncs shared colorway attributes to every size when one item is edited', function () {
    $mediumTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'M', 'name' => 'Medium']);

    $this->itemService->create((object) [
        'pcode' => 'CX90233-23',
        'type' => ItemType::ITEM->value,
        'price' => 100000,
        'description' => 'Old desc',
        'description2' => 'Old nb',
        'restock_urgent_threshold' => 4,
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id, $mediumTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ]);

    $small = Item::where('code', 'AJD-CX90233-23-S')->firstOrFail();
    $medium = Item::where('code', 'AJD-CX90233-23-M')->firstOrFail();
    $medium->forceFill(['restock_urgent_threshold' => 9, 'cost' => 25000])->save();

    $this->itemService->update($small->id, (object) [
        'pcode' => 'CX90233-23',
        'type' => ItemType::ITEM->value,
        'price' => 175000,
        'description' => 'Mikro motif camo hijau',
        'description2' => 'Updated nb',
        'restock_urgent_threshold' => 12,
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ]);

    $small->refresh();
    $medium->refresh();
    $group = $small->group()->firstOrFail();

    expect($group->description)->toBe('MIKRO MOTIF CAMO HIJAU')
        ->and($group->description2)->toBe('UPDATED NB')
        ->and($group->brand)->toBe(\App\Enums\ItemBrand::CX9)
        ->and($group->genre)->toBe($this->typeTag->id)
        ->and((float) $small->price)->toBe(175000.0)
        ->and((float) $medium->price)->toBe(175000.0)
        ->and($small->description)->toBe('Mikro motif camo hijau')
        ->and($medium->description)->toBe('Mikro motif camo hijau')
        ->and($small->restock_urgent_threshold)->toBe(12)
        ->and($medium->restock_urgent_threshold)->toBe(9)
        ->and((float) $medium->cost)->toBe(25000.0)
        ->and($small->name)->toBe('CX90233-23 - BLUE - S')
        ->and($medium->name)->toBe('CX90233-23 - BLUE - M');
});

test('it moves every size to the new pcode and keeps group.name equal to pcode', function () {
    $mediumTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'M', 'name' => 'Medium']);

    $this->itemService->create((object) [
        'pcode' => 'CX00122-04',
        'type' => ItemType::ITEM->value,
        'price' => 100000,
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id, $mediumTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ]);

    $small = Item::where('code', 'AJD-CX00122-04-S')->firstOrFail();

    $this->itemService->update($small->id, (object) [
        'pcode' => 'CX00122-05',
        'type' => ItemType::ITEM->value,
        'product_name' => 'CX00122/04',
        'price' => 100000,
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ]);

    $this->assertDatabaseHas('item_group', [
        'master' => 'CX00122-05',
        'variant' => '05',
        'name' => 'CX00122-05',
    ]);
    $this->assertDatabaseHas('items', [
        'code' => 'AJD-CX00122-05-S',
        'pcode' => 'CX00122-05',
        'name' => 'CX00122-05 - BLUE - S',
    ]);
    $this->assertDatabaseHas('items', [
        'code' => 'AJD-CX00122-05-M',
        'pcode' => 'CX00122-05',
        'name' => 'CX00122-05 - BLUE - M',
    ]);
});

test('saving slash leftover pcode as hyphen keeps every size on the parent group page', function () {
    $mediumTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'M', 'name' => 'Medium']);
    $group = ItemGroup::factory()->create([
        'master' => 'CX00122/03',
        'variant' => '',
        'name' => 'CX00122/03',
        'description' => 'MIKRO MOTIF CAMO HIJAU',
    ]);

    $small = Item::factory()->create([
        'type' => ItemType::ITEM,
        'group_id' => $group->id,
        'pcode' => 'CX00122/03',
        'code' => 'AJDCX0012203S',
        'name' => 'CX00122/03 S',
        'price' => 100000,
    ]);
    $medium = Item::factory()->create([
        'type' => ItemType::ITEM,
        'group_id' => $group->id,
        'pcode' => 'CX00122/03',
        'code' => 'AJDCX0012203M',
        'name' => 'CX00122/03 M',
        'price' => 100000,
    ]);
    $small->tags()->sync([$this->typeTag->id, $this->warnaTag->id, $this->sizeTag->id, $this->jahitTag->id]);
    $medium->tags()->sync([$this->typeTag->id, $this->warnaTag->id, $mediumTag->id, $this->jahitTag->id]);

    $hierarchy = app(\App\Services\Items\ItemGroupHierarchyService::class);
    $builder = app(\App\Services\Items\ItemIdentityBuilder::class);
    $oldKey = $builder->itemParentKey($small->fresh(['group', 'tags']));

    $this->itemService->update($small->id, (object) [
        'pcode' => 'CX00122-03',
        'type' => ItemType::ITEM->value,
        'product_name' => 'CX00122/03',
        'price' => 100000,
        'description' => 'Mikro motif camo hijau',
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ]);

    $small->refresh()->load(['group', 'tags']);
    $medium->refresh()->load(['group', 'tags']);
    $group->refresh();

    expect($group->id)->toBe($small->group_id)
        ->and($medium->group_id)->toBe($group->id)
        ->and($group->master)->toBe('CX00122-03')
        ->and($group->variant)->toBe('03')
        ->and($small->pcode)->toBe('CX00122-03')
        ->and($medium->pcode)->toBe('CX00122-03');

    $newKey = $builder->itemParentKey($small);
    $oldDetail = $hierarchy->parentDetail($oldKey, fetchJubelio: false);
    $newDetail = $hierarchy->parentDetail($newKey, fetchJubelio: false);
    $canonicalDetail = $hierarchy->parentDetail('1:AJD:CX00122', fetchJubelio: false);
    $slashDetail = $hierarchy->parentDetail('1:AJD:CX00122/03', fetchJubelio: false);

    expect($newKey)->toBe('1:AJD:CX00122')
        ->and($oldDetail)->not->toBeNull()
        ->and($newDetail)->not->toBeNull()
        ->and($canonicalDetail)->not->toBeNull()
        ->and($slashDetail)->not->toBeNull()
        ->and(collect($canonicalDetail['colors'])->pluck('group_id'))->toContain($group->id)
        ->and(collect($canonicalDetail['colors'][0]['size_rows'])->pluck('item_id')->all())
        ->toContain($small->id, $medium->id);
});

test('it keeps a custom group title when product name is not the pcode', function () {
    $mediumTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'M', 'name' => 'Medium']);

    $this->itemService->create((object) [
        'pcode' => 'CX90233-23',
        'type' => ItemType::ITEM->value,
        'price' => 100000,
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id, $mediumTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ]);

    $small = Item::where('code', 'AJD-CX90233-23-S')->firstOrFail();

    $this->itemService->update($small->id, (object) [
        'pcode' => 'CX90233-23',
        'type' => ItemType::ITEM->value,
        'product_name' => 'Slash Running Shirt',
        'price' => 120000,
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ]);

    $this->assertDatabaseHas('item_group', ['name' => 'SLASH RUNNING SHIRT', 'master' => 'CX90233-23', 'variant' => '23']);
    $this->assertDatabaseHas('items', ['code' => 'AJD-CX90233-23-S', 'name' => 'SLASH RUNNING SHIRT - BLUE - S', 'price' => 120000]);
    $this->assertDatabaseHas('items', ['code' => 'AJD-CX90233-23-M', 'name' => 'SLASH RUNNING SHIRT - BLUE - M', 'price' => 120000]);
});

test('it updates group name from pcode placeholder when product_name is set on edit', function () {
    $this->itemService->create((object) [
        'pcode' => 'CX93249-03',
        'type' => ItemType::ITEM->value,
        'price' => 100000,
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ]);

    $item = Item::where('code', 'AJD-CX93249-03-S')->firstOrFail();

    $this->itemService->update($item->id, (object) [
        'pcode' => 'CX93249-03',
        'type' => ItemType::ITEM->value,
        'product_name' => 'Essential Shorts',
        'price' => 100000,
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ]);

    $this->assertDatabaseHas('item_group', [
        'master' => 'CX93249-03',
        'variant' => '03',
        'name' => 'ESSENTIAL SHORTS',
    ]);
    $this->assertDatabaseHas('items', [
        'code' => 'AJD-CX93249-03-S',
        'name' => 'ESSENTIAL SHORTS - BLUE - S',
    ]);
});

test('two manufactured colorways can share the same product title without a suffix', function () {
    $redTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'RED', 'name' => 'RED']);

    $this->itemService->create((object) [
        'pcode' => 'CX90233-23',
        'type' => ItemType::ITEM->value,
        'product_name' => 'Essential Shorts',
        'price' => 100000,
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ]);

    $this->itemService->create((object) [
        'pcode' => 'CX90233-24',
        'type' => ItemType::ITEM->value,
        'product_name' => 'Essential Shorts',
        'price' => 100000,
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => [$redTag->id],
        'jahit' => [$this->jahitTag->id],
    ]);

    $this->assertDatabaseHas('item_group', [
        'master' => 'CX90233-23',
        'variant' => '23',
        'name' => 'ESSENTIAL SHORTS',
    ]);
    $this->assertDatabaseHas('item_group', [
        'master' => 'CX90233-24',
        'variant' => '24',
        'name' => 'ESSENTIAL SHORTS',
    ]);
});

test('it does not copy asset cost onto sibling sizes when price is edited', function () {
    $mediumTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'M', 'name' => 'Medium']);
    $assetType = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ASSET_LANCAR->value,
        'code' => 'GLOVE',
        'name' => 'Glove',
    ]);

    $this->itemService->create((object) [
        'pcode' => 'GLOVE-01',
        'type' => ItemType::ASSET_LANCAR->value,
        'product_name' => 'Boxing Gloves',
        'price' => 500000,
        'cost' => 300000,
    ], [
        'types' => [$assetType->id],
        'sizes' => [$this->sizeTag->id, $mediumTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [],
    ]);

    $small = Item::where('code', 'GLOVE-01-BLUE-S')->firstOrFail();
    $medium = Item::where('code', 'GLOVE-01-BLUE-M')->firstOrFail();
    $medium->forceFill(['cost' => 310000])->save();

    $this->itemService->update($small->id, (object) [
        'pcode' => 'GLOVE-01',
        'type' => ItemType::ASSET_LANCAR->value,
        'product_name' => 'Boxing Gloves',
        'price' => 550000,
        'cost' => 305000,
    ], [
        'types' => [$assetType->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => $this->warnaTag->id,
    ]);

    $small->refresh();
    $medium->refresh();

    expect((float) $small->price)->toBe(550000.0)
        ->and((float) $medium->price)->toBe(550000.0)
        ->and((float) $small->cost)->toBe(305000.0)
        ->and((float) $medium->cost)->toBe(310000.0)
        ->and($small->name)->toBe('BOXING GLOVES - BLUE - S')
        ->and($medium->name)->toBe('BOXING GLOVES - BLUE - M');
});

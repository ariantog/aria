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
        new ImageService,
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
        'alias' => 'Test Product',
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

    $this->assertDatabaseHas('item_groups', [
        'name' => 'CX93249-03',
        'master' => 'CX93249',
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

    $group = ItemGroup::where('master', 'CX90233')->where('variant', '23')->firstOrFail();

    $this->itemService->renameGroupProductName($group, 'Slash Running Shirt');

    $this->assertDatabaseHas('item_groups', ['id' => $group->id, 'name' => 'SLASH RUNNING SHIRT']);
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
        'alias' => 'Slash Running Shirt',
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
        'alias' => 'Slash Running Shirt',
    ];

    $tags = [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ];

    expect($this->itemService->create($input, $tags))->toBeTrue();

    $this->assertDatabaseHas('item_groups', [
        'name' => 'SLASH RUNNING SHIRT',
        'master' => 'CX90233',
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
        'alias' => 'Slash Running Shirt',
    ];

    $tags = [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ];

    $this->itemService->create($input, $tags, $file);

    expect(ItemGroup::where('master', 'CX90233')->where('variant', '24')->exists())->toBeTrue();
});

test('it creates asset lancar variants with cartesian color and size', function () {
    $pinkTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'PINK', 'name' => 'PINK']);
    $mediumTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'M', 'name' => 'Medium']);

    $input = (object) [
        'pcode' => 'GLOVE-01',
        'type' => ItemType::ASSET_LANCAR->value,
        'alias' => 'Boxing Gloves',
        'price' => 5000000,
        'cost' => 3000000,
    ];

    $tags = [
        'types' => [$this->typeTag->id],
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

test('it rejects duplicate sku on create', function () {
    Item::factory()->create(['code' => 'AJD-CX90233-23-S']);

    $input = (object) [
        'pcode' => 'CX90233-23',
        'type' => ItemType::ITEM->value,
        'alias' => 'Slash Running Shirt',
    ];

    $tags = [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ];

    $this->itemService->create($input, $tags);
})->throws(Exception::class, 'SKU already exists');

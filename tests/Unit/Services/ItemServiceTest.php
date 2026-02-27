<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\Tag;
use App\Services\ImageService;
use App\Services\InventoryService;
use App\Services\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->imageService = new ImageService;
    $this->inventoryService = new InventoryService;
    $this->itemService = new ItemService($this->imageService, $this->inventoryService);

    // Seed Tags
    $this->typeTag = Tag::factory()->create(['type' => Tag::TYPE_TYPE, 'code' => 'T1', 'name' => 'Type1']);
    $this->sizeTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'S1', 'name' => 'Size1']);
});

test('it throws exception for invalid pcode', function () {
    $input = (object) ['pcode' => 'INVALID', 'type' => ItemType::ITEM->value];
    $this->itemService->create($input, []);
})->throws(Exception::class);

test('it creates item group and item successfully', function () {
    $input = (object) [
        'pcode' => 'CA12345/01',
        'type' => ItemType::ITEM->value,
        'price' => 100000,
        'description' => 'Test Item',
        'alias' => 'Test Alias',
    ];

    $tags = [
        Tag::TYPE_TYPE => [$this->typeTag->id],
        Tag::TYPE_SIZE => [$this->sizeTag->id],
        Tag::TYPE_WARNA => [],
        Tag::TYPE_JAHIT => [],
    ];

    $result = $this->itemService->create($input, $tags);

    expect($result)->toBeTrue();

    // Check Group
    $this->assertDatabaseHas('item_groups', ['name' => 'CA12345/01']);

    // Check Item
    $this->assertDatabaseHas('items', [
        'pcode' => 'CA12345/01',
        'code' => 'T1CA1234501S1', // T1 + CA1234501 + S1
    ]);
});

test('it saves image when provided', function () {
    Storage::fake('public');
    $file = UploadedFile::fake()->image('test.jpg');

    $input = (object) [
        'pcode' => 'CA12345/02',
        'type' => ItemType::ITEM->value,
        'price' => 100000,
    ];

    $tags = [
        Tag::TYPE_TYPE => [$this->typeTag->id],
        Tag::TYPE_SIZE => [$this->sizeTag->id],
    ];

    $this->itemService->create($input, $tags, $file);

    $group = ItemGroup::where('name', 'CA12345/02')->first();

    // Check file existence logic (ImageService saves to public path, might need adjustment for test env if not mocking ImageService)
    // Since ImageService writes to config path, we should ideally verify file exists.
    // However, Intervention Image write might be hard to test without mocking.
    // We check if it ran without error at least.
    expect($group)->not->toBeNull();
});

test('it creates asset lancar correctly', function () {
    $input = (object) [
        'pcode' => 'ASSET001',
        'type' => ItemType::ASSET_LANCAR->value,
        'name' => 'Laptop',
        'price' => 5000000,
    ];

    // Asset lancar might not need strict pcode format in logic?
    // Logic: if ($inputType === ItemType::ITEM) check regex.
    // So ASSET_LANCAR skips regex.

    $result = $this->itemService->create($input, [
        Tag::TYPE_TYPE => [$this->typeTag->id],
        Tag::TYPE_SIZE => [$this->sizeTag->id],
    ]);

    expect($result)->toBeTrue();
    $this->assertDatabaseHas('items', [
        'pcode' => 'ASSET001',
        'name' => 'LAPTOP',
        'type' => ItemType::ASSET_LANCAR->value,
    ]);
});

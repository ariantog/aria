<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\Tag;
use App\Models\User;
use App\Services\ImageService;
use App\Services\InventoryService;
use App\Services\Items\ItemIdentityBuilder;
use App\Services\ItemService;

beforeEach(function () {
    $this->user = User::factory()->create();
    expect($this->user->is_superadmin)->toBeTrue();

    $this->itemService = new ItemService(
        app(ImageService::class),
        new InventoryService,
        new ItemIdentityBuilder,
    );

    $this->typeTag = Tag::factory()->create(['type' => Tag::TYPE_TYPE, 'code' => 'AJD', 'name' => 'Jacket']);
    $this->sizeTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'S', 'name' => 'Small']);
    $this->mediumTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'M', 'name' => 'Medium']);
    $this->warnaTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLUE', 'name' => 'BLUE']);
    $this->jahitTag = Tag::factory()->create(['type' => Tag::TYPE_JAHIT, 'code' => 'J1', 'name' => 'Jahit1']);
});

test('updateColorway renames group and regenerates item display names', function () {
    $this->itemService->create((object) [
        'pcode' => 'CX90233-23',
        'type' => ItemType::ITEM->value,
        'price' => 100000,
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id, $this->mediumTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ]);

    $small = Item::where('code', 'AJD-CX90233-23-S')->firstOrFail();
    $medium = Item::where('code', 'AJD-CX90233-23-M')->firstOrFail();
    $group = $small->group()->firstOrFail();

    $this->itemService->updateColorway(
        $group,
        (object) [
            'product_name' => 'Slash Running Shirt',
            'description' => 'New desc',
        ],
        [
            ['id' => $small->id, 'price' => 100000],
            ['id' => $medium->id, 'price' => 100000],
        ],
    );

    $group->refresh();
    $small->refresh();
    $medium->refresh();

    expect($group->name)->toBe('SLASH RUNNING SHIRT')
        ->and($group->description)->toBe('NEW DESC')
        ->and($small->name)->toBe('SLASH RUNNING SHIRT - BLUE - S')
        ->and($medium->name)->toBe('SLASH RUNNING SHIRT - BLUE - M');
});

test('updateColorway changes price on one size without affecting siblings', function () {
    $this->itemService->create((object) [
        'pcode' => 'CX90233-23',
        'type' => ItemType::ITEM->value,
        'price' => 100000,
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id, $this->mediumTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ]);

    $small = Item::where('code', 'AJD-CX90233-23-S')->firstOrFail();
    $medium = Item::where('code', 'AJD-CX90233-23-M')->firstOrFail();
    $group = $small->group()->firstOrFail();

    $this->itemService->updateColorway(
        $group,
        (object) ['product_name' => 'CX90233-23'],
        [
            ['id' => $small->id, 'price' => 175000],
            ['id' => $medium->id, 'price' => 100000],
        ],
    );

    $small->refresh();
    $medium->refresh();

    expect((float) $small->price)->toBe(175000.0)
        ->and((float) $medium->price)->toBe(100000.0);
});

test('single item update does not broadcast price to sibling sizes', function () {
    $this->itemService->create((object) [
        'pcode' => 'CX90233-23',
        'type' => ItemType::ITEM->value,
        'price' => 100000,
        'description' => 'Old desc',
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id, $this->mediumTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ]);

    $small = Item::where('code', 'AJD-CX90233-23-S')->firstOrFail();
    $medium = Item::where('code', 'AJD-CX90233-23-M')->firstOrFail();

    $this->itemService->update($small->id, (object) [
        'pcode' => 'CX90233-23',
        'type' => ItemType::ITEM->value,
        'price' => 175000,
        'description' => 'Updated desc',
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ]);

    $small->refresh();
    $medium->refresh();

    expect((float) $small->price)->toBe(175000.0)
        ->and((float) $medium->price)->toBe(100000.0)
        ->and($medium->description)->toBe('Updated desc');
});

test('colorway edit page renders with size matrix and preview', function () {
    $group = ItemGroup::factory()->create([
        'master' => 'CX90233-23',
        'variant' => '23',
        'name' => 'CX90233-23',
        'description' => 'Test colorway',
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ITEM,
        'group_id' => $group->id,
        'pcode' => 'CX90233-23',
        'code' => 'AJD-CX90233-23-S',
        'name' => 'CX90233-23 - BLUE - S',
        'price' => 100000,
    ]);
    $item->tags()->sync([$this->typeTag->id, $this->warnaTag->id, $this->sizeTag->id, $this->jahitTag->id]);

    $this->actingAs($this->user)
        ->get(route('items.colorway-edit', $group))
        ->assertOk()
        ->assertSee('Edit colorway', false)
        ->assertSee('data-testid="colorway-size-matrix"', false)
        ->assertSee('data-testid="colorway-name-preview"', false)
        ->assertSee('AJD-CX90233-23-S', false);
});

test('colorway update via HTTP persists per-size price', function () {
    $group = ItemGroup::factory()->create([
        'master' => 'CX90233-23',
        'variant' => '23',
        'name' => 'CX90233-23',
    ]);

    $small = Item::factory()->create([
        'type' => ItemType::ITEM,
        'group_id' => $group->id,
        'pcode' => 'CX90233-23',
        'code' => 'AJD-CX90233-23-S',
        'name' => 'CX90233-23 - BLUE - S',
        'price' => 100000,
    ]);
    $medium = Item::factory()->create([
        'type' => ItemType::ITEM,
        'group_id' => $group->id,
        'pcode' => 'CX90233-23',
        'code' => 'AJD-CX90233-23-M',
        'name' => 'CX90233-23 - BLUE - M',
        'price' => 100000,
    ]);
    $small->tags()->sync([$this->typeTag->id, $this->warnaTag->id, $this->sizeTag->id, $this->jahitTag->id]);
    $medium->tags()->sync([$this->typeTag->id, $this->warnaTag->id, $this->mediumTag->id, $this->jahitTag->id]);

    $this->actingAs($this->user)
        ->put(route('items.colorway-update', $group), [
            'product_name' => 'Renamed Shirt',
            'description' => 'Via form',
            'items' => [
                $small->id => ['price' => 150000],
                $medium->id => ['price' => 120000],
            ],
        ])
        ->assertRedirect(route('items.colorway-edit', $group));

    $group->refresh();
    $small->refresh();
    $medium->refresh();

    expect($group->name)->toBe('RENAMED SHIRT')
        ->and($group->description)->toBe('VIA FORM')
        ->and((float) $small->price)->toBe(150000.0)
        ->and((float) $medium->price)->toBe(120000.0)
        ->and($small->name)->toBe('RENAMED SHIRT - BLUE - S');
});

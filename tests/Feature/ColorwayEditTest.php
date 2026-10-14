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
use App\Support\ItemPricing;
use App\Support\ItemProductTitle;

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

test('updateColorway with empty product name clears stored title to inherit parent', function () {
    $this->itemService->create((object) [
        'pcode' => 'CX90233-23',
        'type' => ItemType::ITEM->value,
        'product_name' => 'Colorway Only Title',
        'price' => 100000,
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ]);

    $small = Item::where('code', 'AJD-CX90233-23-S')->firstOrFail();
    $group = $small->group()->firstOrFail();
    $parentKey = app(ItemIdentityBuilder::class)->itemParentKey($small);
    ItemProductTitle::syncParentProductName($parentKey, 'PARENT RUNNING SHIRT');

    $this->itemService->updateColorway(
        $group,
        (object) ['pcode' => 'CX90233-23', 'product_name' => ''],
        [['id' => $small->id, 'price' => 100000]],
    );

    $group->refresh();
    $small->refresh();

    expect($group->name)->toBe('')
        ->and(ItemProductTitle::resolveBareTitle($small->fresh(['group', 'tags'])))->toBe('PARENT RUNNING SHIRT');
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
            'pcode' => 'CX90233-23',
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
        (object) ['pcode' => 'CX90233-23', 'product_name' => 'CX90233-23'],
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
        ->and((float) $medium->price)->toBe(0.0)
        ->and($medium->catalogDescription())->toBe('OLD DESC');
});

test('colorway show page renders catalog and sku table', function () {
    $group = ItemGroup::factory()->create([
        'master' => 'CX90233-23',
        'variant' => '23',
        'name' => 'RUNNING SHIRT',
        'description' => 'Lightweight tee',
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ITEM,
        'group_id' => $group->id,
        'pcode' => 'CX90233-23',
        'code' => 'AJD-CX90233-23-S',
        'price' => 100000,
    ]);
    $item->tags()->sync([$this->typeTag->id, $this->warnaTag->id, $this->sizeTag->id, $this->jahitTag->id]);

    $this->actingAs($this->user)
        ->get(route('items.colorway-show', $group))
        ->assertOk()
        ->assertSee('data-testid="colorway-show-summary"', false)
        ->assertSee('data-testid="colorway-show-sku-table"', false)
        ->assertSee('data-testid="colorway-show-edit-link"', false)
        ->assertSee('Lightweight tee', false)
        ->assertSee('AJD-CX90233-23-S', false)
        ->assertSee(route('items.colorway-edit', $group), false);
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

    $response = $this->actingAs($this->user)
        ->get(route('items.colorway-edit', $group));

    $response->assertOk()
        ->assertSee('Back to colorway', false)
        ->assertSee('data-testid="colorway-size-matrix"', false)
        ->assertSee('data-testid="colorway-name-preview"', false)
        ->assertSee('data-testid="colorway-save"', false)
        ->assertSee('data-testid="colorway-mass-price"', false)
        ->assertSee('data-testid="colorway-mass-reseller"', false)
        ->assertSee('data-mass-pricing="price"', false)
        ->assertSee('data-mass-pricing="reseller_price"', false)
        ->assertSee('id="colorway-save-form"', false)
        ->assertSee('data-testid="item-form-shared-tags"', false)
        ->assertDontSee('data-testid="item-form-sku-size"', false)
        ->assertDontSee('Size override', false)
        ->assertDontSee('Sell (eff.)', false)
        ->assertSee('AJD-CX90233-23-S', false)
        ->assertSee('data-testid="colorway-description"', false)
        ->assertSee('<textarea id="colorway-description"', false);

    $html = $response->getContent();

    expect($html)->toContain('id="colorway-pcode"')
        ->and($html)->toContain('value="CX90233-23"')
        ->and($html)->not->toContain("return confirm('Save this colorway")
        ->and($html)->not->toContain('id="colorwayApplyPricingForm"');
});

test('colorway edit matrix prefills price from parent when sku amount is unset', function () {
    $group = ItemGroup::factory()->create([
        'master' => 'CX90233-23',
        'variant' => '23',
        'name' => 'CX90233-23',
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ITEM,
        'group_id' => $group->id,
        'pcode' => 'CX90233-23',
        'code' => 'AJD-CX90233-23-S',
        'price' => 0,
        'reseller_price' => 0,
    ]);
    $item->tags()->sync([$this->typeTag->id, $this->warnaTag->id, $this->sizeTag->id, $this->jahitTag->id]);

    $parentKey = app(\App\Services\Items\ItemIdentityBuilder::class)->itemParentKey($item);
    \App\Models\ItemParentPrice::query()->create([
        'parent_key' => $parentKey,
        'price' => 275000,
        'reseller_price' => 250000,
    ]);

    $this->actingAs($this->user)
        ->get(route('items.colorway-edit', $group))
        ->assertOk()
        ->assertSee('name="items['.$item->id.'][pricing][price][value]"', false)
        ->assertSee('value="275000"', false)
        ->assertSee('value="250000"', false);
});

test('manufactured colorway edit preview includes warna label in saved display names', function () {
    $group = ItemGroup::factory()->create([
        'master' => 'CX90032-06',
        'variant' => '06',
        'name' => 'CX90032-06',
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ITEM,
        'group_id' => $group->id,
        'pcode' => 'CX90032-06',
        'code' => 'AJD-CX90032-06-M',
        'name' => 'CX90032-06 - BLACK - M',
        'price' => 100000,
    ]);
    $blackTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLACK', 'name' => 'Black']);
    $mediumTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'M', 'name' => 'Medium']);
    $item->tags()->sync([$this->typeTag->id, $blackTag->id, $mediumTag->id, $this->jahitTag->id]);

    expect(ItemProductTitle::buildDisplayNameWithBareTitle($item->fresh(['group', 'tags']), 'Renamed Shirt'))
        ->toBe('RENAMED SHIRT - BLACK - M');

    $this->actingAs($this->user)
        ->get(route('items.colorway-edit', $group))
        ->assertOk()
        ->assertSee('AJD-CX90032-06-M', false)
        ->assertSee('06', false)
        ->assertSee('BLACK', false)
        ->assertDontSee('warnaCode":"BLACK', false)
        ->assertDontSee('AJD-CX90032-06-BLACK-M', false);
});

test('manufactured sku edit prefills pricing from parent and hides scope radios', function () {
    $group = ItemGroup::factory()->create([
        'master' => 'CX90233-23',
        'variant' => '23',
        'name' => 'CX90233-23',
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ITEM,
        'group_id' => $group->id,
        'pcode' => 'CX90233-23',
        'code' => 'AJD-CX90233-23-S',
        'price' => 0,
        'reseller_price' => 0,
    ]);
    $item->tags()->sync([$this->typeTag->id, $this->warnaTag->id, $this->sizeTag->id, $this->jahitTag->id]);

    $parentKey = app(\App\Services\Items\ItemIdentityBuilder::class)->itemParentKey($item);
    \App\Models\ItemParentPrice::query()->create([
        'parent_key' => $parentKey,
        'price' => 275000,
        'reseller_price' => 250000,
    ]);

    $this->actingAs($this->user)
        ->get(route('items.edit', $item))
        ->assertOk()
        ->assertSee('data-testid="item-form-pricing-price-scope-size"', false)
        ->assertSee('data-testid="item-form-pricing-price-value"', false)
        ->assertSee('value="275000"', false)
        ->assertSee('value="250000"', false)
        ->assertDontSee('data-testid="item-scope-all-colorway"', false)
        ->assertDontSee('data-testid="item-form-pricing-cost-value"', false);
});

test('manufactured item edit shows size attributes only with colorway link', function () {
    $this->itemService->create((object) [
        'pcode' => 'CX90233-23',
        'type' => ItemType::ITEM->value,
        'price' => 100000,
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeTag->id],
        'warna' => [$this->warnaTag->id],
        'jahit' => [$this->jahitTag->id],
    ]);

    $small = Item::where('code', 'AJD-CX90233-23-S')->firstOrFail();

    $this->actingAs($this->user)
        ->get(route('items.edit', $small))
        ->assertOk()
        ->assertSee('data-testid="item-form-sku-size"', false)
        ->assertDontSee('data-testid="item-form-shared-tags"', false)
        ->assertSee(route('items.colorway-edit', $small->group_id), false);
});

test('updateColorway applies shared type tag to every sku code', function () {
    $otherType = Tag::factory()->create(['type' => Tag::TYPE_TYPE, 'code' => 'PNT', 'name' => 'Pants']);

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
            'pcode' => 'CX90233-23',
            'product_name' => 'CX90233-23',
            'tags' => [
                'types' => [$otherType->id],
                'warna' => [$this->warnaTag->id],
                'jahit' => [$this->jahitTag->id],
            ],
        ],
        [
            ['id' => $small->id, 'price' => 100000],
            ['id' => $medium->id, 'price' => 100000],
        ],
    );

    $small->refresh();
    $medium->refresh();

    expect($small->code)->toBe('PNT-CX90233-23-S')
        ->and($medium->code)->toBe('PNT-CX90233-23-M');
});

test('colorway catalog save does not apply colorway pricing or clear size overrides', function () {
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

    ItemPricing::apply($small, 'price', ItemPricing::SCOPE_SIZE, 175000);

    $this->itemService->updateColorway(
        $group,
        (object) [
            'pcode' => 'CX90233-23',
            'product_name' => 'Title Only Change',
            'tags' => [
                'types' => [$this->typeTag->id],
                'warna' => [$this->warnaTag->id],
                'jahit' => [$this->jahitTag->id],
            ],
        ],
        [
            ['id' => $small->id],
            ['id' => $medium->id],
        ],
    );

    $group->refresh();
    $small->refresh();
    $medium->refresh();

    expect($group->name)->toBe('TITLE ONLY CHANGE')
        ->and((float) $small->price)->toBe(175000.0)
        ->and((float) $medium->price)->toBe(0.0);
});

test('colorway update via HTTP persists per-size price and reseller', function () {
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
            'pcode' => 'CX90233-23',
            'product_name' => 'Renamed Shirt',
            'description' => 'Via form',
            'tags' => [
                'types' => $this->typeTag->id,
                'warna' => $this->warnaTag->id,
                'jahit' => $this->jahitTag->id,
            ],
            'items' => [
                $small->id => [
                    'pricing' => [
                        'price' => ['scope' => 'size', 'value' => 150000],
                        'reseller_price' => ['scope' => 'size', 'value' => 140000],
                    ],
                ],
                $medium->id => [
                    'pricing' => [
                        'price' => ['scope' => 'size', 'value' => 120000],
                        'reseller_price' => ['scope' => 'size', 'value' => 110000],
                    ],
                ],
            ],
        ])
        ->assertRedirect(route('items.colorway-show', $group));

    $group->refresh();
    $small->refresh();
    $medium->refresh();

    expect($group->name)->toBe('RENAMED SHIRT')
        ->and($group->description)->toBe('VIA FORM')
        ->and((float) $small->price)->toBe(150000.0)
        ->and((float) $medium->price)->toBe(120000.0)
        ->and((float) $small->reseller_price)->toBe(140000.0)
        ->and((float) $medium->reseller_price)->toBe(110000.0)
        ->and($small->name)->toBe('RENAMED SHIRT - BLUE - S');
});

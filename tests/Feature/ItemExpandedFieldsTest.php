<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\Tag;
use App\Models\User;
use App\Services\ItemService;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('shows url and restock threshold fields on item create page', function () {
    $this->actingAs($this->user)
        ->get(route('items.create'))
        ->assertOk()
        ->assertSee('Product URL', false)
        ->assertSee('Restock urgent threshold', false)
        ->assertSee('name="url"', false)
        ->assertSee('name="restock_urgent_threshold"', false);
});

it('shows restock threshold on item edit and url on colorway edit', function () {
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
        'url' => 'https://example.com/product',
    ]);
    $item = Item::factory()->create([
        'group_id' => $group->id,
        'pcode' => 'CX93249-03',
        'code' => 'AJD-CX93249-03-S',
        'restock_urgent_threshold' => 12,
    ]);
    $item->tags()->attach([$typeTag->id, $sizeTag->id, $warnaTag->id, $jahitTag->id]);

    $this->actingAs($this->user)
        ->get(route('items.edit', $item))
        ->assertOk()
        ->assertSee('Restock urgent threshold', false)
        ->assertSee('value="12"', false)
        ->assertDontSee('name="url"', false);

    $this->actingAs($this->user)
        ->get(route('items.colorway-edit', $group))
        ->assertOk()
        ->assertSee('name="url"', false)
        ->assertSee('https://example.com/product', false);
});

it('stores group url and item restock urgent threshold when creating manufactured item', function () {
    $typeTag = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ITEM->value,
        'code' => 'AJD',
        'name' => 'Jacket',
    ]);
    $sizeTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'S', 'name' => 'S']);
    $warnaTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLUE', 'name' => 'BLUE']);
    $jahitTag = Tag::factory()->create(['type' => Tag::TYPE_JAHIT, 'code' => 'J1', 'name' => 'J1']);

    $this->actingAs($this->user)
        ->post(route('items.store'), [
            'type' => ItemType::ITEM->value,
            'pcode' => 'CX93249-03',
            'price' => 150000,
            'url' => 'https://shop.example.com/cx93249',
            'restock_urgent_threshold' => 15,
            'tags' => [
                'types' => [$typeTag->id],
                'sizes' => [$sizeTag->id],
                'warna' => $warnaTag->id,
                'jahit' => $jahitTag->id,
            ],
        ])
        ->assertRedirect(route('items.index'))
        ->assertSessionHas('success');

    $item = Item::query()->where('pcode', 'CX93249-03')->first();

    expect($item)->not->toBeNull()
        ->and($item->group?->url)->toBe('https://shop.example.com/cx93249')
        ->and($item->restock_urgent_threshold)->toBe(15);
});

it('updates group url on colorway edit and restock threshold on item edit', function () {
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
        'master' => 'CX93249',
        'variant' => '03',
        'url' => null,
    ]);
    $item = Item::factory()->create([
        'group_id' => $group->id,
        'pcode' => 'CX93249-03',
        'code' => 'AJD-CX93249-03-S',
        'restock_urgent_threshold' => null,
    ]);
    $item->tags()->attach([$typeTag->id, $sizeTag->id, $warnaTag->id, $jahitTag->id]);

    $this->actingAs($this->user)
        ->put(route('items.colorway-update', $group), [
            'pcode' => 'CX93249-03',
            'product_name' => 'CX93249-03',
            'url' => 'https://updated.example.com/item',
            'tags' => [
                'types' => $typeTag->id,
                'warna' => $warnaTag->id,
                'jahit' => $jahitTag->id,
            ],
            'items' => [
                $item->id => ['price' => $item->price],
            ],
        ])
        ->assertRedirect(route('items.colorway-edit', $group));

    $this->actingAs($this->user)
        ->put(route('items.update', $item), [
            'type' => ItemType::ITEM->value,
            'pcode' => 'CX93249-03',
            'restock_urgent_threshold' => 8,
            'tags' => [
                'sizes' => [$sizeTag->id],
            ],
        ])
        ->assertRedirect(route('items.show', $item));

    $item->refresh();
    $group->refresh();

    expect($group->url)->toBe('https://updated.example.com/item')
        ->and($item->restock_urgent_threshold)->toBe(8);
});

it('stores colorway descriptions on colorway edit and per-sku overrides on asset lancar edit', function () {
    $size5 = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => '5KG', 'name' => '5KG']);
    $size6 = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => '6KG', 'name' => '6KG']);
    $warnaTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLACK', 'name' => 'BLACK']);
    $group = ItemGroup::factory()->create([
        'master' => 'DUMBBELL-04',
        'variant' => 'BLACK',
        'description' => 'SHARED DESC',
        'description2' => 'SHARED NB',
        'reseller_price' => 100000,
    ]);
    $item5 = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'group_id' => $group->id,
        'code' => 'DUMBBELL-04-BLACK-5KG',
        'pcode' => 'DUMBBELL-04',
        'cost' => 50000,
        'description' => '',
        'description2' => '',
        'reseller_price' => 0,
    ]);
    $item6 = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'group_id' => $group->id,
        'code' => 'DUMBBELL-04-BLACK-6KG',
        'pcode' => 'DUMBBELL-04',
        'cost' => 55000,
        'description' => '6KG ONLY DESC',
        'description2' => '6KG NB',
        'reseller_price' => 120000,
    ]);
    $item5->tags()->attach([$size5->id, $warnaTag->id]);
    $item6->tags()->attach([$size6->id, $warnaTag->id]);

    app(ItemService::class)->updateColorway(
        $group,
        (object) [
            'pcode' => 'DUMBBELL-04',
            'product_name' => 'Dumbbell',
            'description' => 'UPDATED SHARED DESC',
            'description2' => 'UPDATED SHARED NB',
        ],
        [
            ['id' => $item5->id, 'cost' => 50000],
            ['id' => $item6->id, 'cost' => 55000, 'pricing' => ['reseller_price' => ['scope' => 'size', 'value' => 110000]]],
        ],
    );

    $this->actingAs($this->user)
        ->put(route('assetlancar.update', $item5), [
            'type' => ItemType::ASSET_LANCAR->value,
            'pcode' => 'DUMBBELL-04',
            'cost' => 50000,
            'item_description' => '5KG SPECIAL',
            'item_description2' => '5KG NOTES',
            'item_reseller_price' => 95000,
            'tags' => [
                'sizes' => [$size5->id],
                'warna' => $warnaTag->id,
            ],
        ])
        ->assertRedirect(route('assetlancar.show', $item5));

    $item5->refresh();
    $item6->refresh();
    $group->refresh();

    expect($group->description)->toBe('UPDATED SHARED DESC')
        ->and($group->description2)->toBe('UPDATED SHARED NB')
        ->and((float) $group->reseller_price)->toBe(100000.0)
        ->and($item5->description)->toBe('5KG SPECIAL')
        ->and($item5->description2)->toBe('5KG NOTES')
        ->and((float) $item5->reseller_price)->toBe(95000.0)
        ->and($item5->catalogDescription())->toBe('5KG SPECIAL')
        ->and((float) $item5->catalogResellerPrice())->toBe(95000.0)
        ->and($item6->description)->toBe('6KG ONLY DESC')
        ->and($item6->description2)->toBe('6KG NB')
        ->and($item6->catalogDescription())->toBe('6KG ONLY DESC')
        ->and($item6->catalogDescription2())->toBe('6KG NB')
        ->and($item5->catalogDescription2())->toBe('5KG NOTES')
        ->and((float) $item6->catalogResellerPrice())->toBe(110000.0);
});

it('keeps per-sku NB override when colorway description is updated without resubmitting NB', function () {
    $sizeTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => '5KG', 'name' => '5KG']);
    $warnaTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLACK', 'name' => 'BLACK']);
    $group = ItemGroup::factory()->create([
        'master' => 'DUMBBELL-05',
        'variant' => 'BLACK',
        'description' => 'SHARED DESC',
        'description2' => 'SHARED NB',
    ]);
    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'group_id' => $group->id,
        'code' => 'DUMBBELL-05-BLACK-5KG',
        'pcode' => 'DUMBBELL-05',
        'description' => '',
        'description2' => '5KG NB ONLY',
    ]);
    $item->tags()->attach([$sizeTag->id, $warnaTag->id]);

    app(ItemService::class)->updateColorway(
        $group,
        (object) [
            'pcode' => 'DUMBBELL-05',
            'product_name' => 'Dumbbell',
            'description' => 'UPDATED SHARED DESC',
        ],
        [
            ['id' => $item->id, 'cost' => $item->cost],
        ],
    );

    $item->refresh();
    $group->refresh();

    expect($group->description)->toBe('UPDATED SHARED DESC')
        ->and($group->description2)->toBe('SHARED NB')
        ->and($item->description2)->toBe('5KG NB ONLY')
        ->and($item->catalogDescription2())->toBe('5KG NB ONLY');
});

it('shows separate global and per-sku description fields on asset lancar edit page', function () {
    $sizeTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => '5KG', 'name' => '5KG']);
    $warnaTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLACK', 'name' => 'BLACK']);
    $group = ItemGroup::factory()->create([
        'description' => 'GROUP DESC',
        'description2' => 'GROUP NB',
        'reseller_price' => 100000,
    ]);
    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'group_id' => $group->id,
        'code' => 'DUMBBELL-04-BLACK-5KG',
        'pcode' => 'DUMBBELL-04',
        'cost' => 50000,
        'description' => 'SKU DESC',
        'description2' => 'SKU NB',
        'reseller_price' => 90000,
    ]);
    $item->tags()->attach([$sizeTag->id, $warnaTag->id]);

    $this->actingAs($this->user)
        ->get(route('assetlancar.edit', $item))
        ->assertOk()
        ->assertSee('name="item_description"', false)
        ->assertSee('data-testid="item-form-pricing-reseller_price-scope-size"', false)
        ->assertSee('data-testid="item-form-sku-details"', false)
        ->assertSee('Optional description overrides', false)
        ->assertSee('SKU DESC', false)
        ->assertDontSee('name="description"', false);
});

it('redirects to asset lancar detail page after update', function () {
    $sizeTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'S', 'name' => 'S']);
    $warnaTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLACK', 'name' => 'BLACK']);
    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'code' => 'GLOVE-07-BLACK-S',
        'pcode' => 'GLOVE-07',
        'cost' => 1000,
    ]);
    $item->tags()->attach([$sizeTag->id, $warnaTag->id]);

    $this->actingAs($this->user)
        ->put(route('assetlancar.update', $item), [
            'type' => ItemType::ASSET_LANCAR->value,
            'pcode' => 'GLOVE-07',
            'product_name' => 'Glove 07',
            'cost' => 1500,
            'tags' => [
                'sizes' => [$sizeTag->id],
                'warna' => $warnaTag->id,
            ],
        ])
        ->assertRedirect(route('assetlancar.show', $item))
        ->assertSessionHas('success', 'Item updated.');
});

it('allows asset lancar size-only edit without resubmitting warna from the form', function () {
    $sizeTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'S', 'name' => 'S']);
    $mediumTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'M', 'name' => 'M']);
    $blackTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLACK', 'name' => 'BLACK']);
    $gloveType = Tag::factory()->create(['type' => Tag::TYPE_TYPE, 'code' => 'GLOVE', 'name' => 'Glove']);

    $group = ItemGroup::factory()->create([
        'master' => 'GLOVE-07',
        'variant' => 'BLACK',
        'name' => 'GLOVE 07',
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'group_id' => $group->id,
        'code' => 'GLOVE-07-BLACK-S',
        'pcode' => 'GLOVE-07',
        'name' => 'GLOVE 07 - BLACK - S',
        'cost' => 1000,
    ]);
    $item->tags()->attach([$sizeTag->id, $gloveType->id, $blackTag->id]);

    $this->actingAs($this->user)
        ->put(route('assetlancar.update', $item), [
            'type' => ItemType::ASSET_LANCAR->value,
            'pcode' => 'GLOVE-07',
            'product_name' => 'Glove 07',
            'cost' => 1500,
            'tags' => [
                'sizes' => [$mediumTag->id],
            ],
        ])
        ->assertRedirect(route('assetlancar.show', $item))
        ->assertSessionHas('success', 'Item updated.');

    $item->refresh();

    expect($item->code)->toBe('GLOVE-07-BLACK-M')
        ->and($item->tags->firstWhere('type', Tag::TYPE_WARNA)?->id)->toBe($blackTag->id);
});

it('infers warna from group variant on asset lancar size edit when pivot tag is missing', function () {
    $sizeTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'S', 'name' => 'S']);
    $mediumTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'M', 'name' => 'M']);
    $blackTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLACK', 'name' => 'BLACK']);
    $gloveType = Tag::factory()->create(['type' => Tag::TYPE_TYPE, 'code' => 'GLOVE', 'name' => 'Glove']);

    $group = ItemGroup::factory()->create([
        'master' => 'GLOVE-07',
        'variant' => 'BLACK',
        'name' => 'GLOVE 07',
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'group_id' => $group->id,
        'code' => 'GLOVE-07-BLACK-S',
        'pcode' => 'GLOVE-07',
        'name' => 'GLOVE 07 - BLACK - S',
        'cost' => 1000,
    ]);
    $item->tags()->attach([$sizeTag->id, $gloveType->id]);

    $this->actingAs($this->user)
        ->put(route('assetlancar.update', $item), [
            'type' => ItemType::ASSET_LANCAR->value,
            'pcode' => 'GLOVE-07',
            'product_name' => 'Glove 07',
            'cost' => 1500,
            'tags' => [
                'sizes' => [$mediumTag->id],
            ],
        ])
        ->assertRedirect(route('assetlancar.show', $item));

    $item->refresh();

    expect($item->code)->toBe('GLOVE-07-BLACK-M')
        ->and($item->tags->firstWhere('type', Tag::TYPE_WARNA)?->id)->toBe($blackTag->id);
});

it('stores legacy_code when asset lancar edit changes the sku', function () {
    $sizeTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'S', 'name' => 'S']);
    $blackTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLACK', 'name' => 'BLACK']);
    $navyTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'NAVY', 'name' => 'NAVY']);
    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'code' => 'GLOVE-07-BLACK-S',
        'legacy_code' => null,
        'pcode' => 'GLOVE-07',
        'name' => 'GLOVE 07 - BLACK - S',
        'cost' => 1000,
    ]);
    $item->tags()->attach([$sizeTag->id, $blackTag->id]);

    $this->actingAs($this->user)
        ->put(route('assetlancar.update', $item), [
            'type' => ItemType::ASSET_LANCAR->value,
            'pcode' => 'GLOVE-07',
            'product_name' => 'Glove 07',
            'cost' => 1500,
            'tags' => [
                'sizes' => [$sizeTag->id],
                'warna' => $navyTag->id,
            ],
        ])
        ->assertRedirect(route('assetlancar.show', $item));

    $item->refresh();

    expect($item->code)->toBe('GLOVE-07-NAVY-S')
        ->and($item->legacy_code)->toBe('GLOVE-07-BLACK-S');
});

it('shows group url and item restock threshold on item detail page when set', function () {
    $group = ItemGroup::factory()->create(['url' => 'https://catalog.example.com/sku']);
    $item = Item::factory()->create([
        'group_id' => $group->id,
        'restock_urgent_threshold' => 20,
    ]);

    $this->actingAs($this->user)
        ->get(route('items.show', $item))
        ->assertOk()
        ->assertSee('https://catalog.example.com/sku', false)
        ->assertSee('20 units', false);
});

it('rejects invalid restock urgent threshold on create', function () {
    $typeTag = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ITEM->value,
        'code' => 'AJD',
        'name' => 'Jacket',
    ]);
    $sizeTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'S', 'name' => 'S']);
    $warnaTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLUE', 'name' => 'BLUE']);
    $jahitTag = Tag::factory()->create(['type' => Tag::TYPE_JAHIT, 'code' => 'J1', 'name' => 'J1']);

    $this->actingAs($this->user)
        ->from(route('items.create'))
        ->post(route('items.store'), [
            'type' => ItemType::ITEM->value,
            'pcode' => 'CX93249-03',
            'restock_urgent_threshold' => 0,
            'tags' => [
                'types' => [$typeTag->id],
                'sizes' => [$sizeTag->id],
                'warna' => $warnaTag->id,
                'jahit' => $jahitTag->id,
            ],
        ])
        ->assertRedirect(route('items.create'))
        ->assertSessionHasErrors('restock_urgent_threshold');
});

it('stores per-sku overrides when creating multiple asset lancar sizes', function () {
    $assetType = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ASSET_LANCAR->value,
        'code' => 'GLOVE',
        'name' => 'Glove',
    ]);
    $sizeS = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'S', 'name' => 'S']);
    $sizeM = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'M', 'name' => 'M']);
    $warnaTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLACK', 'name' => 'BLACK']);

    $this->actingAs($this->user)
        ->post(route('assetlancar.store'), [
            'type' => ItemType::ASSET_LANCAR->value,
            'pcode' => 'GLOVE-09',
            'product_name' => 'Training Glove',
            'price' => 100000,
            'cost' => 60000,
            'reseller_price' => 90000,
            'description' => 'SHARED DESC',
            'tags' => [
                'types' => [$assetType->id],
                'sizes' => [$sizeS->id, $sizeM->id],
                'warna' => [$warnaTag->id],
            ],
            'sku_overrides' => [
                'GLOVE-09-BLACK-S' => [
                    'price' => 110000,
                    'description' => 'SMALL ONLY',
                    'description2' => 'SMALL NB',
                    'reseller_price' => 95000,
                ],
            ],
        ])
        ->assertRedirect(route('assetlancar.index'))
        ->assertSessionHas('success');

    $small = Item::query()->where('code', 'GLOVE-09-BLACK-S')->first();
    $medium = Item::query()->where('code', 'GLOVE-09-BLACK-M')->first();

    expect($small)->not->toBeNull()
        ->and((float) $small->price)->toBe(110000.0)
        ->and($small->description)->toBe('SMALL ONLY')
        ->and($small->description2)->toBe('SMALL NB')
        ->and($small->catalogDescription2())->toBe('SMALL NB')
        ->and((float) $small->reseller_price)->toBe(95000.0)
        ->and($small->effectivePrice())->toBe(110000.0)
        ->and($medium->effectivePrice())->toBe(100000.0)
        ->and(trim((string) ($medium->description ?? '')))->toBe('');
});

it('allows item create and colorway update without description2', function () {
    $typeTag = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ITEM->value,
        'code' => 'AJD',
        'name' => 'Jacket',
    ]);
    $sizeTag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'S', 'name' => 'S']);
    $warnaTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLUE', 'name' => 'BLUE']);
    $jahitTag = Tag::factory()->create(['type' => Tag::TYPE_JAHIT, 'code' => 'J1', 'name' => 'J1']);

    $this->actingAs($this->user)
        ->post(route('items.store'), [
            'type' => ItemType::ITEM->value,
            'pcode' => 'CX94001-01',
            'catalog_tab' => 'colorway',
            'description' => 'Catalog text only',
            'description2' => '',
            'price' => 120000,
            'tags' => [
                'types' => [$typeTag->id],
                'sizes' => [$sizeTag->id],
                'warna' => $warnaTag->id,
                'jahit' => $jahitTag->id,
            ],
        ])
        ->assertRedirect(route('items.index'))
        ->assertSessionHas('success')
        ->assertSessionHasNoErrors();

    $item = Item::query()->where('pcode', 'CX94001-01')->firstOrFail();
    $group = $item->group;

    expect($group)->not->toBeNull()
        ->and($group->description)->toBe('CATALOG TEXT ONLY')
        ->and((string) $group->description2)->toBe('')
        ->and($item->catalogDescription2())->toBe('');

    $group->update(['description2' => 'OLD NB']);

    $typeTag = Tag::query()->where('type', Tag::TYPE_TYPE)->where('code', 'AJD')->first();
    $warnaTag = $item->tags->firstWhere('type', Tag::TYPE_WARNA);
    $jahitTag = $item->tags->firstWhere('type', Tag::TYPE_JAHIT);

    $this->actingAs($this->user)
        ->put(route('items.colorway-update', $group), [
            'pcode' => 'CX94001-01',
            'product_name' => 'Test shirt',
            'description' => 'Still no NB',
            'description2' => '',
            'tags' => [
                'types' => $typeTag->id,
                'warna' => $warnaTag->id,
                'jahit' => $jahitTag->id,
            ],
            'items' => [
                $item->id => ['price' => $item->price],
            ],
        ])
        ->assertRedirect(route('items.colorway-edit', $group))
        ->assertSessionHas('success');

    expect($group->fresh()->description2)->toBe('');
});

<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\Tag;
use App\Models\User;

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

it('shows url and restock threshold fields on item edit page', function () {
    $group = ItemGroup::factory()->create(['url' => 'https://example.com/product']);
    $item = Item::factory()->create([
        'group_id' => $group->id,
        'restock_urgent_threshold' => 12,
    ]);

    $this->actingAs($this->user)
        ->get(route('items.edit', $item))
        ->assertOk()
        ->assertSee('Product URL', false)
        ->assertSee('Restock urgent threshold', false)
        ->assertSee('https://example.com/product', false)
        ->assertSee('value="12"', false);
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

it('updates group url and item restock urgent threshold on item edit', function () {
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
        ->put(route('items.update', $item), [
            'type' => ItemType::ITEM->value,
            'pcode' => 'CX93249-03',
            'url' => 'https://updated.example.com/item',
            'restock_urgent_threshold' => 8,
            'tags' => [
                'types' => $typeTag->id,
                'sizes' => [$sizeTag->id],
                'warna' => $warnaTag->id,
                'jahit' => $jahitTag->id,
            ],
        ])
        ->assertRedirect(route('items.show', $item));

    $item->refresh();
    $group->refresh();

    expect($group->url)->toBe('https://updated.example.com/item')
        ->and($item->restock_urgent_threshold)->toBe(8);
});

it('stores per-sku description overrides and reseller price on asset lancar edit', function () {
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

    $this->actingAs($this->user)
        ->put(route('assetlancar.update', $item5), [
            'type' => ItemType::ASSET_LANCAR->value,
            'pcode' => 'DUMBBELL-04',
            'product_name' => 'Dumbbell',
            'cost' => 50000,
            'description' => 'UPDATED SHARED DESC',
            'description2' => 'UPDATED SHARED NB',
            'reseller_price' => 110000,
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
        ->and((float) $group->reseller_price)->toBe(110000.0)
        ->and($item5->description)->toBe('5KG SPECIAL')
        ->and($item5->description2)->toBe('5KG NOTES')
        ->and((float) $item5->reseller_price)->toBe(95000.0)
        ->and($item5->catalogDescription())->toBe('5KG SPECIAL')
        ->and((float) $item5->catalogResellerPrice())->toBe(95000.0)
        ->and($item6->description)->toBe('6KG ONLY DESC')
        ->and($item6->catalogDescription())->toBe('6KG ONLY DESC')
        ->and((float) $item6->catalogResellerPrice())->toBe(120000.0);
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
        ->assertSee('name="item_reseller_price"', false)
        ->assertSee('data-testid="item-form-sku-local-desc"', false)
        ->assertSee('Local description overrides (optional)', false)
        ->assertSee('GROUP DESC', false)
        ->assertSee('SKU DESC', false);
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
        ->and((float) $small->reseller_price)->toBe(95000.0)
        ->and((float) $medium->price)->toBe(100000.0)
        ->and(trim((string) ($medium->description ?? '')))->toBe('');
});

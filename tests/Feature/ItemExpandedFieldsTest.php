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

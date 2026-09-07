<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = User::factory()->create();
    $role = Role::create(['name' => 'Super Admin']);
    $this->user->assignRole($role);
});

test('manufactured item show page shows duplicate button when user can create', function () {
    Permission::firstOrCreate(['name' => 'items-create']);
    $this->user->givePermissionTo('items-create');

    $item = Item::factory()->create([
        'type' => ItemType::ITEM,
        'code' => 'AJD-CX93024-23-S',
    ]);

    $this->actingAs($this->user)
        ->get(route('items.show', $item))
        ->assertOk()
        ->assertSee('data-testid="duplicate-sku"', false)
        ->assertSee(route('items.duplicate', $item), false);
});

test('manufactured duplicate prefills form except sizes', function () {
    Permission::firstOrCreate(['name' => 'items-create']);
    $this->user->givePermissionTo('items-create');

    $typeTag = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ITEM->value,
        'code' => 'AJD',
        'name' => 'Jacket',
    ]);
    $warnaTag = Tag::factory()->create([
        'type' => Tag::TYPE_WARNA,
        'code' => '23',
        'name' => '23',
    ]);
    $jahitTag = Tag::factory()->create([
        'type' => Tag::TYPE_JAHIT,
        'code' => 'J1',
        'name' => 'Jahit 1',
    ]);
    $sizeTag = Tag::factory()->create([
        'type' => Tag::TYPE_SIZE,
        'code' => 'S',
        'name' => 'Small',
    ]);
    $lSizeTag = Tag::factory()->create([
        'type' => Tag::TYPE_SIZE,
        'code' => 'L',
        'name' => 'Large',
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ITEM,
        'pcode' => 'CX93024-23',
        'code' => 'AJD-CX93024-23-S',
        'price' => 175_000,
        'description' => 'Running shirt',
    ]);
    $item->tags()->attach([$typeTag->id, $warnaTag->id, $jahitTag->id, $sizeTag->id]);

    $this->actingAs($this->user)
        ->get(route('items.duplicate', $item))
        ->assertOk()
        ->assertSee('data-testid="duplicate-sku-banner"', false)
        ->assertSee('value="CX93024-23"', false)
        ->assertSee('data-testid="item-form-price"', false)
        ->assertSee('175000', false)
        ->assertSee('data-tag-selected="1"', false)
        ->assertSee('name="tags[jahit]"', false)
        ->assertSee('value="'.$jahitTag->id.'"', false)
        ->assertSee('value="'.$lSizeTag->id.'"', false)
        ->assertDontSee('name="tags[sizes][]" value="'.$sizeTag->id.'" checked', false);
});

test('manufactured duplicate creates additional size for same colorway', function () {
    Permission::firstOrCreate(['name' => 'items-create']);
    $this->user->givePermissionTo('items-create');

    $typeTag = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ITEM->value,
        'code' => 'AJD',
        'name' => 'Jacket',
    ]);
    $warnaTag = Tag::factory()->create([
        'type' => Tag::TYPE_WARNA,
        'code' => '23',
        'name' => '23',
    ]);
    $jahitTag = Tag::factory()->create([
        'type' => Tag::TYPE_JAHIT,
        'code' => 'J1',
        'name' => 'Jahit 1',
    ]);
    $sizeTag = Tag::factory()->create([
        'type' => Tag::TYPE_SIZE,
        'code' => 'S',
        'name' => 'Small',
    ]);
    $lSizeTag = Tag::factory()->create([
        'type' => Tag::TYPE_SIZE,
        'code' => 'L',
        'name' => 'Large',
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ITEM,
        'pcode' => 'CX93024-23',
        'code' => 'AJD-CX93024-23-S',
        'price' => 175_000,
    ]);
    $item->tags()->attach([$typeTag->id, $warnaTag->id, $jahitTag->id, $sizeTag->id]);

    $this->actingAs($this->user)
        ->post(route('items.store'), [
            'type' => ItemType::ITEM->value,
            'pcode' => 'CX93024-23',
            'price' => 175_000,
            'tags' => [
                'types' => $typeTag->id,
                'sizes' => [$lSizeTag->id],
                'warna' => $warnaTag->id,
                'jahit' => $jahitTag->id,
            ],
        ])
        ->assertRedirect(route('items.index'))
        ->assertSessionHas('success');

    expect(Item::query()->where('code', 'AJD-CX93024-23-L')->exists())->toBeTrue();
});

test('asset lancar duplicate prefills form except sizes', function () {
    Permission::firstOrCreate(['name' => 'assetLancar-create']);
    $this->user->givePermissionTo('assetLancar-create');

    $typeTag = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ASSET_LANCAR->value,
        'code' => 'ELBOW',
        'name' => 'Elbow',
    ]);
    $warnaTag = Tag::factory()->create([
        'type' => Tag::TYPE_WARNA,
        'code' => 'BLACK',
        'name' => 'Black',
    ]);
    $sizeTag = Tag::factory()->create([
        'type' => Tag::TYPE_SIZE,
        'code' => 'S',
        'name' => 'Small',
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'pcode' => 'ELBOW-01',
        'code' => 'ELBOW-01-BLACK-S',
        'cost' => 50_000,
        'price' => 75_000,
    ]);
    $item->tags()->attach([$typeTag->id, $warnaTag->id, $sizeTag->id]);

    $this->actingAs($this->user)
        ->get(route('assetlancar.duplicate', $item))
        ->assertOk()
        ->assertSee('data-testid="duplicate-sku-banner"', false)
        ->assertSee('value="ELBOW-01"', false)
        ->assertSee('data-testid="item-form-cost"', false)
        ->assertSee('50000', false)
        ->assertSee('data-tag-selected="1"', false)
        ->assertSee('name="tags[sizes][]"', false)
        ->assertSee('value="'.$sizeTag->id.'"', false)
        ->assertDontSee('name="tags[sizes][]" value="'.$sizeTag->id.'" checked', false);
});

test('duplicate route rejects wrong item type', function () {
    Permission::firstOrCreate(['name' => 'items-create']);
    $this->user->givePermissionTo('items-create');

    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'code' => 'ELBOW-01-BLACK-S',
    ]);

    $this->actingAs($this->user)
        ->get(route('items.duplicate', $item))
        ->assertNotFound();
});

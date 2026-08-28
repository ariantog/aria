<?php

use App\Models\Item;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = User::factory()->create();
    $role = Role::create(['name' => 'Super Admin']);
    $this->user->assignRole($role);
});

test('items index page can be rendered', function () {
    $response = $this->actingAs($this->user)
        ->get(route('items.index'));

    $response->assertStatus(200);
});

test('items index hides redundant kode produksi and product columns', function () {
    $group = \App\Models\ItemGroup::factory()->create(['name' => 'UNIQUE GROUP TITLE']);
    $item = Item::factory()->create([
        'group_id' => $group->id,
        'name' => 'DISPLAY NAME - NAVY - S',
        'code' => 'AJD-FILTER-COL-S',
        'pcode' => 'FILTER-COL',
    ]);

    $this->actingAs($this->user)
        ->get(route('items.index'))
        ->assertOk()
        ->assertSee('DISPLAY NAME - NAVY - S', false)
        ->assertSee('AJD-FILTER-COL-S', false)
        ->assertDontSee('>Kode Produksi<', false)
        ->assertDontSee('>Product<', false)
        ->assertDontSee('UNIQUE GROUP TITLE', false);
});

test('items index code filter matches sku legacy alias and numeric barcode id', function () {
    $bySku = Item::factory()->create(['code' => 'AJD-SKU-MATCH-S', 'legacy_code' => null]);
    $byLegacy = Item::factory()->create(['code' => 'AJD-NEW-SKU-S', 'legacy_code' => 'OLD-BARCODE-SKU']);
    $byId = Item::factory()->create(['code' => 'AJD-ID-MATCH-S', 'legacy_code' => null]);

    $this->actingAs($this->user)
        ->get(route('items.index', ['code' => 'AJD-SKU-MATCH']))
        ->assertOk()
        ->assertSee('AJD-SKU-MATCH-S', false)
        ->assertDontSee('AJD-NEW-SKU-S', false)
        ->assertDontSee('AJD-ID-MATCH-S', false);

    $this->actingAs($this->user)
        ->get(route('items.index', ['code' => 'OLD-BARCODE']))
        ->assertOk()
        ->assertSee('AJD-NEW-SKU-S', false)
        ->assertDontSee('AJD-SKU-MATCH-S', false);

    $this->actingAs($this->user)
        ->get(route('items.index', ['code' => (string) $byId->id]))
        ->assertOk()
        ->assertSee('AJD-ID-MATCH-S', false)
        ->assertDontSee('AJD-SKU-MATCH-S', false);
});

test('items index display name filter searches item name not group product title', function () {
    $group = \App\Models\ItemGroup::factory()->create(['name' => 'GROUP PRODUCT ONLY']);
    $match = Item::factory()->create([
        'group_id' => $group->id,
        'name' => 'DISPLAY FILTER MATCH - BLUE - M',
        'code' => 'AJD-DISPLAY-FILTER-M',
    ]);
    $other = Item::factory()->create([
        'name' => 'OTHER ITEM - RED - L',
        'code' => 'AJD-OTHER-FILTER-L',
    ]);

    $this->actingAs($this->user)
        ->get(route('items.index', ['name' => 'DISPLAY FILTER MATCH']))
        ->assertOk()
        ->assertSee('AJD-DISPLAY-FILTER-M', false)
        ->assertDontSee('AJD-OTHER-FILTER-L', false);

    $this->actingAs($this->user)
        ->get(route('items.index', ['name' => 'GROUP PRODUCT ONLY']))
        ->assertOk()
        ->assertDontSee('AJD-DISPLAY-FILTER-M', false);
});

test('items create page can be rendered', function () {
    $response = $this->actingAs($this->user)
        ->get(route('items.create'));

    $response->assertStatus(200);
});

test('items show page links group and tags to filtered lists', function () {
    $typeTag = \App\Models\Tag::factory()->create([
        'type' => \App\Models\Tag::TYPE_TYPE,
        'item_type' => \App\Enums\ItemType::ITEM->value,
        'code' => 'AJD',
        'name' => 'Jacket',
    ]);
    $warnaTag = \App\Models\Tag::factory()->create([
        'type' => \App\Models\Tag::TYPE_WARNA,
        'code' => 'NAVY',
        'name' => 'NAVY',
    ]);
    $group = \App\Models\ItemGroup::factory()->create([
        'master' => 'CX93024',
        'variant' => '05',
        'name' => 'RUNNING SHIRT',
    ]);
    $item = Item::factory()->create([
        'group_id' => $group->id,
        'code' => 'AJD-CX93024-05-S',
        'pcode' => 'CX93024-05',
    ]);
    $item->tags()->attach([$typeTag->id, $warnaTag->id]);

    $builder = app(\App\Services\Items\ItemIdentityBuilder::class);
    $groupUrl = route('items.group-parent-detail', $builder->parentKeyToSlug($builder->itemParentKey($item->load('tags', 'group'))));

    $this->actingAs($this->user)
        ->get(route('items.show', $item))
        ->assertOk()
        ->assertSee($groupUrl, false)
        ->assertSee($warnaTag->itemsIndexFilterUrl($item->type), false)
        ->assertSee($typeTag->itemsIndexFilterUrl($item->type), false);
});

test('items edit page can be rendered', function () {
    $item = Item::factory()->create();

    $response = $this->actingAs($this->user)
        ->get(route('items.edit', $item));

    $response->assertStatus(200);
});

test('items json lookup resolves barcode by numeric item id', function () {
    $item = Item::factory()->create([
        'name' => 'Scanned SKU',
        'code' => 'AJD-TEST-01-S',
        'price' => 125_000,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/items?id='.$item->id.'&json=1');

    $response->assertSuccessful();
    $payload = $response->json();
    expect($payload)->toHaveCount(1)
        ->and($payload[0]['id'])->toBe($item->id)
        ->and($payload[0]['name'])->toBe('Scanned SKU')
        ->and($payload[0]['code'])->toBe('AJD-TEST-01-S');
});

test('items json lookup returns empty array for unknown id', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/items?id=999999&json=1');

    $response->assertSuccessful();
    expect($response->json())->toBe([]);
});

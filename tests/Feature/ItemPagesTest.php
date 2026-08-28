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

test('items index shows database columns and collapsible filters', function () {
    $item = Item::factory()->create([
        'name' => 'DISPLAY NAME - NAVY - S',
        'code' => 'AJD-FILTER-COL-S',
        'description' => 'Item description text',
        'description2' => 'Internal note',
    ]);

    $this->actingAs($this->user)
        ->get(route('items.index'))
        ->assertOk()
        ->assertSee('>Barcode<', false)
        ->assertSee('>Code<', false)
        ->assertSee('>Name<', false)
        ->assertSee('>Desc<', false)
        ->assertSee((string) $item->id, false)
        ->assertSee('AJD-FILTER-COL-S', false)
        ->assertSee('DISPLAY NAME - NAVY - S', false)
        ->assertSee('Item description text', false)
        ->assertSee('Internal note', false)
        ->assertSee('data-testid="items-index-filters-toggle"', false)
        ->assertSee('aria-items-index-filters-open', false);
});

test('items index barcode filter matches id and code', function () {
    $byCode = Item::factory()->create(['code' => 'AJD-BARCODE-MATCH-S', 'legacy_code' => null]);
    $byId = Item::factory()->create(['code' => 'AJD-ID-BARCODE-S', 'legacy_code' => null]);
    $other = Item::factory()->create(['code' => 'AJD-OTHER-BARCODE-S', 'legacy_code' => 'OLD-BARCODE-SKU']);

    $this->actingAs($this->user)
        ->get(route('items.index', ['barcode' => 'AJD-BARCODE-MATCH']))
        ->assertOk()
        ->assertSee('AJD-BARCODE-MATCH-S', false)
        ->assertDontSee('AJD-ID-BARCODE-S', false)
        ->assertDontSee('AJD-OTHER-BARCODE-S', false);

    $this->actingAs($this->user)
        ->get(route('items.index', ['barcode' => (string) $byId->id]))
        ->assertOk()
        ->assertSee('AJD-ID-BARCODE-S', false)
        ->assertDontSee('AJD-BARCODE-MATCH-S', false);
});

test('items index code filter matches code and legacy_code only', function () {
    $byCode = Item::factory()->create(['code' => 'AJD-CODE-MATCH-S', 'legacy_code' => null]);
    $byLegacy = Item::factory()->create(['code' => 'AJD-NEW-SKU-S', 'legacy_code' => 'OLD-LEGACY-SKU']);
    $other = Item::factory()->create(['code' => 'AJD-OTHER-CODE-S', 'legacy_code' => null]);

    $this->actingAs($this->user)
        ->get(route('items.index', ['code' => 'AJD-CODE-MATCH']))
        ->assertOk()
        ->assertSee('AJD-CODE-MATCH-S', false)
        ->assertDontSee('AJD-NEW-SKU-S', false)
        ->assertDontSee('AJD-OTHER-CODE-S', false);

    $this->actingAs($this->user)
        ->get(route('items.index', ['code' => 'OLD-LEGACY']))
        ->assertOk()
        ->assertSee('AJD-NEW-SKU-S', false)
        ->assertDontSee('AJD-CODE-MATCH-S', false);

    $this->actingAs($this->user)
        ->get(route('items.index', ['code' => (string) $other->id]))
        ->assertOk()
        ->assertDontSee('AJD-OTHER-CODE-S', false);
});

test('items index name filter searches item name', function () {
    $match = Item::factory()->create([
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
});

test('items index desc filter searches item description only', function () {
    $group = \App\Models\ItemGroup::factory()->create(['description' => 'GROUP DESCRIPTION ONLY']);
    $match = Item::factory()->create([
        'group_id' => $group->id,
        'code' => 'AJD-DESC-FILTER-M',
        'description' => 'ITEM DESCRIPTION MATCH',
    ]);
    $other = Item::factory()->create([
        'code' => 'AJD-DESC-OTHER-M',
        'description' => 'OTHER DESCRIPTION',
    ]);

    $this->actingAs($this->user)
        ->get(route('items.index', ['desc' => 'ITEM DESCRIPTION MATCH']))
        ->assertOk()
        ->assertSee('AJD-DESC-FILTER-M', false)
        ->assertDontSee('AJD-DESC-OTHER-M', false);

    $this->actingAs($this->user)
        ->get(route('items.index', ['desc' => 'GROUP DESCRIPTION ONLY']))
        ->assertOk()
        ->assertDontSee('AJD-DESC-FILTER-M', false);
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

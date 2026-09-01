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
        ->assertSee('>Barcode / Code<', false)
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

test('items index lookup filter matches id code and legacy_code substrings', function () {
    $byCode = Item::factory()->create(['code' => 'AJD-CX90151-01-M', 'legacy_code' => null]);
    $byLegacy = Item::factory()->create(['code' => 'AJD-NEW-SKU-S', 'legacy_code' => 'OLD-90151-LEGACY']);
    $byId = Item::factory()->create(['code' => 'AJD-ID-LOOKUP-S', 'legacy_code' => null]);
    $other = Item::factory()->create(['code' => 'AJD-OTHER-CODE-S', 'legacy_code' => null]);

    $this->actingAs($this->user)
        ->get(route('items.index', ['code' => '90151']))
        ->assertOk()
        ->assertSee('AJD-CX90151-01-M', false)
        ->assertSee('AJD-NEW-SKU-S', false)
        ->assertDontSee('AJD-OTHER-CODE-S', false);

    $this->actingAs($this->user)
        ->get(route('items.index', ['code' => (string) $byId->id]))
        ->assertOk()
        ->assertSee('AJD-ID-LOOKUP-S', false)
        ->assertDontSee('AJD-CX90151-01-M', false);
});

test('items index name filter searches item name and group name', function () {
    $group = \App\Models\ItemGroup::factory()->create(['name' => 'GROUP TITLE MATCH']);
    Item::factory()->create([
        'group_id' => $group->id,
        'name' => 'DISPLAY FILTER MATCH - BLUE - M',
        'code' => 'AJD-DISPLAY-FILTER-M',
    ]);
    Item::factory()->create([
        'group_id' => \App\Models\ItemGroup::factory()->create(['name' => 'GROUP TITLE MATCH ONLY']),
        'name' => 'OTHER ITEM NAME - RED - L',
        'code' => 'AJD-GROUP-FILTER-L',
    ]);
    Item::factory()->create([
        'name' => 'OTHER ITEM - RED - L',
        'code' => 'AJD-OTHER-FILTER-L',
    ]);

    $this->actingAs($this->user)
        ->get(route('items.index', ['name' => 'DISPLAY FILTER MATCH']))
        ->assertOk()
        ->assertSee('AJD-DISPLAY-FILTER-M', false)
        ->assertDontSee('AJD-OTHER-FILTER-L', false);

    $this->actingAs($this->user)
        ->get(route('items.index', ['name' => 'GROUP TITLE MATCH ONLY']))
        ->assertOk()
        ->assertSee('AJD-GROUP-FILTER-L', false)
        ->assertDontSee('AJD-DISPLAY-FILTER-M', false);
});

test('items index name filter finds item by group product title energy', function () {
    $group = \App\Models\ItemGroup::factory()->create(['name' => 'ENERGY']);
    Item::factory()->create([
        'group_id' => $group->id,
        'name' => 'hj00022',
        'code' => 'AJD-ENERGY-HJ-M',
    ]);
    Item::factory()->create([
        'name' => 'other-item',
        'code' => 'AJD-OTHER-ITEM-M',
    ]);

    $this->actingAs($this->user)
        ->get(route('items.index', ['name' => 'energy']))
        ->assertOk()
        ->assertSee('AJD-ENERGY-HJ-M', false)
        ->assertDontSee('AJD-OTHER-ITEM-M', false);
});

test('items index name filter searches group alias when product title is stored there', function () {
    if (! \Illuminate\Support\Facades\Schema::hasColumn('item_group', 'alias')) {
        \Illuminate\Support\Facades\Schema::table('item_group', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->string('alias')->nullable();
        });
    }

    $group = \App\Models\ItemGroup::factory()->create(['name' => 'HJ00022']);
    \Illuminate\Support\Facades\DB::table('item_group')
        ->where('id', $group->id)
        ->update(['alias' => 'energy']);

    Item::factory()->create([
        'group_id' => $group->id,
        'name' => 'hj00022',
        'code' => 'AJD-ENERGY-ALIAS-M',
    ]);

    $this->actingAs($this->user)
        ->get(route('items.index', ['name' => 'energy']))
        ->assertOk()
        ->assertSee('AJD-ENERGY-ALIAS-M', false);
});

test('getItemName prefers non-empty group alias for manufactured items', function () {
    $group = \App\Models\ItemGroup::factory()->make(['name' => 'GROUP PRODUCT NAME']);
    $group->setRawAttributes(array_merge($group->getAttributes(), ['alias' => 'GROUP ALIAS NAME']));

    $item = Item::factory()->make([
        'name' => 'ITEM DISPLAY NAME - NAVY - S',
        'type' => \App\Enums\ItemType::ITEM,
    ]);
    $item->setRelation('group', $group);

    expect($item->getItemName())->toBe('GROUP ALIAS NAME');
});

test('items index name column prefers group alias when available', function () {
    if (! \Illuminate\Support\Facades\Schema::hasColumn('item_group', 'alias')) {
        return;
    }

    $group = \App\Models\ItemGroup::factory()->create(['name' => 'GROUP PRODUCT NAME']);
    \Illuminate\Support\Facades\DB::table('item_group')
        ->where('id', $group->id)
        ->update(['alias' => 'GROUP ALIAS NAME']);

    Item::factory()->create([
        'group_id' => $group->id,
        'name' => 'ITEM DISPLAY NAME - NAVY - S',
        'code' => 'AJD-ALIAS-DISPLAY-S',
    ]);

    $this->actingAs($this->user)
        ->get(route('items.index'))
        ->assertOk()
        ->assertSee('GROUP ALIAS NAME', false)
        ->assertDontSee('ITEM DISPLAY NAME - NAVY - S', false);
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

test('asset edit form shows the bare product title not the unique group name', function () {
    $group = \App\Models\ItemGroup::factory()->create([
        'master' => 'ELBOWSUPPORT-02',
        'variant' => 'BLACKWHITE',
        'name' => 'ELBOW STRAP - BLACKWHITE (ELBOWSUPPORT-02)',
    ]);
    $item = Item::factory()->create([
        'type' => \App\Enums\ItemType::ASSET_LANCAR,
        'group_id' => $group->id,
        'pcode' => 'ELBOWSUPPORT-02',
        'code' => 'ELBOWSUPPORT-02-BLACKWHITE',
        'name' => 'ELBOW STRAP - BLACKWHITE',
    ]);

    $this->actingAs($this->user)
        ->get(route('assetlancar.edit', $item))
        ->assertOk()
        ->assertSee('value="ELBOW STRAP"', false)
        ->assertDontSee('ELBOW STRAP - BLACKWHITE (ELBOWSUPPORT-02) - BLACKWHITE', false);
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

<?php

use App\Enums\ItemBrand;
use App\Enums\ItemType;
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
    $group = \App\Models\ItemGroup::factory()->create([
        'description' => 'Item description text',
        'description2' => 'Internal note',
    ]);
    $item = Item::factory()->create([
        'group_id' => $group->id,
        'name' => 'DISPLAY NAME - NAVY - S',
        'code' => 'AJD-FILTER-COL-S',
        'description' => 'Stale item description',
        'description2' => 'Stale item note',
    ]);

    $this->actingAs($this->user)
        ->get(route('items.index'))
        ->assertOk()
        ->assertSee('>Barcode / Code<', false)
        ->assertSee('>Group<', false)
        ->assertSee('>Name<', false)
        ->assertSee('>Desc<', false)
        ->assertSee((string) $item->id, false)
        ->assertSee('data-testid="item-list-group-'.$item->id.'"', false)
        ->assertSee($item->fresh(['group', 'tags'])->groupParentUrl(), false)
        ->assertSee('AJD-FILTER-COL-S', false)
        ->assertSee('DISPLAY NAME - NAVY - S', false)
        ->assertSee('Item description text', false)
        ->assertSee('Internal note', false)
        ->assertDontSee('Stale item description', false)
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

test('item and asset lancar code filter treats percent as a like wildcard', function (string $routeName, ItemType $type) {
    Item::factory()->create([
        'type' => $type,
        'code' => 'CX00122-RED-03',
        'legacy_code' => null,
    ]);
    Item::factory()->create([
        'type' => $type,
        'code' => 'CX00122-RED-01',
        'legacy_code' => null,
    ]);
    Item::factory()->create([
        'type' => $type,
        'code' => 'ZZ99999-03',
        'legacy_code' => 'OLDCX00122-LEGACY-01',
    ]);
    Item::factory()->create([
        'type' => $type,
        'code' => 'NEW-SKU-AFTER-CONVERT',
        'legacy_code' => 'OLDCX00122-NAVY-03',
    ]);

    $this->actingAs($this->user)
        ->get(route($routeName, ['code' => 'cx00122%03']))
        ->assertOk()
        ->assertSee('CX00122-RED-03', false)
        ->assertSee('NEW-SKU-AFTER-CONVERT', false)
        ->assertDontSee('CX00122-RED-01', false)
        ->assertDontSee('ZZ99999-03', false);
})->with([
    'items' => ['items.index', ItemType::ITEM],
    'assetlancar' => ['assetlancar.index', ItemType::ASSET_LANCAR],
]);

test('item and asset lancar name filter treats percent as a like wildcard', function (string $routeName, ItemType $type) {
    Item::factory()->create([
        'type' => $type,
        'name' => 'ELBOW STRAP BLACK 03',
        'code' => 'AL-NAME-MATCH-03',
    ]);
    Item::factory()->create([
        'type' => $type,
        'name' => 'ELBOW STRAP BLACK',
        'code' => 'AL-NAME-PARTIAL',
    ]);
    Item::factory()->create([
        'type' => $type,
        'name' => 'OTHER PRODUCT 03',
        'code' => 'AL-NAME-OTHER-03',
    ]);

    $this->actingAs($this->user)
        ->get(route($routeName, ['name' => 'elbow%03']))
        ->assertOk()
        ->assertSee('AL-NAME-MATCH-03', false)
        ->assertDontSee('AL-NAME-PARTIAL', false)
        ->assertDontSee('AL-NAME-OTHER-03', false);
})->with([
    'items' => ['items.index', ItemType::ITEM],
    'assetlancar' => ['assetlancar.index', ItemType::ASSET_LANCAR],
]);

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

test('catalogDescription prefers item_group description over items.description', function () {
    $group = \App\Models\ItemGroup::factory()->make([
        'description' => 'MIKRO MOTIF CAMO HIJAU',
        'description2' => 'GROUP NB',
    ]);
    $item = Item::factory()->make([
        'group_id' => 24961,
        'description' => 'MIKRO MOTIF HIJAU',
        'description2' => 'ITEM NB',
        'type' => ItemType::ITEM,
    ]);
    $item->setRelation('group', $group);

    expect($item->catalogDescription())->toBe('MIKRO MOTIF CAMO HIJAU')
        ->and($item->catalogDescription2())->toBe('GROUP NB');
});

test('catalogDescription falls back to the item column when there is no group', function () {
    $item = Item::factory()->make([
        'group_id' => 0,
        'description' => 'UNGROUPED DESC',
        'description2' => 'UNGROUPED NB',
    ]);
    $item->setRelation('group', null);

    expect($item->catalogDescription())->toBe('UNGROUPED DESC')
        ->and($item->catalogDescription2())->toBe('UNGROUPED NB');
});

test('catalogBrand and catalogGenre prefer group values when they are set', function () {
    $group = \App\Models\ItemGroup::factory()->make([
        'brand' => \App\Enums\ItemBrand::CX9,
        'genre' => 6480,
    ]);
    $item = Item::factory()->make([
        'group_id' => 24961,
        'brand' => \App\Enums\ItemBrand::NO_BRAND,
        'genre' => 12,
    ]);
    $item->setRelation('group', $group);

    expect($item->catalogBrand())->toBe(\App\Enums\ItemBrand::CX9)
        ->and($item->catalogGenre())->toBe(6480);
});

test('catalogBrand falls back to the item column when the group brand is empty', function () {
    $group = \App\Models\ItemGroup::factory()->make([
        'brand' => \App\Enums\ItemBrand::NO_BRAND,
        'genre' => 0,
    ]);
    $item = Item::factory()->make([
        'group_id' => 24961,
        'brand' => \App\Enums\ItemBrand::CX0,
        'genre' => 99,
    ]);
    $item->setRelation('group', $group);

    expect($item->catalogBrand())->toBe(\App\Enums\ItemBrand::CX0)
        ->and($item->catalogGenre())->toBe(99);
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

test('items index name column shows the item display name not the group alias', function () {
    if (! \Illuminate\Support\Facades\Schema::hasColumn('item_group', 'alias')) {
        \Illuminate\Support\Facades\Schema::table('item_group', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->string('alias')->nullable();
        });
    }

    $group = \App\Models\ItemGroup::factory()->create(['name' => 'GROUP PRODUCT NAME']);
    \Illuminate\Support\Facades\DB::table('item_group')
        ->where('id', $group->id)
        ->update(['alias' => 'UNBEATABLE DUAL LAYER SHORTS - BLACK']);

    $item = Item::factory()->create([
        'group_id' => $group->id,
        'name' => 'UNBEATABLE DUAL LAYER SHORTS - GREEN - XL',
        'code' => 'CLN-CX90113-05-XL',
    ]);

    $this->actingAs($this->user)
        ->get(route('items.index'))
        ->assertOk()
        ->assertSee('UNBEATABLE DUAL LAYER SHORTS - GREEN - XL', false)
        ->assertDontSee('UNBEATABLE DUAL LAYER SHORTS - BLACK', false)
        ->assertSee('CLN-CX90113-05-XL', false)
        ->assertDontSee('CLN-CX90113-05...', false)
        ->assertSee('data-testid="item-list-name-'.$item->id.'"', false)
        ->assertSee('data-testid="item-list-code-'.$item->id.'"', false);
});

test('asset lancar index shows the full sku without truncating the code column', function () {
    $item = Item::factory()->create([
        'type' => \App\Enums\ItemType::ASSET_LANCAR,
        'name' => 'ELBOW STRAP - BLACKWHITE',
        'code' => 'ELBOWSUPPORT-02-BLACKWHITE',
        'pcode' => 'ELBOWSUPPORT-02',
    ]);

    $this->actingAs($this->user)
        ->get(route('assetlancar.index'))
        ->assertOk()
        ->assertSee('ELBOWSUPPORT-02-BLACKWHITE', false)
        ->assertSee('ELBOW STRAP - BLACKWHITE', false)
        ->assertSee('data-testid="item-list-code-'.$item->id.'"', false)
        ->assertDontSee('ELBOWSUPPORT-02-BLACKW...', false);
});

test('items index desc filter searches group description for grouped skus', function () {
    $group = \App\Models\ItemGroup::factory()->create(['description' => 'MIKRO MOTIF CAMO HIJAU']);
    Item::factory()->create([
        'group_id' => $group->id,
        'code' => 'AJD-DESC-FILTER-M',
        'description' => 'PUTIH LEGACY ITEM TEXT',
    ]);
    Item::factory()->create([
        'group_id' => \App\Models\ItemGroup::factory()->create(['description' => 'OTHER GROUP DESC']),
        'code' => 'AJD-DESC-OTHER-M',
        'description' => 'OTHER DESCRIPTION',
    ]);

    $this->actingAs($this->user)
        ->get(route('items.index', ['desc' => 'MIKRO MOTIF CAMO HIJAU']))
        ->assertOk()
        ->assertSee('AJD-DESC-FILTER-M', false)
        ->assertDontSee('AJD-DESC-OTHER-M', false);

    $this->actingAs($this->user)
        ->get(route('items.index', ['desc' => 'PUTIH LEGACY ITEM TEXT']))
        ->assertOk()
        ->assertDontSee('AJD-DESC-FILTER-M', false);
});

test('items index brand filter matches item_group.brand when the item mirror is stale', function () {
    $group = \App\Models\ItemGroup::factory()->create(['brand' => ItemBrand::CX9]);
    Item::factory()->create([
        'group_id' => $group->id,
        'code' => 'AJD-BRAND-FILTER-S',
        'brand' => ItemBrand::NO_BRAND,
    ]);
    Item::factory()->create([
        'code' => 'AJD-BRAND-OTHER-S',
        'brand' => ItemBrand::HJ,
    ]);

    $this->actingAs($this->user)
        ->get(route('items.index', ['brand' => ItemBrand::CX9->value]))
        ->assertOk()
        ->assertSee('AJD-BRAND-FILTER-S', false)
        ->assertDontSee('AJD-BRAND-OTHER-S', false);
});

test('items index desc filter searches item description when the sku has no group', function () {
    Item::factory()->create([
        'group_id' => null,
        'code' => 'AJD-UNGROUPED-DESC-M',
        'description' => 'UNGROUPED ITEM DESC',
    ]);
    Item::factory()->create([
        'group_id' => \App\Models\ItemGroup::factory()->create(['description' => 'GROUPED DESC']),
        'code' => 'AJD-GROUPED-DESC-M',
        'description' => 'UNGROUPED ITEM DESC',
    ]);

    $this->actingAs($this->user)
        ->get(route('items.index', ['desc' => 'UNGROUPED ITEM DESC']))
        ->assertOk()
        ->assertSee('AJD-UNGROUPED-DESC-M', false)
        ->assertDontSee('AJD-GROUPED-DESC-M', false);
});

test('items create page can be rendered', function () {
    $response = $this->actingAs($this->user)
        ->get(route('items.create'));

    $response->assertStatus(200);
});

test('item show and edit use the group description when it differs from the item column', function () {
    $group = \App\Models\ItemGroup::factory()->create([
        'master' => 'CX00122',
        'variant' => '04',
        'name' => 'CX00122/04',
        'description' => 'MIKRO MOTIF CAMO HIJAU',
    ]);
    $item = Item::factory()->create([
        'group_id' => $group->id,
        'code' => 'CLNCX0012204S',
        'pcode' => 'CX00122/04',
        'name' => 'CLN CX00122/04 S',
        'description' => 'MIKRO MOTIF HIJAU',
    ]);

    $this->actingAs($this->user)
        ->get(route('items.show', $item))
        ->assertOk()
        ->assertSee('data-testid="item-catalog-description"', false)
        ->assertSee('MIKRO MOTIF CAMO HIJAU', false)
        ->assertDontSee('>MIKRO MOTIF HIJAU<', false);

    $this->actingAs($this->user)
        ->get(route('items.edit', $item))
        ->assertOk()
        ->assertSee('>MIKRO MOTIF CAMO HIJAU<', false)
        ->assertDontSee('>MIKRO MOTIF HIJAU<', false);
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

test('item create and edit forms mark shared colorway attributes', function () {
    $item = Item::factory()->create();

    $this->actingAs($this->user)
        ->get(route('items.create'))
        ->assertOk()
        ->assertSee('data-testid="item-form-shared-attributes"', false)
        ->assertSee('data-testid="item-form-shared-details"', false)
        ->assertSee('Shared across this colorway', false)
        ->assertSee('This size only', false)
        ->assertSee('group name - color - size', false)
        ->assertDontSee('each row keeps its own price', false);

    $this->actingAs($this->user)
        ->get(route('items.edit', $item))
        ->assertOk()
        ->assertSee('data-testid="item-form-shared-attributes"', false)
        ->assertSee('data-testid="item-form-shared-details"', false)
        ->assertSee('data-testid="item-form-shared-tags"', false)
        ->assertSee('Shared across this colorway', false)
        ->assertSee('This size only', false);
});

test('asset edit form shows the bare product title not the unique group name', function () {
    $group = \App\Models\ItemGroup::factory()->create([
        'master' => 'ELBOWSUPPORT-02',
        'variant' => 'BLACKWHITE',
        'name' => 'ELBOW STRAP - BLACKWHITE (ELBOWSUPPORT-02)',
    ]);
    $typeTag = \App\Models\Tag::factory()->create([
        'type' => \App\Models\Tag::TYPE_TYPE,
        'item_type' => \App\Enums\ItemType::ASSET_LANCAR->value,
        'code' => 'ELBOW',
        'name' => 'Elbow',
    ]);
    $warnaTag = \App\Models\Tag::factory()->create([
        'type' => \App\Models\Tag::TYPE_WARNA,
        'code' => 'BLACKWHITE',
        'name' => 'BLACKWHITE',
    ]);
    $sizeTag = \App\Models\Tag::factory()->create([
        'type' => \App\Models\Tag::TYPE_SIZE,
        'code' => 'AS',
        'name' => 'All Size',
    ]);
    $item = Item::factory()->create([
        'type' => \App\Enums\ItemType::ASSET_LANCAR,
        'group_id' => $group->id,
        'pcode' => 'ELBOWSUPPORT-02',
        'code' => 'ELBOWSUPPORT-02-BLACKWHITE',
        'name' => 'ELBOW STRAP - BLACKWHITE',
    ]);
    $item->tags()->attach([$typeTag->id, $warnaTag->id, $sizeTag->id]);

    $this->actingAs($this->user)
        ->get(route('assetlancar.edit', $item))
        ->assertOk()
        ->assertSee('data-testid="tag-filter-warna"', false)
        ->assertSee('data-testid="tag-picker-warna"', false)
        ->assertSee('data-testid="tag-filter-type"', false)
        ->assertSee('data-testid="tag-picker-type"', false)
        ->assertSee('data-testid="tag-filter-size"', false)
        ->assertSee('data-testid="tag-picker-size"', false)
        ->assertSee('data-tag-selected="1"', false)
        ->assertSee('checked', false)
        ->assertSee('value="ELBOW STRAP"', false)
        ->assertDontSee('ELBOW STRAP - BLACKWHITE (ELBOWSUPPORT-02) - BLACKWHITE', false);
});

test('manufactured item edit form pins selected tag pickers', function () {
    $typeTag = \App\Models\Tag::factory()->create([
        'type' => \App\Models\Tag::TYPE_TYPE,
        'item_type' => \App\Enums\ItemType::ITEM->value,
        'code' => 'AJD',
        'name' => 'Jacket',
    ]);
    $warnaTag = \App\Models\Tag::factory()->create([
        'type' => \App\Models\Tag::TYPE_WARNA,
        'code' => '23',
        'name' => '23',
    ]);
    $jahitTag = \App\Models\Tag::factory()->create([
        'type' => \App\Models\Tag::TYPE_JAHIT,
        'code' => 'J1',
        'name' => 'Jahit 1',
    ]);
    $sizeTag = \App\Models\Tag::factory()->create([
        'type' => \App\Models\Tag::TYPE_SIZE,
        'code' => 'S',
        'name' => 'Small',
    ]);
    $item = Item::factory()->create([
        'type' => \App\Enums\ItemType::ITEM,
        'pcode' => 'CX93024-23',
        'code' => 'AJD-CX93024-23-S',
    ]);
    $item->tags()->attach([$typeTag->id, $warnaTag->id, $jahitTag->id, $sizeTag->id]);

    $this->actingAs($this->user)
        ->get(route('items.edit', $item))
        ->assertOk()
        ->assertSee('data-testid="tag-filter-jahit"', false)
        ->assertSee('data-testid="tag-picker-jahit"', false)
        ->assertSee('data-tag-selected="1"', false)
        ->assertSee('name="tags[jahit]"', false)
        ->assertSee('name="tags[types]"', false);
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

test('distinctLegacyCode hides empty or duplicate sku', function () {
    expect(Item::factory()->make(['code' => 'NEW-SKU', 'legacy_code' => 'OLD-SKU'])->distinctLegacyCode())->toBe('OLD-SKU')
        ->and(Item::factory()->make(['code' => 'NEW-SKU', 'legacy_code' => null])->distinctLegacyCode())->toBeNull()
        ->and(Item::factory()->make(['code' => 'NEW-SKU', 'legacy_code' => ''])->distinctLegacyCode())->toBeNull()
        ->and(Item::factory()->make(['code' => 'NEW-SKU', 'legacy_code' => '  '])->distinctLegacyCode())->toBeNull()
        ->and(Item::factory()->make(['code' => 'NEW-SKU', 'legacy_code' => 'NEW-SKU'])->distinctLegacyCode())->toBeNull()
        ->and(Item::factory()->make(['code' => 'new-sku', 'legacy_code' => 'NEW-SKU'])->distinctLegacyCode())->toBeNull();
});

test('item and assetlancar show pages display a distinct legacy code', function (string $routeName, ItemType $type) {
    $item = Item::factory()->create([
        'type' => $type,
        'code' => 'NEW-SKU-01',
        'legacy_code' => 'OLD-SKU-01',
    ]);

    $this->actingAs($this->user)
        ->get(route($routeName, $item))
        ->assertOk()
        ->assertSee('SKU Reference', false)
        ->assertSee('NEW-SKU-01', false)
        ->assertSee('Legacy Code', false)
        ->assertSee('data-testid="item-legacy-code"', false)
        ->assertSee('OLD-SKU-01', false);
})->with([
    'items' => ['items.show', ItemType::ITEM],
    'assetlancar' => ['assetlancar.show', ItemType::ASSET_LANCAR],
]);

test('item and assetlancar show pages hide legacy code when it is empty', function (string $routeName, ItemType $type) {
    $item = Item::factory()->create([
        'type' => $type,
        'code' => 'ONLY-CURRENT-SKU',
        'legacy_code' => null,
    ]);

    $this->actingAs($this->user)
        ->get(route($routeName, $item))
        ->assertOk()
        ->assertSee('ONLY-CURRENT-SKU', false)
        ->assertSee('SKU Reference', false)
        ->assertDontSee('data-testid="item-legacy-code"', false)
        ->assertDontSee('Legacy Code', false);
})->with([
    'items' => ['items.show', ItemType::ITEM],
    'assetlancar' => ['assetlancar.show', ItemType::ASSET_LANCAR],
]);

test('item and assetlancar show pages hide legacy code when it matches the current sku', function (string $routeName, ItemType $type) {
    $item = Item::factory()->create([
        'type' => $type,
        'code' => 'SAME-SKU-01',
        'legacy_code' => 'SAME-SKU-01',
    ]);

    $this->actingAs($this->user)
        ->get(route($routeName, $item))
        ->assertOk()
        ->assertSee('SAME-SKU-01', false)
        ->assertDontSee('data-testid="item-legacy-code"', false)
        ->assertDontSee('Legacy Code', false);
})->with([
    'items' => ['items.show', ItemType::ITEM],
    'assetlancar' => ['assetlancar.show', ItemType::ASSET_LANCAR],
]);

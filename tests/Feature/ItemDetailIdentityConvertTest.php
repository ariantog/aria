<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use App\Models\User;
use App\Services\Items\LegacyItemConverterService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'items-convert-legacy', 'guard_name' => 'web']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('items-convert-legacy');

    Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'S', 'name' => 'S']);
    Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLACK', 'name' => 'BLACK']);
    Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'AS', 'name' => 'All Size']);
});

function makeLegacyAssetItem(string $code, ?string $name = null): Item
{
    return Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'group_id' => null,
        'code' => $code,
        'legacy_code' => null,
        'pcode' => implode('-', array_slice(explode('-', $code), 0, 2)),
        'name' => $name ?? $code,
    ]);
}

it('shows convert panel on asset lancar detail for ungrouped legacy sku', function () {
    $item = makeLegacyAssetItem('GLOVE-01-BLACK-S', 'BOXING GLOVE - BLACK - S');

    $this->actingAs($this->user)
        ->get(route('assetlancar.show', $item))
        ->assertOk()
        ->assertSee('Legacy SKU Conversion', false)
        ->assertSee('Convert to new SKU', false)
        ->assertSee('GLOVE-01-BLACK-S', false);
});

it('hides convert panel when item is already fully canonical', function () {
    $gloveType = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ASSET_LANCAR->value,
        'code' => 'GLOVE',
        'name' => 'Glove',
    ]);

    $group = \App\Models\ItemGroup::factory()->create([
        'master' => 'GLOVE-01',
        'variant' => 'BLACK',
        'name' => 'BOXING GLOVE - BLACK',
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'group_id' => $group->id,
        'code' => 'GLOVE-01-BLACK-S',
        'pcode' => 'GLOVE-01',
        'name' => 'BOXING GLOVE - BLACK - S',
        'genre' => $gloveType->id,
    ]);
    $item->tags()->sync([
        $gloveType->id,
        Tag::where('code', 'BLACK')->first()->id,
        Tag::where('code', 'S')->first()->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('assetlancar.show', $item))
        ->assertOk()
        ->assertDontSee('Legacy SKU Conversion', false);
});

it('shows convert panel when group link exists but asset type tag is missing', function () {
    $group = \App\Models\ItemGroup::factory()->create([
        'master' => 'GLOVE-07',
        'variant' => 'BLACK',
        'name' => 'MICROFIBER STRAP GYM GLOVE - BLACK',
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'group_id' => $group->id,
        'code' => 'GLOVE-07-BLACK-S',
        'pcode' => 'GLOVE-07',
        'name' => 'MICROFIBER STRAP GYM GLOVE - BLACK - S',
        'genre' => 0,
    ]);
    $item->tags()->sync([
        Tag::where('code', 'BLACK')->first()->id,
        Tag::where('code', 'S')->first()->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('assetlancar.show', $item))
        ->assertOk()
        ->assertSee('Legacy SKU Conversion', false)
        ->assertSee('Convert to new SKU', false)
        ->assertSee('missing the asset TYPE tag', false);
});

it('shows convert panel without convert permission but blocks the button', function () {
    Permission::firstOrCreate(['name' => 'assetLancar-list', 'guard_name' => 'web']);
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('assetLancar-list');

    $item = makeLegacyAssetItem('GLOVE-07-BLACK-S', 'MICROFIBER STRAP GYM GLOVE - BLACK - S');

    $this->actingAs($viewer)
        ->get(route('assetlancar.show', $item))
        ->assertOk()
        ->assertSee('Legacy SKU Conversion', false)
        ->assertSee('Legacy Converter permission is required', false)
        ->assertDontSee('Convert to new SKU', false);
});

it('shows convert panel when item is linked to a legacy group without master', function () {
    $legacyGroup = \App\Models\ItemGroup::factory()->create([
        'master' => null,
        'variant' => 'BLACK',
        'name' => 'OLD GROUP - BLACK',
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'group_id' => $legacyGroup->id,
        'code' => 'GLOVE-07-BLACK-S',
        'pcode' => 'GLOVE-07',
        'name' => 'MICROFIBER STRAP GYM GLOVE - BLACK - S',
    ]);
    $item->tags()->sync([
        Tag::where('code', 'BLACK')->first()->id,
        Tag::where('code', 'S')->first()->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('assetlancar.show', $item))
        ->assertOk()
        ->assertSee('Legacy SKU Conversion', false)
        ->assertSee('Convert to new SKU', false);
});

it('shows convert panel when item has the wrong product group', function () {
    $wrongGroup = \App\Models\ItemGroup::factory()->create([
        'master' => 'OTHER-01',
        'variant' => 'BLACK',
        'name' => 'OTHER - BLACK',
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'group_id' => $wrongGroup->id,
        'code' => 'GLOVE-07-BLACK-S',
        'pcode' => 'GLOVE-07',
        'name' => 'MICROFIBER STRAP GYM GLOVE - BLACK - S',
        'legacy_code' => 'OLD-GLOVE-CODE',
    ]);
    $item->tags()->sync([
        Tag::where('code', 'BLACK')->first()->id,
        Tag::where('code', 'S')->first()->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('assetlancar.show', $item))
        ->assertOk()
        ->assertSee('Legacy SKU Conversion', false)
        ->assertSee('Convert to new SKU', false)
        ->assertSee('linked to the wrong product group', false);
});

it('relinks a mis-grouped item from the detail page', function () {
    $gloveType = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ASSET_LANCAR->value,
        'code' => 'GLOVE',
        'name' => 'Glove',
    ]);
    $wrongGroup = \App\Models\ItemGroup::factory()->create([
        'master' => 'OTHER-01',
        'variant' => 'BLACK',
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'group_id' => $wrongGroup->id,
        'code' => 'GLOVE-07-BLACK-S',
        'pcode' => 'GLOVE-07',
        'name' => 'MICROFIBER STRAP GYM GLOVE - BLACK - S',
        'legacy_code' => 'OLD-GLOVE-CODE',
        'genre' => $gloveType->id,
    ]);
    $item->tags()->sync([
        $gloveType->id,
        Tag::where('code', 'BLACK')->first()->id,
        Tag::where('code', 'S')->first()->id,
    ]);

    $this->actingAs($this->user)
        ->post(route('assetlancar.convert-identity', $item))
        ->assertRedirect(route('assetlancar.show', $item))
        ->assertSessionHas('success');

    $item->refresh()->load('group');

    expect($item->group?->master)->toBe('GLOVE-07')
        ->and($item->group?->variant)->toBe('BLACK')
        ->and($item->legacy_code)->toBe('OLD-GLOVE-CODE');
});

it('converts a single asset lancar item from the detail page', function () {
    $gloveType = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ASSET_LANCAR->value,
        'code' => 'GLOVE',
        'name' => 'Glove',
    ]);

    $item = makeLegacyAssetItem('GLOVE-07-BLACK-S', 'MICROFIBER STRAP GYM GLOVE - BLACK - S');
    $item->update([
        'genre' => $gloveType->id,
        'pcode' => 'GLOVE-07',
        'description' => 'foam black',
        'description2' => 'note',
    ]);
    $item->tags()->sync([$gloveType->id]);

    $this->actingAs($this->user)
        ->post(route('assetlancar.convert-identity', $item))
        ->assertRedirect(route('assetlancar.show', $item))
        ->assertSessionHas('success');

    $item->refresh()->load(['group', 'tags']);

    expect($item->group_id)->toBeGreaterThan(0)
        ->and($item->group?->master)->toBe('GLOVE-07')
        ->and($item->group?->variant)->toBe('BLACK')
        ->and($item->code)->toBe('GLOVE-07-BLACK-S')
        ->and($item->genre)->toBe($gloveType->id)
        ->and($item->group?->genre)->toBe($gloveType->id)
        ->and($item->group?->description)->toBe('FOAM BLACK')
        ->and($item->group?->description2)->toBe('NOTE')
        ->and($item->description)->toBe('FOAM BLACK')
        ->and($item->catalogGenre())->toBe($gloveType->id)
        ->and($item->tags->contains(fn (Tag $tag) => $tag->id === $gloveType->id))->toBeTrue()
        ->and($item->tags->contains(fn (Tag $tag) => $tag->type === Tag::TYPE_WARNA))->toBeTrue()
        ->and($item->tags->contains(fn (Tag $tag) => $tag->type === Tag::TYPE_SIZE))->toBeTrue();
});

it('converts a manufactured item from the items detail page onto the group catalog', function () {
    $typeTag = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ITEM->value,
        'code' => 'AJJ',
        'name' => 'Jacket',
    ]);
    $jahit = Tag::factory()->create(['type' => Tag::TYPE_JAHIT, 'code' => 'J1', 'name' => 'J1']);

    $item = Item::factory()->create([
        'type' => ItemType::ITEM,
        'group_id' => null,
        'code' => 'AJJCX0012204S',
        'legacy_code' => null,
        'pcode' => 'CX00122-04',
        'name' => 'RUNNING SHIRT',
        'description' => 'MIKRO MOTIF HIJAU',
        'description2' => 'nb',
        'brand' => \App\Enums\ItemBrand::NO_BRAND,
        'genre' => 0,
    ]);
    $item->tags()->sync([$typeTag->id, $jahit->id, Tag::where('code', 'S')->first()->id]);

    $this->actingAs($this->user)
        ->get(route('items.show', $item))
        ->assertOk()
        ->assertSee('Legacy SKU Conversion', false)
        ->assertSee('Convert to new SKU', false);

    $this->actingAs($this->user)
        ->post(route('items.convert-identity', $item))
        ->assertRedirect(route('items.show', $item))
        ->assertSessionHas('success');

    $item->refresh()->load('group');

    expect($item->code)->toBe('AJJ-CX00122-04-S')
        ->and($item->legacy_code)->toBe('AJJCX0012204S')
        ->and($item->group?->description)->toBe('MIKRO MOTIF HIJAU')
        ->and($item->group?->description2)->toBe('NB')
        ->and($item->group?->brand)->toBe(\App\Enums\ItemBrand::CX0)
        ->and($item->group?->genre)->toBe($typeTag->id)
        ->and($item->description)->toBe('MIKRO MOTIF HIJAU')
        ->and($item->catalogDescription())->toBe('MIKRO MOTIF HIJAU');
});

it('seeds a new group from the leftover group catalog when relinking from items detail', function () {
    $typeTag = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ITEM->value,
        'code' => 'AJJ',
        'name' => 'Jacket',
    ]);
    $jahit = Tag::factory()->create(['type' => Tag::TYPE_JAHIT, 'code' => 'J1', 'name' => 'J1']);
    $warna = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'GREEN', 'name' => 'GREEN']);
    $oldGroup = \App\Models\ItemGroup::factory()->create([
        'master' => null,
        'variant' => 'OLD',
        'name' => 'OLD LEFTOVER GROUP',
        'description' => 'MIKRO MOTIF CAMO HIJAU',
        'description2' => 'KEEP',
    ]);

    $item = Item::factory()->create([
        'type' => ItemType::ITEM,
        'group_id' => $oldGroup->id,
        'code' => 'AJJ-CX00122-04-S',
        'legacy_code' => null,
        'pcode' => 'CX00122-04',
        'name' => 'RUNNING SHIRT',
        'description' => 'MIKRO MOTIF HIJAU',
        'description2' => 'STALE',
    ]);
    $item->tags()->sync([$typeTag->id, $jahit->id, $warna->id, Tag::where('code', 'S')->first()->id]);

    $this->actingAs($this->user)
        ->post(route('items.convert-identity', $item))
        ->assertRedirect(route('items.show', $item))
        ->assertSessionHas('success');

    $item->refresh()->load('group');

    expect((int) $item->group_id)->not->toBe($oldGroup->id)
        ->and($item->group?->master)->toBe('CX00122-04')
        ->and($item->group?->description)->toBe('MIKRO MOTIF CAMO HIJAU')
        ->and($item->group?->description2)->toBe('KEEP')
        ->and($item->description)->toBe('MIKRO MOTIF CAMO HIJAU')
        ->and($item->description2)->toBe('KEEP');
});

it('links converted asset lancar items to parent group and restock type', function () {
    $gloveType = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ASSET_LANCAR->value,
        'code' => 'GLOVE',
        'name' => 'Glove',
    ]);

    $item = makeLegacyAssetItem('GLOVE-07-BLACK-S', 'MICROFIBER STRAP GYM GLOVE - BLACK - S');
    $item->update(['genre' => $gloveType->id, 'pcode' => 'GLOVE-07']);

    app(LegacyItemConverterService::class)->convertSingleFromDetail(
        $item->fresh(['tags', 'group']),
        $this->user,
    );

    $item->refresh()->load(['group', 'tags']);

    $parent = app(\App\Services\Items\ItemGroupHierarchyService::class)->parentDetail('2:GLOVE-07', false);
    $restockQuery = app(\App\Services\Restock\RestockSheetService::class);
    $method = new ReflectionMethod($restockQuery, 'assetLancarItemsForType');
    $method->setAccessible(true);

    expect($parent)->not->toBeNull()
        ->and(collect($parent['colors'])->pluck('group_id'))->toContain($item->group_id)
        ->and($method->invoke($restockQuery, $gloveType)->where('items.id', $item->id)->exists())->toBeTrue();
});

it('detail convert context treats legacy group_id zero as ungrouped', function () {
    $service = app(LegacyItemConverterService::class);
    $item = makeLegacyAssetItem('KNEESUPPORT-21-WHITE-S');
    $item->group_id = 0;

    $context = $service->detailConvertContext($item);

    expect($service->hasProductGroup($item))->toBeFalse()
        ->and($context['visible'])->toBeTrue()
        ->and($context['convertible'])->toBeTrue();
});

it('detail convert context hides only fully canonical items', function () {
    $service = app(LegacyItemConverterService::class);
    $item = makeLegacyAssetItem('GLOVE-01-BLACK-S');

    $context = $service->detailConvertContext($item);

    expect($context['convertible'])->toBeTrue()
        ->and($context['visible'])->toBeTrue();

    $canonicalGroup = \App\Models\ItemGroup::factory()->create([
        'master' => 'GLOVE-01',
        'variant' => 'BLACK',
        'name' => 'BOXING GLOVE - BLACK',
    ]);
    $gloveType = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ASSET_LANCAR->value,
        'code' => 'GLOVE',
        'name' => 'Glove',
    ]);
    $item->update(['group_id' => $canonicalGroup->id, 'genre' => $gloveType->id]);
    $item->tags()->sync([
        $gloveType->id,
        Tag::where('code', 'BLACK')->first()->id,
        Tag::where('code', 'S')->first()->id,
    ]);

    expect($service->detailConvertContext($item->fresh())['visible'])->toBeFalse();
});

it('forbids detail convert without permission', function () {
    $item = makeLegacyAssetItem('GLOVE-01-BLACK-S');
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)
        ->post(route('assetlancar.convert-identity', $item))
        ->assertForbidden();
});

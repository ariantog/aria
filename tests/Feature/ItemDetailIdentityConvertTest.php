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
    Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BABYBLUE', 'name' => 'BABYBLUE']);
    Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'LIGHT', 'name' => 'LIGHT']);
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

it('hides convert panel when item already has a group', function () {
    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'group_id' => \App\Models\ItemGroup::factory()->create()->id,
        'code' => 'GLOVE-01-BLACK-S',
    ]);

    $this->actingAs($this->user)
        ->get(route('assetlancar.show', $item))
        ->assertOk()
        ->assertDontSee('Legacy SKU Conversion', false);
});

it('warns about special sku families without a convert button', function () {
    $item = makeLegacyAssetItem('FABRICBAND-03-LIGHT-BABYBLUE', 'FABRIC BAND - LIGHT - BABYBLUE');

    $this->actingAs($this->user)
        ->get(route('assetlancar.show', $item))
        ->assertOk()
        ->assertSee('Special SKU — do not use generic conversion', false)
        ->assertSee('Open Special SKU Converter', false)
        ->assertDontSee('Convert to new SKU', false);
});

it('converts a single asset lancar item from the detail page', function () {
    $item = makeLegacyAssetItem('GLOVE-01-BLACK-S', 'BOXING GLOVE - BLACK - S');

    $this->actingAs($this->user)
        ->post(route('assetlancar.convert-identity', $item))
        ->assertRedirect(route('assetlancar.show', $item))
        ->assertSessionHas('success');

    $item->refresh();

    expect($item->group_id)->not->toBeNull()
        ->and($item->code)->toBe('GLOVE-01-BLACK-S')
        ->and($item->tags->contains(fn (Tag $tag) => $tag->type === Tag::TYPE_WARNA))->toBeTrue();
});

it('rejects generic convert for special sku posts', function () {
    $item = makeLegacyAssetItem('FABRICBAND-03-LIGHT-BABYBLUE', 'FABRIC BAND - LIGHT - BABYBLUE');

    $this->actingAs($this->user)
        ->post(route('assetlancar.convert-identity', $item))
        ->assertRedirect(route('assetlancar.show', $item))
        ->assertSessionHas('error');

    expect($item->fresh()->group_id)->toBeNull();
});

it('detail convert context requires no group and pending legacy column', function () {
    $service = app(LegacyItemConverterService::class);
    $item = makeLegacyAssetItem('GLOVE-01-BLACK-S');

    $context = $service->detailConvertContext($item);

    expect($context['convertible'])->toBeTrue()
        ->and($context['visible'])->toBeTrue();

    $item->update(['group_id' => \App\Models\ItemGroup::factory()->create()->id]);

    expect($service->detailConvertContext($item->fresh())['visible'])->toBeFalse();
});

it('forbids detail convert without permission', function () {
    $item = makeLegacyAssetItem('GLOVE-01-BLACK-S');
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)
        ->post(route('assetlancar.convert-identity', $item))
        ->assertForbidden();
});

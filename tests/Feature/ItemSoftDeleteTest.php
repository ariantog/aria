<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\User;
use App\Services\Items\ItemDimensionResolver;
use Spatie\Permission\Models\Permission;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    foreach (['items-list', 'items-delete', 'assetLancar-list', 'assetLancar-delete'] as $name) {
        Permission::findOrCreate($name, 'web');
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'items-list',
        'items-delete',
        'assetLancar-list',
        'assetLancar-delete',
    ]);
});

it('archives an item and hides it from list and autocomplete lookups', function () {
    $item = Item::factory()->create([
        'type' => ItemType::ITEM,
        'name' => 'Archive Lookup Tee',
        'code' => 'ARCHIVE-TEE-01',
    ]);

    $this->actingAs($this->user)
        ->delete(route('items.destroy', $item))
        ->assertRedirect(route('items.index'));

    expect($item->fresh()->trashed())->toBeTrue();

    $this->actingAs($this->user)
        ->get(route('items.index', ['search' => 'ARCHIVE-TEE-01']))
        ->assertOk()
        ->assertDontSee('ARCHIVE-TEE-01');

    $this->actingAs($this->user)
        ->getJson('/items?search=Archive&json=1')
        ->assertSuccessful()
        ->assertExactJson([]);
});

it('archives asset lancar and redirects to the asset list', function () {
    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'name' => 'Archive Asset Band',
        'code' => 'ARCHIVE-BAND-01',
    ]);

    $this->actingAs($this->user)
        ->delete(route('assetlancar.destroy', $item))
        ->assertRedirect(route('assetlancar.index'));

    expect($item->fresh()->trashed())->toBeTrue();
});

it('still resolves dimensions for archived items in reporting paths', function () {
    $item = Item::factory()->create([
        'type' => ItemType::ITEM,
        'name' => 'Archived Report Tee',
        'code' => 'ARCHIVE-RPT-01',
        'pcode' => 'CX90001-01',
    ]);

    $item->delete();

    $resolved = app(ItemDimensionResolver::class)->findItem($item->id);

    expect($resolved)->not->toBeNull()
        ->and($resolved->code)->toBe('ARCHIVE-RPT-01');
});

it('shows archive action on item detail when delete permission is granted', function () {
    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR,
        'code' => 'ARCHIVE-UI-01',
    ]);

    $this->actingAs($this->user)
        ->get(route('assetlancar.show', $item))
        ->assertOk()
        ->assertSee('data-testid="archive-item"', false);
});

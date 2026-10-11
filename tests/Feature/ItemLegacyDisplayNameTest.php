<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\Tag;
use App\Models\User;
use App\Services\Items\ItemIdentityBuilder;
use App\Services\Items\ItemLegacyDisplayNameService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'items-convert-legacy', 'guard_name' => 'web']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('items-convert-legacy');
    $this->actingAs($this->user);

    $this->service = app(ItemLegacyDisplayNameService::class);
    $this->builder = app(ItemIdentityBuilder::class);

    $this->typeTag = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'item_type' => ItemType::ITEM->value,
        'code' => 'CLN',
        'name' => 'Shorts',
    ]);
    $this->sizeXl = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'XL', 'name' => 'XL']);
    $this->armyTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'ARMY', 'name' => 'ARMY GREEN']);
    $this->redTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'RED', 'name' => 'RED']);
});

it('infers bare product title from legacy display names and skips technical names', function () {
    $legacyGroup = ItemGroup::factory()->create([
        'master' => 'CI00098-04',
        'variant' => '04',
        'name' => 'CI00098-04',
    ]);
    $legacyItem = Item::factory()->create([
        'group_id' => $legacyGroup->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CI00098-04',
        'code' => 'CLN-CI00098-04-XL',
        'name' => 'CORE SHORTS - ARMY GREEN - XL',
    ]);
    $legacyItem->tags()->attach([$this->typeTag->id, $this->armyTag->id, $this->sizeXl->id]);

    $newGroup = ItemGroup::factory()->create([
        'master' => 'CI00098-06',
        'variant' => '06',
        'name' => 'CI00098/06',
    ]);
    $newItem = Item::factory()->create([
        'group_id' => $newGroup->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CI00098-06',
        'code' => 'CLN-CI00098-06-XL',
        'name' => 'CLN CI00098/06 XL',
    ]);
    $newItem->tags()->attach([$this->typeTag->id, $this->redTag->id, $this->sizeXl->id]);

    $parentKey = $this->builder->itemParentKey($legacyItem->fresh(['group', 'tags']));
    expect($this->builder->itemParentKey($newItem->fresh(['group', 'tags'])))->toBe($parentKey);

    $preview = $this->service->previewForParentKey($parentKey);

    expect($preview['inferred_title'])->toBe('CORE SHORTS')
        ->and($preview['would_change'])->toBeGreaterThan(0);

    $newRow = collect($preview['rows'])->firstWhere('id', $newItem->id);
    expect($newRow)->not->toBeNull()
        ->and(strtoupper($newRow['proposed']))->toBe('CORE SHORTS - RED - XL');
});

it('apply syncs parent title and rebuilds items.name for all colorways', function () {
    $legacyGroup = ItemGroup::factory()->create([
        'master' => 'CI00098-04',
        'variant' => '04',
        'name' => 'CI00098-04',
    ]);
    $legacyItem = Item::factory()->create([
        'group_id' => $legacyGroup->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CI00098-04',
        'code' => 'CLN-CI00098-04-XL',
        'name' => 'CORE SHORTS - ARMY GREEN - XL',
    ]);
    $legacyItem->tags()->attach([$this->typeTag->id, $this->armyTag->id, $this->sizeXl->id]);

    $newGroup = ItemGroup::factory()->create([
        'master' => 'CI00098-06',
        'variant' => '06',
        'name' => 'CI00098/06',
    ]);
    $newItem = Item::factory()->create([
        'group_id' => $newGroup->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CI00098-06',
        'code' => 'CLN-CI00098-06-XL',
        'name' => 'CLN CI00098/06 XL',
    ]);
    $newItem->tags()->attach([$this->typeTag->id, $this->redTag->id, $this->sizeXl->id]);

    $parentKey = $this->builder->itemParentKey($legacyItem->fresh(['group', 'tags']));

    $result = $this->service->applyForParentKey($parentKey);

    expect($result['product_title'])->toBe('CORE SHORTS')
        ->and($newItem->fresh()->name)->toBe('CORE SHORTS - RED - XL')
        ->and($legacyItem->fresh()->name)->toBe('CORE SHORTS - ARMY GREEN - XL');
});

it('renders display name sync page and applies via http', function () {
    $group = ItemGroup::factory()->create([
        'master' => 'CX99001-01',
        'variant' => '01',
        'name' => 'CX99001-01',
    ]);
    $legacy = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX99001-01',
        'code' => 'CLN-CX99001-01-XL',
        'name' => 'SYNC PANTS - BLACK - XL',
    ]);
    $legacy->tags()->attach([$this->typeTag->id, Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLACK', 'name' => 'BLACK'])->id, $this->sizeXl->id]);

    $converted = Item::factory()->create([
        'group_id' => $group->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX99001-01',
        'code' => 'CLN-CX99001-01-M',
        'name' => 'CLN CX99001/01 M',
        'size' => Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'M', 'name' => 'M'])->id,
    ]);
    $converted->tags()->attach([$this->typeTag->id, Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLACK', 'name' => 'BLACK'])->id]);

    $parentKey = $this->builder->itemParentKey($legacy->fresh(['group', 'tags']));

    $this->get(route('items.display-name-sync', ['needle' => 'CX99001', 'parent_key' => $parentKey]))
        ->assertOk()
        ->assertSee('data-testid="display-name-sync-preview-table"', false)
        ->assertSee('SYNC PANTS');

    $this->post(route('items.display-name-sync.apply'), [
        'parent_key' => $parentKey,
        'needle' => 'CX99001',
        'product_title' => '',
        'type' => ItemType::ITEM->value,
    ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($converted->fresh()->name)->toContain('SYNC PANTS');
});

it('denies display name sync without permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('items.display-name-sync'))->assertForbidden();
});

<?php

use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemParent;
use App\Models\Tag;
use App\Enums\ItemType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

it('copies legacy group catalog when parent_id is already linked', function () {
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
        'master' => 'CX93249-03',
        'variant' => '03',
        'name' => 'Legacy Product Title',
        'description' => 'Legacy group desc',
        'url' => 'https://legacy.example/item',
    ]);
    $item = Item::factory()->create([
        'group_id' => $group->id,
        'pcode' => 'CX93249-03',
        'code' => 'AJD-CX93249-03-S',
    ]);
    $item->tags()->attach([$typeTag->id, $sizeTag->id, $warnaTag->id, $jahitTag->id]);

    $parent = ensureItemParentForGroup($group, $item);
    expect($group->fresh()->parent_id)->toBe($parent->id);

    $parent->description = '';
    $parent->url = '';
    $parent->product_name = '';
    $parent->save();

    $this->artisan('items:backfill-group-parent-id', ['--copy-catalog' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('catalog parents updated: 1');

    $parent->refresh();
    expect($parent->description)->toBe('LEGACY GROUP DESC');
    expect($parent->url)->toBe('https://legacy.example/item');
    expect($parent->product_name)->toBe('LEGACY PRODUCT TITLE');
});

it('copies group catalog when skus are soft-deleted', function () {
    $parent = ItemParent::query()->create([
        'parent_key' => '2:KNEESUPPORT-05',
    ]);

    $group = ItemGroup::factory()->create([
        'parent_id' => $parent->id,
        'master' => 'KNEESUPPORT-05',
        'variant' => 'BLACK',
        'name' => 'ULTIMATE KNEE SUPPORT',
        'description' => 'EXTRA SUPPORT',
        'description2' => 'RESELLER NOTE',
        'genre' => 6499,
    ]);

    $item = Item::factory()->create([
        'group_id' => $group->id,
        'deleted_at' => now(),
        'pcode' => 'KNEESUPPORT-05',
        'code' => 'KNEESUPPORT-05-BLACK-S',
        'type' => ItemType::ASSET_LANCAR->value,
    ]);

    $this->artisan('items:backfill-group-parent-id', [
        '--copy-catalog' => true,
        '--parent-id' => (string) $parent->id,
    ])->assertSuccessful();

    $parent->refresh();
    expect($parent->product_name)->toBe('ULTIMATE KNEE SUPPORT');
    expect($parent->description)->toBe('EXTRA SUPPORT');
    expect($parent->description2)->toBe('RESELLER NOTE');
    expect($parent->genre)->toBe(6499);
});

it('relinks colorways to canonical parent when parent_key is duplicated', function () {
    if (Schema::hasTable('item_parents') && Schema::hasColumn('item_parents', 'parent_key')) {
        Schema::table('item_parents', function (Blueprint $table) {
            if (Schema::hasIndex('item_parents', 'item_parents_key_uq')) {
                $table->dropUnique('item_parents_key_uq');
            }
        });
    }

    $canonical = ItemParent::query()->create(['parent_key' => '2:KNEESUPPORT-05']);
    $duplicate = ItemParent::query()->create(['parent_key' => '2:KNEESUPPORT-05']);

    $group = ItemGroup::factory()->create([
        'parent_id' => $duplicate->id,
        'master' => 'KNEESUPPORT-05',
        'variant' => 'BLACK',
        'name' => 'ULTIMATE KNEE SUPPORT',
        'description' => 'KNEE SUPPORT DESC',
    ]);

    Item::factory()->create([
        'group_id' => $group->id,
        'pcode' => 'KNEESUPPORT-05',
        'code' => 'KNEESUPPORT-05-BLACK-S',
        'type' => ItemType::ASSET_LANCAR->value,
    ]);

    $this->artisan('items:backfill-group-parent-id', ['--copy-catalog' => true])
        ->assertSuccessful()
        ->expectsOutputToContain("Relinked 1 colorway(s): parent_id {$duplicate->id} → {$canonical->id}");

    expect($group->fresh()->parent_id)->toBe($canonical->id);

    $canonical->refresh();
    expect($canonical->description)->toBe('KNEE SUPPORT DESC');
    expect($canonical->product_name)->toBe('ULTIMATE KNEE SUPPORT');
});

it('reports already-linked groups when nothing needs parent_id', function () {
    $group = ItemGroup::factory()->create(['parent_id' => 0]);
    Item::factory()->create(['group_id' => $group->id]);

    $this->artisan('items:backfill-group-parent-id', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Groups needing parent_id link: 1');
});

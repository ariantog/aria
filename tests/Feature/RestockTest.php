<?php

use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'restock-create']);
    $this->user->givePermissionTo('restock-create');
});

test('it can add a single item to restock list', function () {
    $item = Item::factory()->create(['code' => 'ITEM001']);

    $response = $this->actingAs($this->user)
        ->from(route('restock.create'))
        ->withoutMiddleware()
        ->post('/restock/add-item', [
            'code' => 'ITEM001',
            'qty' => 5,
        ]);

    $response->assertRedirect(route('restock.create'));
    $response->assertSessionHas('success');

    $cacheKey = "cart_items_user_{$this->user->id}";
    $items = Cache::get($cacheKey);

    expect($items)->toHaveCount(1);
    expect($items[0]['id'])->toBe($item->id);
    expect($items[0]['qty'])->toBe(5);
});

test('it can add all items from a group to restock list', function () {
    $group = ItemGroup::factory()->create(['master' => 'GROUP001']);
    $item1 = Item::factory()->create(['group_id' => $group->id, 'code' => 'GITEM001']);
    $item2 = Item::factory()->create(['group_id' => $group->id, 'code' => 'GITEM002']);

    $response = $this->actingAs($this->user)
        ->from(route('items.group'))
        ->withoutMiddleware()
        ->post('/restock/add-item', [
            'code' => 'GROUP001',
            'qty' => 10,
        ]);

    $response->assertRedirect(route('items.group'));
    $response->assertSessionHas('success');

    $cacheKey = "cart_items_user_{$this->user->id}";
    $items = Cache::get($cacheKey);

    expect($items)->toHaveCount(2);

    $itemIds = collect($items)->pluck('id')->toArray();
    expect($itemIds)->toContain($item1->id);
    expect($itemIds)->toContain($item2->id);

    foreach ($items as $item) {
        expect($item['qty'])->toBe(10);
    }
});

test('it returns error if item or group not found', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware()
        ->post('/restock/add-item', [
            'code' => 'NONEXISTENT',
            'qty' => 1,
        ]);

    $response->assertSessionHas('error');
});

test('it prevents adding a duplicate group already in cache', function () {
    $group = ItemGroup::factory()->create(['master' => 'DUPGROUP']);

    // Pre-populate cache
    $cacheKey = "cart_items_user_{$this->user->id}";
    Cache::put($cacheKey, [['group_id' => $group->id, 'qty' => 1]], now()->addHour());

    $response = $this->actingAs($this->user)
        ->withoutMiddleware()
        ->post('/restock/add-item', [
            'code' => 'DUPGROUP',
            'qty' => 1,
        ]);

    $response->assertSessionHas('error', "Group {$group->name} sudah ada di daftar restock (cache).");
});

test('it prevents adding a group already in database', function () {
    $group = ItemGroup::factory()->create(['master' => 'DBGROUP']);
    \App\Models\Restock::create([
        'group_id' => $group->id,
        'date' => now(),
        'status' => 1,
        'restocked_quantity' => 10,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware()
        ->post('/restock/add-item', [
            'code' => 'DBGROUP',
            'qty' => 1,
        ]);

    $response->assertSessionHas('error', "Group {$group->name} sudah terdaftar di database restock.");
});

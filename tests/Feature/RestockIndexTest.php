<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'restock-list']);
    $this->user->givePermissionTo('restock-list');
});

test('restock index page receives restockCacheCount', function () {
    $userId = $this->user->id;
    $cacheKey = "cart_items_user_{$userId}";

    // Add some items to cache
    Cache::put($cacheKey, [
        ['id' => 1, 'qty' => 5],
        ['id' => 2, 'qty' => 10],
    ], now()->addHour());

    $response = $this->actingAs($this->user)
        ->get('/restock');

    $response->assertStatus(200);
    $response->assertViewIs('restock.index');
    $response->assertViewHas('restockCacheCount', 2);
});

test('restock index page receives restockCacheCount as zero when empty', function () {
    $response = $this->actingAs($this->user)
        ->get('/restock');

    $response->assertStatus(200);
    $response->assertViewIs('restock.index');
    $response->assertViewHas('restockCacheCount', 0);
});

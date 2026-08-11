<?php

use App\Models\Borongan;
use App\Models\Item;
use App\Models\Produksi;
use App\Models\User;
use App\Models\Worker;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Seed permissions if they don't exist
    foreach (Borongan::getPermissions() as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->admin = User::factory()->create();
    $this->user = User::factory()->create();

    $role = Role::findOrCreate('superadmin');
    $this->admin->assignRole($role);
});

it('denies access to list borongan for unauthorized user', function () {
    $response = $this->actingAs($this->user)->get('/borongan');
    $response->assertStatus(403);
});

it('allows access to list borongan for authorized user', function () {
    $this->user->givePermissionTo('borongan-list');

    $response = $this->actingAs($this->user)->get('/borongan');
    $response->assertStatus(200);
    $response->assertViewIs('borongan.index');
});

it('denies access to create borongan for unauthorized user', function () {
    $response = $this->actingAs($this->user)->get('/borongan/create');
    $response->assertStatus(403);
});

it('allows access to create borongan for authorized user', function () {
    $this->user->givePermissionTo('borongan-create');

    $response = $this->actingAs($this->user)->get('/borongan/create');
    $response->assertStatus(200);
    $response->assertViewIs('borongan.create');
});

it('denies access to view borongan details for unauthorized user', function () {
    $worker = Worker::create(['name' => 'John Jahit', 'type' => Worker::TYPE_JAHIT]);
    $borongan = Borongan::create([
        'date' => now()->toDateString(),
        'user_id' => $this->admin->id,
        'jahit_id' => $worker->id,
        'total' => 100000,
        'total_items' => 10,
        'from' => now()->subDays(7)->toDateString(),
        'to' => now()->toDateString(),
    ]);

    $response = $this->actingAs($this->user)->get("/borongan/{$borongan->id}");
    $response->assertStatus(403);
});

it('allows access to view borongan details for authorized user', function () {
    $this->user->givePermissionTo('borongan-view');

    $worker = Worker::create(['name' => 'John Jahit', 'type' => Worker::TYPE_JAHIT]);
    $borongan = Borongan::create([
        'date' => now()->toDateString(),
        'user_id' => $this->admin->id,
        'jahit_id' => $worker->id,
        'total' => 100000,
        'total_items' => 10,
        'from' => now()->subDays(7)->toDateString(),
        'to' => now()->toDateString(),
    ]);

    $response = $this->actingAs($this->user)->get("/borongan/{$borongan->id}");
    $response->assertStatus(200);
    $response->assertViewIs('borongan.show');
});

it('creates one borongan per jahit for gudang items in date range', function () {
    $this->user->givePermissionTo('borongan-create');

    $jahitA = Worker::create(['name' => 'Jahit A', 'type' => Worker::TYPE_JAHIT]);
    $jahitB = Worker::create(['name' => 'Jahit B', 'type' => Worker::TYPE_JAHIT]);
    $item = Item::factory()->create();

    $from = now()->subDays(7)->toDateString();
    $to = now()->toDateString();

    foreach ([$jahitA, $jahitB] as $jahit) {
        Produksi::create([
            'temp_name' => 'Item '.$jahit->name,
            'quantity' => 5,
            'jahit_id' => $jahit->id,
            'jahit_date' => now(),
            'item_id' => $item->id,
            'status' => Produksi::STATUS_GUDANG,
            'gudang_date' => now()->toDateString(),
        ]);
    }

    $response = $this->actingAs($this->user)->post('/borongan', [
        'from' => $from,
        'to' => $to,
        'batches' => [
            ['jahit_id' => $jahitA->id, 'permak' => 1000, 'tres' => 0, 'lain2' => 0],
            ['jahit_id' => $jahitB->id, 'permak' => 0, 'tres' => 500, 'lain2' => 0],
        ],
    ]);

    $response->assertRedirect('/borongan');
    expect(\App\Models\Borongan::count())->toBe(2);
    expect(Produksi::where('status', Produksi::STATUS_BOTH)->count())->toBe(2);
});

it('ajax borongan returns items grouped by jahit', function () {
    $this->user->givePermissionTo('borongan-create');

    $jahit = Worker::create(['name' => 'Jahit Ajax', 'type' => Worker::TYPE_JAHIT]);
    $item = Item::factory()->create();

    Produksi::create([
        'temp_name' => 'Ajax Item',
        'quantity' => 3,
        'jahit_id' => $jahit->id,
        'item_id' => $item->id,
        'status' => Produksi::STATUS_GUDANG,
        'gudang_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($this->user)->get('/borongan/ajax?from='.now()->subDay()->toDateString().'&to='.now()->toDateString());

    $response->assertSuccessful();
    $response->assertJsonFragment(['jahit_name' => 'Jahit Ajax']);
    $response->assertJsonStructure([['jahit_id', 'jahit_name', 'items', 'subtotal', 'total_qty']]);
});

<?php

use App\Models\Produksi;
use App\Models\User;
use App\Models\Worker;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'superadmin']);
    $this->user->assignRole($role);
});

it('can list pritil workers', function () {
    Worker::create(['name' => 'Pritil Specialist', 'type' => Worker::TYPE_PRITIL]);

    $response = $this->actingAs($this->user)->get('/produksi/pritil/list');

    $response->assertStatus(200);
    $response->assertViewIs('produksi.workers.index');
    $response->assertSee('Pritil Specialist');
});

it('can create pritil worker', function () {
    $response = $this->actingAs($this->user)->post('/produksi/pritil/store', [
        'name' => 'John Pritil',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('prod_worker', [
        'name' => 'John Pritil',
        'type' => Worker::TYPE_PRITIL,
    ]);
});

it('can assign a pritil worker to a setoran entry', function () {
    $worker = Worker::create(['name' => 'Head Pritil', 'type' => Worker::TYPE_PRITIL]);
    $produksi = Produksi::create([
        'temp_name' => 'Pritil Test Item',
        'quantity' => 10,
        'status' => Produksi::STATUS_SETOR,
    ]);

    $response = $this->actingAs($this->user)->patch("/produksi/{$produksi->id}/pritil", [
        'pritil_id' => $worker->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('prod_produksi', [
        'id' => $produksi->id,
        'pritil_id' => $worker->id,
    ]);
});

it('shows worker detail page with history', function () {
    $worker = Worker::create(['name' => 'Potong A', 'type' => Worker::TYPE_POTONG]);
    Produksi::create([
        'temp_name' => 'Item A',
        'quantity' => 5,
        'potong_id' => $worker->id,
        'potong_date' => now()->toDateString(),
        'status' => Produksi::STATUS_PRODUKSI,
    ]);

    $response = $this->actingAs($this->user)->get("/produksi/potong/{$worker->id}");

    $response->assertStatus(200);
    $response->assertViewIs('produksi.workers.show');
    $response->assertSee('Potong A');
    $response->assertSee('Item A');
});

it('filters permissions by search query', function () {
    app(\App\Services\PermissionGenerator::class)->generateAll();

    $response = $this->actingAs($this->user)->get('/permissions?search=production-setoran-revert');

    $response->assertStatus(200);
    $response->assertSee('production-setoran-revert');
});

it('filters produksi index by clicking customer filter value', function () {
    Produksi::create([
        'temp_name' => 'Alpha',
        'quantity' => 1,
        'customer' => 'ACME',
        'status' => Produksi::STATUS_PRODUKSI,
        'potong_date' => now()->toDateString(),
    ]);
    Produksi::create([
        'temp_name' => 'Beta',
        'quantity' => 1,
        'customer' => 'OTHER',
        'status' => Produksi::STATUS_PRODUKSI,
        'potong_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($this->user)->get('/produksi?customer=ACME');

    $response->assertStatus(200);
    $response->assertSee('Alpha');
    $response->assertDontSee('Beta');
});

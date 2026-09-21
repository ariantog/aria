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

it('can list jahit workers', function () {
    Worker::create(['name' => 'Jane Doe', 'type' => Worker::TYPE_JAHIT]);

    $response = $this->actingAs($this->user)->get('/produksi/jahit/list');

    $response->assertStatus(200);
    $response->assertViewIs('produksi.workers.index');
    $response->assertViewHas('workers', fn ($workers) => $workers->total() === 1);
});

it('can create jahit worker', function () {
    $response = $this->actingAs($this->user)->post('/produksi/jahit/store', [
        'name' => 'John Taylor',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('prod_worker', [
        'name' => 'John Taylor',
        'type' => Worker::TYPE_JAHIT,
    ]);
});

it('can update jahit worker', function () {
    $worker = Worker::create(['name' => 'Jane Smith', 'type' => Worker::TYPE_JAHIT]);

    $response = $this->actingAs($this->user)->put("/produksi/jahit/{$worker->id}", [
        'name' => 'Jane Taylor',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('prod_worker', [
        'id' => $worker->id,
        'name' => 'Jane Taylor',
    ]);
});

it('can delete jahit worker', function () {
    $worker = Worker::create(['name' => 'To Be Deleted', 'type' => Worker::TYPE_JAHIT]);

    $response = $this->actingAs($this->user)->delete("/produksi/jahit/{$worker->id}/delete");

    $response->assertRedirect();
    $this->assertSoftDeleted('prod_worker', [
        'id' => $worker->id,
    ]);
});

it('assigns jahit from an inline dropdown on the produksi list', function () {
    $worker = Worker::create(['name' => 'Inline Jahit', 'type' => Worker::TYPE_JAHIT]);
    $produksi = Produksi::create([
        'temp_name' => 'Needs Jahit',
        'quantity' => 5,
        'status' => Produksi::STATUS_PRODUKSI,
    ]);

    $this->actingAs($this->user)
        ->get('/produksi')
        ->assertSuccessful()
        ->assertSee('data-testid="assign-jahit-'.$produksi->id.'"', false)
        ->assertSee('Inline Jahit')
        ->assertDontSee('Assign Worker')
        ->assertDontSee('Search worker...');
});

it('can assign a jahit worker to a production entry', function () {
    $worker = Worker::create(['name' => 'Top Sewer', 'type' => Worker::TYPE_JAHIT]);
    $produksi = Produksi::create([
        'temp_name' => 'Test Item',
        'quantity' => 10,
        'status' => Produksi::STATUS_PRODUKSI,
    ]);

    $response = $this->actingAs($this->user)->patch("/produksi/{$produksi->id}/jahit", [
        'jahit_id' => $worker->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('prod_produksi', [
        'id' => $produksi->id,
        'jahit_id' => $worker->id,
    ]);
});

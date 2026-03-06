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

it('can list qc workers', function () {
    Worker::create(['name' => 'QC Specialist', 'type' => Worker::TYPE_QC]);

    $response = $this->actingAs($this->user)->get('/produksi/qc/list');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Produksi/QC/Workers/Index')
        ->has('workers.data', 1)
    );
});

it('can create qc worker', function () {
    $response = $this->actingAs($this->user)->post('/produksi/qc/store', [
        'name' => 'John Qc',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('workers', [
        'name' => 'John Qc',
        'type' => Worker::TYPE_QC,
    ]);
});

it('can update qc worker', function () {
    $worker = Worker::create(['name' => 'Old Qc', 'type' => Worker::TYPE_QC]);

    $response = $this->actingAs($this->user)->put("/produksi/qc/{$worker->id}", [
        'name' => 'New Qc',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('workers', [
        'id' => $worker->id,
        'name' => 'New Qc',
    ]);
});

it('can delete qc worker', function () {
    $worker = Worker::create(['name' => 'Delete Me', 'type' => Worker::TYPE_QC]);

    $response = $this->actingAs($this->user)->delete("/produksi/qc/{$worker->id}/delete");

    $response->assertRedirect();
    $this->assertSoftDeleted('workers', [
        'id' => $worker->id,
    ]);
});

it('can assign a qc worker to a production entry', function () {
    $worker = Worker::create(['name' => 'Head QC', 'type' => Worker::TYPE_QC]);
    $produksi = Produksi::create([
        'temp_name' => 'QC Test Item',
        'quantity' => 10,
        'status' => Produksi::STATUS_PRODUKSI,
    ]);

    $response = $this->actingAs($this->user)->patch("/produksi/{$produksi->id}/qc", [
        'qc_id' => $worker->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('produksis', [
        'id' => $produksi->id,
        'qc_id' => $worker->id,
    ]);
});

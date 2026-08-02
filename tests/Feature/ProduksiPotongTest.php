<?php

use App\Models\Tag;
use App\Models\User;
use App\Models\Worker;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'superadmin']);
    $this->user->assignRole($role);
});

it('can list potong workers', function () {
    Worker::create(['name' => 'John Doe', 'type' => Worker::TYPE_POTONG]);

    $response = $this->actingAs($this->user)->get('/produksi/potong/list');

    $response->assertStatus(200);
    $response->assertViewIs('produksi.workers.index');
    $response->assertViewHas('workers', fn ($workers) => $workers->total() === 1);
});

it('can create potong worker', function () {
    $response = $this->actingAs($this->user)->post('/produksi/potong/store', [
        'name' => 'Jane Doe',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('workers', [
        'name' => 'Jane Doe',
        'type' => Worker::TYPE_POTONG,
    ]);
});

it('can update potong worker', function () {
    $worker = Worker::create(['name' => 'Old Name', 'type' => Worker::TYPE_POTONG]);

    $response = $this->actingAs($this->user)->put("/produksi/potong/{$worker->id}", [
        'name' => 'New Name',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('workers', [
        'id' => $worker->id,
        'name' => 'New Name',
    ]);
});

it('can delete potong worker', function () {
    $worker = Worker::create(['name' => 'To Delete', 'type' => Worker::TYPE_POTONG]);

    $response = $this->actingAs($this->user)->delete("/produksi/potong/{$worker->id}/delete");

    $response->assertSessionHasNoErrors();
    $this->assertSoftDeleted('workers', ['id' => $worker->id]);
});

it('can store bulk production entries', function () {
    $worker = Worker::create(['name' => 'Cutter 1', 'type' => Worker::TYPE_POTONG]);
    $size = Tag::create(['name' => 'L', 'type' => Tag::TYPE_SIZE, 'item_type' => 0]);

    $response = $this->actingAs($this->user)->post('/produksi', [
        'date' => now()->toDateString(),
        'potong_id' => $worker->id,
        'surat_jalan_potong' => 'SJ-001',
        'items' => [
            [
                'name' => 'T-Shirt A',
                'size_id' => $size->id,
                'qty' => 10,
                'customer' => 'Client X',
                'warna' => 'Red',
            ],
            [
                'name' => 'T-Shirt B',
                'size_id' => $size->id,
                'qty' => 20,
                'customer' => 'Client Y',
                'warna' => 'Blue',
            ],
        ],
    ]);

    $response->assertRedirect('/produksi');
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('produksis', [
        'temp_name' => 'T-Shirt A',
        'quantity' => 10,
        'customer' => 'CLIENT X',
        'warna' => 'RED',
        'potong_id' => $worker->id,
    ]);

    $this->assertDatabaseHas('produksis', [
        'temp_name' => 'T-Shirt B',
        'quantity' => 20,
        'customer' => 'CLIENT Y',
        'warna' => 'BLUE',
        'potong_id' => $worker->id,
    ]);
});

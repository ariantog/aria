<?php

use App\Models\Item;
use App\Models\Produksi;
use App\Models\Tag;
use App\Models\User;
use App\Models\Worker;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'superadmin']);
    $this->user->assignRole($role);
});

it('shows split lineage on produksi index', function () {
    $worker = Worker::create(['name' => 'Cutter', 'type' => Worker::TYPE_POTONG]);
    $size = Tag::create(['name' => 'L', 'type' => Tag::TYPE_SIZE, 'item_type' => 0]);

    $parent = Produksi::create([
        'temp_name' => 'Parent Item',
        'size_id' => $size->id,
        'quantity' => 40,
        'potong_id' => $worker->id,
        'potong_date' => now(),
        'status' => Produksi::STATUS_PRODUKSI,
    ]);

    Produksi::create([
        'temp_name' => 'Parent Item',
        'size_id' => $size->id,
        'quantity' => 10,
        'potong_id' => $worker->id,
        'potong_date' => now(),
        'status' => Produksi::STATUS_PRODUKSI,
        'original_id' => $parent->id,
    ]);

    $response = $this->actingAs($this->user)->get('/produksi');

    $response->assertSuccessful();
    $response->assertSee('split of');
    $response->assertSee('parent');
    $response->assertSee(route('produksi.edit', $parent->id), false);
});

it('does not show qc reassignment on produksi edit page', function () {
    $worker = Worker::create(['name' => 'Cutter', 'type' => Worker::TYPE_POTONG]);
    $qc = Worker::create(['name' => 'QC One', 'type' => Worker::TYPE_QC]);
    $size = Tag::create(['name' => 'L', 'type' => Tag::TYPE_SIZE, 'item_type' => 0]);

    $produksi = Produksi::create([
        'temp_name' => 'Edit Me',
        'size_id' => $size->id,
        'quantity' => 10,
        'potong_id' => $worker->id,
        'potong_date' => now(),
        'status' => Produksi::STATUS_PRODUKSI,
        'qc_id' => $qc->id,
    ]);

    $response = $this->actingAs($this->user)->get("/produksi/{$produksi->id}/edit");

    $response->assertSuccessful();
    $response->assertSee('Reassign Jahit');
    $response->assertDontSee('Reassign QC');
});

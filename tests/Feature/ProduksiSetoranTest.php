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

it('allows editing kode on setoran row when invoice is empty and status is setor', function () {
    $worker = Worker::create(['name' => 'Cutter', 'type' => Worker::TYPE_POTONG]);
    $size = Tag::create(['name' => 'L', 'type' => Tag::TYPE_SIZE, 'item_type' => 0]);
    $item = Item::factory()->create(['code' => 'AJD-CX90233-23-S']);

    $produksi = Produksi::create([
        'temp_name' => 'Temp Name',
        'size_id' => $size->id,
        'quantity' => 10,
        'potong_id' => $worker->id,
        'potong_date' => now(),
        'status' => Produksi::STATUS_SETOR,
        'item_id' => $item->id,
        'invoice' => null,
    ]);

    $response = $this->actingAs($this->user)->get('/produksi/setoran');

    $response->assertSuccessful();
    $response->assertSee('openUpdate('.$produksi->id, false);
    $response->assertSee('AJD-CX90233-23-S');
});

it('locks kode on setoran row when invoice is present', function () {
    $worker = Worker::create(['name' => 'Cutter', 'type' => Worker::TYPE_POTONG]);
    $size = Tag::create(['name' => 'L', 'type' => Tag::TYPE_SIZE, 'item_type' => 0]);
    $item = Item::factory()->create(['code' => 'AJD-CX90233-23-S']);

    $produksi = Produksi::create([
        'temp_name' => 'Temp Name',
        'size_id' => $size->id,
        'quantity' => 10,
        'potong_id' => $worker->id,
        'potong_date' => now(),
        'status' => Produksi::STATUS_GUDANG,
        'item_id' => $item->id,
        'invoice' => 'INV-001',
    ]);

    $response = $this->actingAs($this->user)->get('/produksi/setoran');

    $response->assertSuccessful();
    $response->assertDontSee('openUpdate('.$produksi->id, false);
    $response->assertSee('AJD-CX90233-23-S');
    $response->assertSee('INV-001');
});

it('rejects item code update when status is no longer setor', function () {
    $worker = Worker::create(['name' => 'Cutter', 'type' => Worker::TYPE_POTONG]);
    $size = Tag::create(['name' => 'L', 'type' => Tag::TYPE_SIZE, 'item_type' => 0]);
    $item = Item::factory()->create(['code' => 'OLD-CODE']);
    $newItem = Item::factory()->create(['code' => 'NEW-CODE']);

    $produksi = Produksi::create([
        'temp_name' => 'Temp Name',
        'size_id' => $size->id,
        'quantity' => 10,
        'potong_id' => $worker->id,
        'potong_date' => now(),
        'status' => Produksi::STATUS_GUDANG,
        'item_id' => $item->id,
        'invoice' => 'INV-LOCK',
    ]);

    $response = $this->actingAs($this->user)->patch("/produksi/setoran/{$produksi->id}/edit-item", [
        'item_id' => $newItem->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('error');
    expect($produksi->fresh()->item_id)->toBe($item->id);
});

it('shows qc assignment dropdown on setoran index', function () {
    $qc = Worker::create(['name' => 'QC Budi', 'type' => Worker::TYPE_QC]);
    $worker = Worker::create(['name' => 'Cutter', 'type' => Worker::TYPE_POTONG]);
    $size = Tag::create(['name' => 'L', 'type' => Tag::TYPE_SIZE, 'item_type' => 0]);

    Produksi::create([
        'temp_name' => 'QC Row',
        'size_id' => $size->id,
        'quantity' => 5,
        'potong_id' => $worker->id,
        'potong_date' => now(),
        'status' => Produksi::STATUS_SETOR,
        'qc_id' => $qc->id,
    ]);

    $response = $this->actingAs($this->user)->get('/produksi/setoran');

    $response->assertSuccessful();
    $response->assertSee('QC Budi');
    $response->assertSee('name="qc_id"', false);
});

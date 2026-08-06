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

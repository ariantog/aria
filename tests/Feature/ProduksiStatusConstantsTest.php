<?php

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

it('uses legacy L10 status values for produksi and setoran lists', function () {
    expect(Produksi::STATUS_PRODUKSI)->toBe(0);
    expect(Produksi::STATUS_SETOR)->toBe(1);
    expect(Produksi::STATUS_BAYAR)->toBe(3);
    expect(Produksi::STATUS_GUDANG)->toBe(5);
    expect(Produksi::STATUS_BOTH)->toBe(15);
});

it('routes rows to produksi and setoran pages using legacy status values', function () {
    $worker = Worker::create(['name' => 'Cutter', 'type' => Worker::TYPE_POTONG]);
    $size = Tag::create(['name' => 'L', 'type' => Tag::TYPE_SIZE, 'item_type' => 0]);

    $inProduksi = Produksi::create([
        'temp_name' => 'Still Cutting',
        'size_id' => $size->id,
        'quantity' => 5,
        'potong_id' => $worker->id,
        'potong_date' => now(),
        'status' => 0,
    ]);

    $inSetoran = Produksi::create([
        'temp_name' => 'Already Setor',
        'size_id' => $size->id,
        'quantity' => 8,
        'potong_id' => $worker->id,
        'potong_date' => now(),
        'status' => 1,
    ]);

    $paidNotWarehouse = Produksi::create([
        'temp_name' => 'Paid Not Warehouse',
        'size_id' => $size->id,
        'quantity' => 3,
        'potong_id' => $worker->id,
        'potong_date' => now(),
        'status' => 3,
    ]);

    $produksiResponse = $this->actingAs($this->user)->get('/produksi');
    $produksiResponse->assertSuccessful();
    $produksiResponse->assertSee('Still Cutting');
    $produksiResponse->assertDontSee('Already Setor');
    $produksiResponse->assertDontSee('Paid Not Warehouse');

    $setoranResponse = $this->actingAs($this->user)->get('/produksi/setoran');
    $setoranResponse->assertSuccessful();
    $setoranResponse->assertSee('Already Setor');
    $setoranResponse->assertSee('Paid Not Warehouse');
    $setoranResponse->assertDontSee('Still Cutting');
});

it('moves a produksi row to setoran with legacy status value 1', function () {
    $worker = Worker::create(['name' => 'Cutter', 'type' => Worker::TYPE_POTONG]);
    $jahit = Worker::create(['name' => 'Jahit', 'type' => Worker::TYPE_JAHIT]);
    $size = Tag::create(['name' => 'L', 'type' => Tag::TYPE_SIZE, 'item_type' => 0]);

    $produksi = Produksi::create([
        'temp_name' => 'Move Me',
        'size_id' => $size->id,
        'quantity' => 4,
        'potong_id' => $worker->id,
        'potong_date' => now(),
        'jahit_id' => $jahit->id,
        'jahit_date' => now(),
        'status' => Produksi::STATUS_PRODUKSI,
    ]);

    $response = $this->actingAs($this->user)->patch("/produksi/{$produksi->id}/setor");
    $response->assertRedirect();

    $produksi->refresh();
    expect($produksi->status)->toBe(1);
    expect($produksi->setor_date)->not->toBeNull();
});

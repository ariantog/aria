<?php

use App\Actions\Produksi\SendToWarehouse;
use App\Enums\AddrbookType;
use App\Enums\TransactionType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Produksi;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\TransactionDetail;
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

it('stores setoran row into warehouse with transaction detail audit columns', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    Setting::query()->updateOrCreate(
        ['slug' => 'produksi.default_warehouse_id'],
        ['name' => 'Default Produksi Warehouse', 'value' => (string) $warehouse->id, 'location_id' => 0],
    );

    $worker = Worker::create(['name' => 'Cutter', 'type' => Worker::TYPE_POTONG]);
    $size = Tag::create(['name' => 'L', 'type' => Tag::TYPE_SIZE, 'item_type' => 0]);
    $item = Item::factory()->create(['code' => 'SETOR-ITEM']);

    $produksi = Produksi::create([
        'temp_name' => 'Setor Item',
        'size_id' => $size->id,
        'quantity' => 5,
        'potong_id' => $worker->id,
        'potong_date' => now(),
        'status' => Produksi::STATUS_SETOR,
        'item_id' => $item->id,
        'invoice' => null,
    ]);

    $response = $this->actingAs($this->user)->patch("/produksi/setoran/{$produksi->id}/gudang", [
        'invoice' => 'PROD-INV-001',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $produksi->refresh();
    expect($produksi->status)->toBe(Produksi::STATUS_GUDANG);
    expect($produksi->invoice)->toBe('PROD-INV-001');

    $transaction = Transaction::where('invoice', 'PROD-INV-001')
        ->where('type', TransactionType::Production->value)
        ->first();

    expect($transaction)->not->toBeNull();
    expect($transaction->receiver_id)->toBe($warehouse->id);
    expect($transaction->receiver_type)->toBe(AddrbookType::Warehouse->value);

    $detail = TransactionDetail::find($produksi->detail_id);
    expect($detail)->not->toBeNull();
    expect($detail->date->toDateString())->toBe($transaction->date->toDateString());
    expect($detail->transaction_type)->toBe(TransactionType::Production->value);
    expect($detail->sender_id)->toBe(0);
    expect($detail->receiver_id)->toBe($warehouse->id);
    expect((float) $detail->quantity)->toBe(5.0);
});

it('send to warehouse action fills transaction detail audit columns', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    Setting::query()->updateOrCreate(
        ['slug' => 'produksi.default_warehouse_id'],
        ['name' => 'Default Produksi Warehouse', 'value' => (string) $warehouse->id, 'location_id' => 0],
    );

    $worker = Worker::create(['name' => 'Cutter', 'type' => Worker::TYPE_POTONG]);
    $size = Tag::create(['name' => 'L', 'type' => Tag::TYPE_SIZE, 'item_type' => 0]);
    $item = Item::factory()->create(['code' => 'ACTION-ITEM']);

    $produksi = Produksi::create([
        'temp_name' => 'Action Item',
        'size_id' => $size->id,
        'quantity' => 3,
        'potong_id' => $worker->id,
        'potong_date' => now(),
        'status' => Produksi::STATUS_SETOR,
        'item_id' => $item->id,
        'invoice' => null,
    ]);

    app(SendToWarehouse::class)->execute($produksi, 'PROD-INV-002', $this->user->id);

    $detail = TransactionDetail::find($produksi->fresh()->detail_id);
    $transaction = Transaction::find($produksi->fresh()->transaction_id);

    expect($detail->date->toDateString())->toBe($transaction->date->toDateString());
    expect($detail->transaction_type)->toBe(TransactionType::Production->value);
    expect($detail->receiver_id)->toBe($warehouse->id);
});

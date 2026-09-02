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

    $parentSerial = strtoupper(base_convert((string) $parent->id, 10, 36));

    $response = $this->actingAs($this->user)->get('/produksi');

    $response->assertSuccessful();
    $response->assertSee($parentSerial);
    $response->assertSee('Parent', false);
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

it('splits produksi quantity and leaves the new row unassigned for jahit/qc', function () {
    $cutter = Worker::create(['name' => 'Cutter', 'type' => Worker::TYPE_POTONG]);
    $jahit = Worker::create(['name' => 'Jahit One', 'type' => Worker::TYPE_JAHIT]);
    $qc = Worker::create(['name' => 'QC One', 'type' => Worker::TYPE_QC]);
    $size = Tag::create(['name' => 'L', 'type' => Tag::TYPE_SIZE, 'item_type' => 0]);

    $produksi = Produksi::create([
        'temp_name' => 'APJ CJ00414',
        'customer' => 'RIZKY W',
        'warna' => 'HITAM',
        'size_id' => $size->id,
        'quantity' => 10,
        'potong_id' => $cutter->id,
        'potong_date' => now(),
        'jahit_id' => $jahit->id,
        'jahit_date' => now(),
        'qc_id' => $qc->id,
        'qc_date' => now(),
        'status' => Produksi::STATUS_PRODUKSI,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->post("/produksi/{$produksi->id}/split", [
        'split_q' => 3,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $produksi->refresh();
    expect($produksi->quantity)->toBe(7)
        ->and((int) $produksi->jahit_id)->toBe($jahit->id)
        ->and((int) $produksi->qc_id)->toBe($qc->id);

    $split = Produksi::query()->where('original_id', $produksi->id)->first();
    expect($split)->not->toBeNull()
        ->and($split->quantity)->toBe(3)
        ->and($split->temp_name)->toBe('APJ CJ00414')
        ->and($split->customer)->toBe('RIZKY W')
        ->and((int) $split->jahit_id)->toBe(0)
        ->and($split->jahit_date)->toBeNull()
        ->and((int) $split->qc_id)->toBe(0)
        ->and($split->qc_date)->toBeNull()
        ->and((int) $split->pritil_id)->toBe(0)
        ->and($split->pritil_date)->toBeNull();
});

it('rejects splitting the full produksi quantity', function () {
    $produksi = Produksi::create([
        'temp_name' => 'Cannot Split All',
        'quantity' => 5,
        'status' => Produksi::STATUS_PRODUKSI,
    ]);

    $response = $this->actingAs($this->user)->post("/produksi/{$produksi->id}/split", [
        'split_q' => 5,
    ]);

    $response->assertSessionHasErrors('split_q');
    expect(Produksi::query()->where('original_id', $produksi->id)->exists())->toBeFalse();
    expect($produksi->fresh()->quantity)->toBe(5);
});

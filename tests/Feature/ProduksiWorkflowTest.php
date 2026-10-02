<?php

use App\Models\Produksi;
use App\Models\Tag;
use App\Models\User;
use App\Models\Worker;
use Spatie\Permission\Models\Permission;
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
        ->and($split->jahit_id)->toBeNull()
        ->and($split->jahit_date)->toBeNull()
        ->and($split->qc_id)->toBeNull()
        ->and($split->qc_date)->toBeNull()
        ->and($split->pritil_id)->toBeNull()
        ->and($split->pritil_date)->toBeNull();
});

it('shows the jumlah total for the rendered produksi page', function () {
    $worker = Worker::create(['name' => 'Cutter', 'type' => Worker::TYPE_POTONG]);
    $size = Tag::create(['name' => 'L', 'type' => Tag::TYPE_SIZE, 'item_type' => 0]);

    Produksi::create([
        'temp_name' => 'Qty Ten',
        'size_id' => $size->id,
        'quantity' => 10,
        'potong_id' => $worker->id,
        'potong_date' => now(),
        'status' => Produksi::STATUS_PRODUKSI,
    ]);
    Produksi::create([
        'temp_name' => 'Qty Seven',
        'size_id' => $size->id,
        'quantity' => 7,
        'potong_id' => $worker->id,
        'potong_date' => now(),
        'status' => Produksi::STATUS_PRODUKSI,
    ]);

    $response = $this->actingAs($this->user)->get('/produksi');

    $response->assertSuccessful();
    $response->assertSee('data-testid="produksi-page-jumlah-total"', false);
    $response->assertSee('>17</td>', false);
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

it('deletes a produksi row when user has production-delete permission', function () {
    $worker = Worker::create(['name' => 'Cutter', 'type' => Worker::TYPE_POTONG]);
    $size = Tag::create(['name' => 'L', 'type' => Tag::TYPE_SIZE, 'item_type' => 0]);

    $produksi = Produksi::create([
        'temp_name' => 'To Delete',
        'size_id' => $size->id,
        'quantity' => 5,
        'potong_id' => $worker->id,
        'potong_date' => now(),
        'status' => Produksi::STATUS_PRODUKSI,
    ]);

    $response = $this->actingAs($this->user)->delete("/produksi/{$produksi->id}");

    $response->assertRedirect(route('produksi.index'));
    $this->assertSoftDeleted('prod_produksi', ['id' => $produksi->id]);
});

it('forbids deleting produksi without production-delete permission', function () {
    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'production-list', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'production-edit', 'guard_name' => 'web']);
    $user->givePermissionTo(['production-list', 'production-edit']);

    $produksi = Produksi::create([
        'temp_name' => 'Protected',
        'quantity' => 3,
        'status' => Produksi::STATUS_PRODUKSI,
    ]);

    $response = $this->actingAs($user)->delete("/produksi/{$produksi->id}");

    $response->assertForbidden();
    $this->assertDatabaseHas('prod_produksi', ['id' => $produksi->id, 'deleted_at' => null]);
});

it('shows delete control on edit only with production-delete permission', function () {
    $produksi = Produksi::create([
        'temp_name' => 'Edit Delete UI',
        'quantity' => 4,
        'status' => Produksi::STATUS_PRODUKSI,
    ]);

    $this->actingAs($this->user)->get("/produksi/{$produksi->id}/edit")
        ->assertSuccessful()
        ->assertSee('data-testid="produksi-delete-row"', false);

    $viewer = User::factory()->create();
    Permission::firstOrCreate(['name' => 'production-list', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'production-edit', 'guard_name' => 'web']);
    $viewer->givePermissionTo(['production-list', 'production-edit']);

    $this->actingAs($viewer)->get("/produksi/{$produksi->id}/edit")
        ->assertSuccessful()
        ->assertDontSee('data-testid="produksi-delete-row"', false);
});

it('does not delete produksi rows that are no longer in produksi status', function () {
    $produksi = Produksi::create([
        'temp_name' => 'Already Setor',
        'quantity' => 2,
        'status' => Produksi::STATUS_SETOR,
    ]);

    $response = $this->actingAs($this->user)->from("/produksi/{$produksi->id}/edit")
        ->delete("/produksi/{$produksi->id}");

    $response->assertRedirect("/produksi/{$produksi->id}/edit");
    $response->assertSessionHasErrors('error');
    $this->assertDatabaseHas('prod_produksi', ['id' => $produksi->id, 'deleted_at' => null]);
});

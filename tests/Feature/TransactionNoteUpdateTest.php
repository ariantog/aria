<?php

use App\Models\Addrbook;
use App\Models\Transaction;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'transactions-edit']);
    Permission::firstOrCreate(['name' => 'transactions-list']);
    Permission::firstOrCreate(['name' => 'transactions-show']);

    User::factory()->create(); // id 1 — superadmin; keep note-permission tests on a normal user
    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['transactions-edit', 'transactions-list', 'transactions-show']);

    $this->supplier = Addrbook::factory()->supplier()->create();
    $this->warehouse = Addrbook::factory()->warehouse()->create();

    $this->transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_BUY,
        'invoice' => 'INV-NOTE-TEST',
        'sender_type' => (string) Addrbook::TYPE_SUPPLIER,
        'sender_id' => $this->supplier->id,
        'receiver_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'receiver_id' => $this->warehouse->id,
        'notes' => 'Old note',
        'description' => 'Old note',
        'user_id' => $this->user->id,
    ]);
});

it('updates a transaction note via patch', function () {
    $this->actingAs($this->user)
        ->patchJson(route('transactions.update-note', $this->transaction), [
            'note' => 'Updated shipment note',
        ])
        ->assertSuccessful()
        ->assertJson([
            'note' => 'Updated shipment note',
            'display' => 'Updated shipment note',
        ]);

    $this->transaction->refresh();

    expect($this->transaction->notes)->toBe('Updated shipment note')
        ->and($this->transaction->description)->toBe('Updated shipment note');
});

it('clears a transaction note when empty string is sent', function () {
    $this->actingAs($this->user)
        ->patchJson(route('transactions.update-note', $this->transaction), [
            'note' => '',
        ])
        ->assertSuccessful()
        ->assertJson([
            'note' => null,
            'display' => '-',
        ]);

    $this->transaction->refresh();

    expect($this->transaction->notes)->toBeNull()
        ->and($this->transaction->description)->toBe('');
});

it('forbids note updates without edit permission', function () {
    $this->user->syncPermissions(['transactions-list']);

    $this->actingAs($this->user)
        ->patchJson(route('transactions.update-note', $this->transaction), [
            'note' => 'Should not save',
        ])
        ->assertForbidden();

    expect($this->transaction->fresh()->notes)->toBe('Old note');
});

it('shows edit note controls on the transactions index for editors', function () {
    $this->actingAs($this->user)
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertSee('Description', false)
        ->assertSee('data-testid="edit-tx-note-'.$this->transaction->id.'"', false);
});

it('hides edit note controls without edit permission', function () {
    $this->user->syncPermissions(['transactions-list']);

    $this->actingAs($this->user)
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertSee('Description', false)
        ->assertDontSee('data-testid="edit-tx-note-'.$this->transaction->id.'"', false);
});

it('shows edit description control on the transaction show page for editors', function () {
    $this->actingAs($this->user)
        ->get(route('transactions.show', $this->transaction))
        ->assertOk()
        ->assertSee('data-testid="edit-tx-show-note"', false)
        ->assertSee('Old note', false);
});

it('hides edit description control on the transaction show page without edit permission', function () {
    $this->user->syncPermissions(['transactions-list', 'transactions-show']);

    $this->actingAs($this->user)
        ->get(route('transactions.show', $this->transaction))
        ->assertOk()
        ->assertDontSee('data-testid="edit-tx-show-note"', false);
});

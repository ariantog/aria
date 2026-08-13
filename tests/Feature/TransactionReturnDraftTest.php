<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Models\WarehouseItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('drafts a return from a sell transaction with swapped parties and prefilled rows', function () {
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Pusat']);
    $customer = Addrbook::factory()->customer()->create(['name' => 'Toko Maju']);
    $item = Item::factory()->create(['name' => 'Kaos Hitam', 'code' => 'KAO-01', 'price' => 100_000]);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => 10,
    ]);

    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'INV-RET-001',
        'notes' => 'Catatan penjualan',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
        'discount' => 0,
        'adjustment' => 0,
        'real_total' => -100_000,
        'total' => 100_000,
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $sell->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'price' => 100_000,
        'discount' => 0,
        'total' => 100_000,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.create', ['type' => 'return', 'from' => $sell->id]))
        ->assertOk()
        ->assertSee('Toko Maju', false)
        ->assertSee('Gudang Pusat', false)
        ->assertSee('INV-RET-001', false)
        ->assertSee('Kaos Hitam', false)
        ->assertSee('return', false)
        ->assertSee('Catatan penjualan', false);
});

it('drafts a return supplier from a buy transaction', function () {
    $supplier = Addrbook::factory()->supplier()->create(['name' => 'PT Sumber']);
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Utama']);
    $item = Item::factory()->create(['name' => 'Kain Katun', 'code' => 'KN-01', 'cost' => 50_000]);

    $buy = Transaction::factory()->create([
        'type' => Transaction::TYPE_BUY,
        'invoice' => 'PO-100',
        'sender_id' => $supplier->id,
        'receiver_id' => $warehouse->id,
        'sender_type' => (string) Addrbook::TYPE_SUPPLIER,
        'receiver_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'real_total' => 100_000,
        'total' => 100_000,
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $buy->id,
        'item_id' => $item->id,
        'quantity' => 2,
        'price' => 50_000,
        'discount' => 0,
        'total' => 100_000,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.create', ['type' => 'return-supplier', 'from' => $buy->id]))
        ->assertOk()
        ->assertSee('Gudang Utama', false)
        ->assertSee('PT Sumber', false)
        ->assertSee('PO-100', false)
        ->assertSee('Kain Katun', false);
});

it('drafts an opposite move from a move transaction', function () {
    $source = Addrbook::factory()->warehouse()->create(['name' => 'Gudang A']);
    $destination = Addrbook::factory()->warehouse()->create(['name' => 'Gudang B']);
    $item = Item::factory()->create(['name' => 'Barang Pindah', 'code' => 'MOV-01', 'price' => 25_000]);

    WarehouseItem::create([
        'warehouse_id' => $destination->id,
        'item_id' => $item->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => 8,
    ]);

    $move = Transaction::factory()->create([
        'type' => Transaction::TYPE_MOVE,
        'invoice' => 'MOV-100',
        'notes' => 'Pindah stok',
        'sender_id' => $source->id,
        'receiver_id' => $destination->id,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'receiver_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'real_total' => 50_000,
        'total' => 50_000,
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $move->id,
        'item_id' => $item->id,
        'quantity' => 2,
        'price' => 25_000,
        'discount' => 0,
        'total' => 50_000,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.create', ['type' => 'move', 'from' => $move->id]))
        ->assertOk()
        ->assertSee('Gudang B', false)
        ->assertSee('Gudang A', false)
        ->assertSee('MOV-100', false)
        ->assertSee('Barang Pindah', false)
        ->assertSee('return', false)
        ->assertSee('Pindah stok', false);
});

it('shows the return button on buy, sell, and move detail pages', function () {
    $sell = Transaction::factory()->create(['type' => Transaction::TYPE_SELL]);
    $buy = Transaction::factory()->create(['type' => Transaction::TYPE_BUY]);
    $move = Transaction::factory()->create(['type' => Transaction::TYPE_MOVE]);
    $cashIn = Transaction::factory()->create(['type' => Transaction::TYPE_CASH_IN]);

    $sellUrl = route('transactions.create', ['type' => 'return', 'from' => $sell->id]);
    $buyUrl = route('transactions.create', ['type' => 'return-supplier', 'from' => $buy->id]);
    $moveUrl = route('transactions.create', ['type' => 'move', 'from' => $move->id]);

    $this->actingAs($this->user)->get(route('transactions.show', $sell))
        ->assertOk()
        ->assertSee($sellUrl, false)
        ->assertDontSee('/draft-return', false);
    $this->actingAs($this->user)->get(route('transactions.show', $buy))
        ->assertOk()
        ->assertSee($buyUrl, false)
        ->assertDontSee('/draft-return', false);
    $this->actingAs($this->user)->get(route('transactions.show', $move))
        ->assertOk()
        ->assertSee($moveUrl, false)
        ->assertDontSee('/draft-return', false);
    $this->actingAs($this->user)->get(route('transactions.show', $cashIn))
        ->assertOk()
        ->assertDontSee('data-testid="draft-return-button"', false)
        ->assertDontSee('/draft-return', false);
});

it('submits a drafted return transaction with the same invoice and line totals', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create(['price' => 80_000]);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => 5,
    ]);

    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'INV-SUBMIT-1',
        'notes' => 'Original note',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
        'real_total' => -80_000,
        'total' => 80_000,
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $sell->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'price' => 80_000,
        'discount' => 0,
        'total' => 80_000,
    ]);

    $response = $this->actingAs($this->user)->post(route('transactions.store'), [
        'date' => now()->toDateString(),
        'type' => 'return',
        'sender_id' => $customer->id,
        'receiver_id' => $warehouse->id,
        'invoice' => 'INV-SUBMIT-1',
        'note' => "return\nOriginal note",
        'items' => [
            [
                'item_id' => $item->id,
                'quantity' => 1,
                'price' => 80_000,
                'discount' => 0,
            ],
        ],
        'discount' => 0,
        'adjustment' => 0,
    ]);

    $return = Transaction::query()->where('type', Transaction::TYPE_RETURN)->latest('id')->first();
    $response->assertRedirect(route('transactions.show', $return));

    expect($return)->not->toBeNull()
        ->and($return->invoice)->toBe('INV-SUBMIT-1')
        ->and($return->sender_id)->toBe($customer->id)
        ->and($return->receiver_id)->toBe($warehouse->id)
        ->and($return->notes)->toBe("return\nOriginal note")
        ->and((float) $return->real_total)->toBe(80_000.0);
});

it('rejects return prefill from unsupported transaction types', function () {
    $cashIn = Transaction::factory()->create(['type' => Transaction::TYPE_CASH_IN]);
    TransactionDetail::factory()->create(['transaction_id' => $cashIn->id]);

    $this->actingAs($this->user)
        ->get(route('transactions.create', ['type' => 'return', 'from' => $cashIn->id]))
        ->assertStatus(422);
});

it('loads a large sell transaction return prefill from the source id query param', function () {
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Besar']);
    $customer = Addrbook::factory()->customer()->create(['name' => 'Toko Besar']);

    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'INV-LARGE-001',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
        'real_total' => -5_000_000,
        'total' => 5_000_000,
    ]);

    for ($i = 1; $i <= 150; $i++) {
        $item = Item::factory()->create([
            'name' => "Bulk Item {$i}",
            'code' => sprintf('BLK-%03d', $i),
            'price' => 10_000 + $i,
        ]);

        WarehouseItem::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
            'quantity' => 100,
        ]);

        TransactionDetail::factory()->create([
            'transaction_id' => $sell->id,
            'item_id' => $item->id,
            'quantity' => 2,
            'price' => 10_000 + $i,
            'discount' => 0,
            'total' => (10_000 + $i) * 2,
        ]);
    }

    $this->actingAs($this->user)
        ->get(route('transactions.create', ['type' => 'return', 'from' => $sell->id]))
        ->assertOk()
        ->assertSee('INV-LARGE-001', false)
        ->assertSee('Bulk Item 1', false)
        ->assertSee('Bulk Item 150', false);
});

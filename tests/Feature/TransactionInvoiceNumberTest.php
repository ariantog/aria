<?php

namespace Tests\Feature;

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionInvoiceNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_defaults_to_id_when_empty()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Setup data
        $supplier = Addrbook::create([
            'name' => 'Supplier A',
            'type' => Addrbook::TYPE_SUPPLIER,
            'code' => 'SUP001',
        ]);
        $warehouse = \App\Models\Location::create([
            'name' => 'Warehouse A',
            'code' => 'WH001',
            'type' => 'warehouse',
        ]);
        $item = Item::factory()->create();

        $response = $this->post(route('transactions.store'), [
            'date' => now()->toDateString(),
            'type' => 'buy',
            'sender_id' => $supplier->id,
            'receiver_id' => $warehouse->id,
            'sender_type' => Addrbook::class,
            'receiver_type' => \App\Models\Location::class,
            'invoice' => '', // Empty
            'note' => 'Test transaction note',
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 10,
                    'price' => 1000,
                    'discount' => 0,
                    'note' => 'Item note',
                ],
            ],
        ]);

        $transaction = Transaction::first();
        $response->assertRedirect(route('transactions.show', $transaction));

        $this->assertEquals($transaction->id, $transaction->invoice);
        $this->assertEquals('Test transaction note', $transaction->notes);
    }

    public function test_invoice_stays_as_provided_when_not_empty()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Setup data
        $supplier = Addrbook::create([
            'name' => 'Supplier B',
            'type' => Addrbook::TYPE_SUPPLIER,
            'code' => 'SUP002',
        ]);
        $warehouse = \App\Models\Location::create([
            'name' => 'Warehouse B',
            'code' => 'WH002',
            'type' => 'warehouse',
        ]);
        $item = Item::factory()->create();

        $response = $this->post(route('transactions.store'), [
            'date' => now()->toDateString(),
            'type' => 'buy',
            'sender_id' => $supplier->id,
            'receiver_id' => $warehouse->id,
            'sender_type' => Addrbook::class,
            'receiver_type' => \App\Models\Location::class,
            'invoice' => 'INV-12345',
            'note' => 'Test transaction note',
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 10,
                    'price' => 1000,
                    'discount' => 0,
                    'note' => 'Item note',
                ],
            ],
        ]);

        $transaction = Transaction::first();
        $this->assertEquals('INV-12345', $transaction->invoice);
    }

    public function test_gracefully_handles_missing_polymorphic_types()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $supplier = Addrbook::create(['name' => 'S1', 'type' => Addrbook::TYPE_SUPPLIER, 'code' => 'C1']);
        $warehouse = Addrbook::create(['name' => 'W1', 'type' => Addrbook::TYPE_WAREHOUSE, 'code' => 'C2']);

        // SQLite foreign key workaround: warehouse_item.warehouse_id is constrained to locations.id
        // Both sender and receiver might trigger stock updates, so both IDs must exist in locations.
        \App\Models\Location::forceCreate(['id' => $supplier->id, 'name' => 'D1']);
        \App\Models\Location::forceCreate(['id' => $warehouse->id, 'name' => 'D2']);

        $item = Item::factory()->create();

        $response = $this->post(route('transactions.store'), [
            'date' => now()->toDateString(),
            'type' => 'buy',
            'sender_id' => $supplier->id,
            'receiver_id' => $warehouse->id,
            // sender_type and receiver_type OMITTED
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1, 'price' => 100],
            ],
        ]);

        $transaction = Transaction::latest('id')->first();

        $response->assertRedirect(route('transactions.show', $transaction));

        // Assert it defaults to the IDs from config or fallback constants
        // In the test, we omitted type, so it uses default from config or whatever store logic does.
        // Wait, the test uses 'type' => 'buy'. Config for 'buy' has specific types.
        // config('transaction_rules.buy.sender_type') is Addrbook::TYPE_SUPPLIER
        // config('transaction_rules.buy.receiver_type') is Addrbook::TYPE_WAREHOUSE

        $this->assertEquals(Addrbook::TYPE_SUPPLIER, $transaction->sender_type);
        $this->assertEquals(Addrbook::TYPE_WAREHOUSE, $transaction->receiver_type);

        // This confirms relations work (belongsTo Addrbook)
        $this->assertNotNull($transaction->receiver);
    }
}

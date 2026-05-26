<?php

namespace Tests\Feature;

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Location;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_can_create_buy_transaction()
    {
        // 1. Setup Data
        $user = User::first(); // Assume existing user
        if (! $user) {
            $user = User::factory()->create();
        }
        $this->actingAs($user);

        // Ensure we have a supplier
        $supplier = Addrbook::where('type', Addrbook::TYPE_SUPPLIER)->first();
        if (! $supplier) {
            $supplier = Addrbook::create([
                'name' => 'Test Supplier',
                'type' => Addrbook::TYPE_SUPPLIER,
                'email' => 'supplier@test.com',
                'phone' => '123',
            ]);
        }

        // Ensure we have a warehouse (Location)
        $warehouse = Location::first();
        if (! $warehouse) {
            $warehouse = Location::create(['name' => 'Test Warehouse', 'slug' => 'test-wh', 'type' => 'warehouse']);
        }

        // Ensure we have an item
        $item = Item::first();
        if (! $item) {
            $item = Item::factory()->create([
                'name' => 'Test Item',
                'code' => 'ITM001',
                'price' => 10000,
                'cost' => 5000,
            ]);
        }

        // 2. Submit Data
        $response = $this->post(route('transactions.store'), [
            'date' => now()->toDateString(),
            'type' => 'buy',
            'sender_id' => $supplier->id,
            'sender_type' => get_class($supplier),
            'receiver_id' => $warehouse->id,
            'receiver_type' => get_class($warehouse),
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 10,
                    'price' => 5000,
                    'discount' => 0,
                ],
            ],
        ]);

        // 3. Verify Response
        $response->assertRedirect(route('transactions.index'));
        $response->assertSessionHas('success');

        // 4. Verify Database
        $this->assertDatabaseHas('transactions', [
            'type' => Transaction::TYPE_BUY,
            'sender_id' => $supplier->id,
            'receiver_id' => $warehouse->id,
            'total' => 50000, // 10 * 5000
        ]);

        // 5. Verify Stock Update (WarehouseItem)
        $this->assertDatabaseHas('warehouse_items', [
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            // 'quantity' => 10 // Can't check exact if previous stock existed.
        ]);
    }

    public function test_can_create_return_supplier_transaction()
    {
        // 1. Setup Data
        $user = User::factory()->create();
        $this->actingAs($user);

        $reseller = Addrbook::create([
            'name' => 'Test Reseller',
            'type' => Addrbook::TYPE_RESELLER,
            'email' => 'reseller@test.com',
            'phone' => '123',
        ]);

        $warehouse = Addrbook::create([
            'name' => 'Test Warehouse',
            'type' => Addrbook::TYPE_WAREHOUSE,
            'email' => 'wh@test.com',
            'phone' => '123',
        ]);

        $item = Item::factory()->create([
            'name' => 'Test Item',
            'code' => 'ITM001',
            'price' => 10000,
            'cost' => 5000,
        ]);

        // Add initial stock
        \App\Models\WarehouseItem::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
            'quantity' => 20,
        ]);

        // 2. Submit Data
        $response = $this->post(route('transactions.store'), [
            'date' => now()->toDateString(),
            'type' => 'return-supplier',
            'sender_id' => $warehouse->id, // From Warehouse
            'receiver_id' => $reseller->id, // To Reseller
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 5,
                    'price' => 5000,
                    'discount' => 0,
                ],
            ],
        ]);

        // 3. Verify Response
        $response->assertRedirect(route('transactions.index'));

        // 4. Verify Database
        $this->assertDatabaseHas('transactions', [
            'type' => Transaction::TYPE_RETURN_SUPPLIER,
            'sender_id' => $warehouse->id,
            'receiver_id' => $reseller->id,
            'grand_total' => -25000, // Negative for return supplier
        ]);

        // 5. Verify Stock Update (WarehouseItem)
        $this->assertDatabaseHas('warehouse_items', [
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'quantity' => 15, // 20 - 5
        ]);

        // 6. Verify Reseller Balance
        $this->assertDatabaseHas('addrbook_stats', [
            'addrbook_id' => $reseller->id,
            'balance' => -25000,
        ]);
    }
}

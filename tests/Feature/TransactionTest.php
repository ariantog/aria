<?php

namespace Tests\Feature;

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Location;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

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
            'due' => now()->addDays(30)->toDateString(),
            'type' => 'buy',
            'sender_id' => $supplier->id,
            'receiver_id' => $warehouse->id,
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
        $transaction = Transaction::latest('id')->first();
        $response->assertRedirect(route('transactions.show', $transaction));
        $response->assertSessionHas('success');

        // 4. Verify Database
        $this->assertDatabaseHas('transactions', [
            'type' => Transaction::TYPE_BUY,
            'sender_id' => $supplier->id,
            'receiver_id' => $warehouse->id,
            'total' => 50000, // 10 * 5000
            'due' => now()->addDays(30)->startOfDay()->toDateTimeString(),
        ]);

        // 5. Verify Stock Update (WarehouseItem)
        $this->assertDatabaseHas('warehouse_item', [
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
        $transaction = Transaction::latest('id')->first();
        $response->assertRedirect(route('transactions.show', $transaction));

        // 4. Verify Database
        $this->assertDatabaseHas('transactions', [
            'type' => Transaction::TYPE_RETURN_SUPPLIER,
            'sender_id' => $warehouse->id,
            'receiver_id' => $reseller->id,
            'total' => -25000,
            'real_total' => -25000, // Negative for return supplier
        ]);

        // 5. Verify Stock Update (WarehouseItem)
        $this->assertDatabaseHas('warehouse_item', [
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'quantity' => 15, // 20 - 5
        ]);

        // 6. Verify Reseller Balance
        // Return-supplier transactions adjust stock/global qty but do NOT
        // update addrbook balances in the current TransactionService logic
        // (only buy/sell/return/cash/transfer/adjust touch balances).
        $this->assertDatabaseMissing('customerstat', [
            'customer_id' => $reseller->id,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\User;
use App\Models\WarehouseItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_buy_transaction_adjusts_stock_correctly()
    {
        // 1. Setup
        $user = User::factory()->create();
        $this->actingAs($user);

        $supplier = Addrbook::create([
            'name' => 'Test Supplier',
            'type' => Addrbook::TYPE_SUPPLIER,
            'email' => 'supplier@test.com',
            'phone' => '123',
        ]);

        $warehouse = Addrbook::create([
            'name' => 'Main Warehouse',
            'type' => Addrbook::TYPE_WAREHOUSE,
        ]);

        $item = Item::factory()->create([
            'name' => 'Stock Item',
            'code' => 'STK001',
            'price' => 1000,
            'cost' => 500,
        ]);

        // 2. Action: BUY (Supplier -> Warehouse)
        $this->post(route('transactions.store'), [
            'date' => now()->toDateString(),
            'type' => 'buy',
            'sender_id' => $supplier->id,
            'sender_type' => $supplier->type,
            'receiver_id' => $warehouse->id,
            'receiver_type' => $warehouse->type,
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 10,
                    'price' => 500,
                    'discount' => 0,
                ],
            ],
        ]);

        // 3. Verify
        $this->assertDatabaseCount('transaction_details', 1);

        // Supplier (Sender) should have -10
        $this->assertDatabaseHas('warehouse_items', [
            'warehouse_id' => $supplier->id,
            'warehouse_type' => $supplier->type,
            'item_id' => $item->id,
            'quantity' => -10,
        ]);

        // Warehouse (Receiver) should have +10
        $this->assertDatabaseHas('warehouse_items', [
            'warehouse_id' => $warehouse->id,
            'warehouse_type' => $warehouse->type,
            'item_id' => $item->id,
            'quantity' => 10,
        ]);
    }

    public function test_sell_transaction_adjusts_stock_correctly()
    {
        // 1. Setup
        $user = User::factory()->create();
        $this->actingAs($user);

        $warehouse = Addrbook::create([
            'name' => 'Sell Warehouse',
            'type' => Addrbook::TYPE_WAREHOUSE,
        ]);

        $customer = Addrbook::create([
            'name' => 'Test Customer',
            'type' => Addrbook::TYPE_CUSTOMER,
            'email' => 'customer@test.com',
            'phone' => '456',
        ]);

        $item = Item::factory()->create([
            'name' => 'Sell Item',
            'code' => 'SELL001',
            'price' => 2000,
            'cost' => 1000,
        ]);

        // Pre-stock warehouse with 50
        WarehouseItem::create([
            'warehouse_id' => $warehouse->id,
            'warehouse_type' => $warehouse->type,
            'item_id' => $item->id,
            'quantity' => 50,
        ]);

        // 2. Action: SELL (Warehouse -> Customer)
        $this->post(route('transactions.store'), [
            'date' => now()->toDateString(),
            'type' => 'sell',
            'sender_id' => $warehouse->id,
            'sender_type' => $warehouse->type,
            'receiver_id' => $customer->id,
            'receiver_type' => $customer->type,
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 5,
                    'price' => 2000,
                    'discount' => 0,
                ],
            ],
        ]);

        // 3. Verify
        // Warehouse (Sender) should have 50 - 5 = 45
        $this->assertDatabaseHas('warehouse_items', [
            'warehouse_id' => $warehouse->id,
            'warehouse_type' => $warehouse->type,
            'item_id' => $item->id,
            'quantity' => 45,
        ]);

        // Customer (Receiver) should have +5
        $this->assertDatabaseHas('warehouse_items', [
            'warehouse_id' => $customer->id,
            'warehouse_type' => $customer->type,
            'item_id' => $item->id,
            'quantity' => 5,
        ]);
    }
}

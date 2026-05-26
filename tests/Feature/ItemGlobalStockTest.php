<?php

namespace Tests\Feature;

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ItemGlobalStockTest extends TestCase
{
    use DatabaseTransactions;

    public function test_buy_transaction_increases_global_qty()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $item = Item::factory()->create(['qty' => 0]);
        $supplier = Addrbook::create(['name' => 'Supplier', 'type' => Addrbook::TYPE_SUPPLIER]);
        $warehouse = Location::create(['name' => 'Warehouse', 'slug' => 'wh', 'type' => 'warehouse']);

        $this->post(route('transactions.store'), [
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
                    'price' => 1000,
                ],
            ],
        ]);

        $this->assertEquals(10, $item->fresh()->qty);
    }

    public function test_sell_transaction_decreases_global_qty()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $item = Item::factory()->create(['qty' => 50]);
        $customer = Addrbook::create(['name' => 'Customer', 'type' => Addrbook::TYPE_CUSTOMER]);
        $warehouse = Location::create(['name' => 'Warehouse', 'slug' => 'wh', 'type' => 'warehouse']);

        $this->post(route('transactions.store'), [
            'date' => now()->toDateString(),
            'type' => 'sell',
            'sender_id' => $warehouse->id,
            'sender_type' => get_class($warehouse),
            'receiver_id' => $customer->id,
            'receiver_type' => get_class($customer),
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 5,
                    'price' => 2000,
                ],
            ],
        ]);

        $this->assertEquals(45, $item->fresh()->qty);
    }
}

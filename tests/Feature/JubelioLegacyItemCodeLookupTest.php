<?php

use App\Enums\ItemType;
use App\Jobs\UpdateTransactionSummaries;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;
use Illuminate\Support\Facades\Queue;

/**
 * @return array{warehouse: Addrbook, customer: Addrbook, sync: Jubeliosync}
 */
function seedJubelioOrderParties(int $storeId = 10, int $locationId = 20): array
{
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    $sync = Jubeliosync::create([
        'jubelio_store_id' => $storeId,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => $locationId,
        'jubelio_location_name' => 'Gudang',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    return compact('warehouse', 'customer', 'sync');
}

function seedLinkedJubelioItem(
    Addrbook $warehouse,
    string $code,
    ?int $jubelioItemId = null,
    float $stock = 10,
): Item {
    $item = Item::factory()->create([
        'type' => ItemType::ITEM->value,
        'code' => $code,
        'jubelio_item_id' => $jubelioItemId ?? random_int(10000, 99999),
    ]);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => $warehouse->type,
        'item_id' => $item->id,
        'quantity' => $stock,
    ]);

    return $item;
}

function queueJubelioSellOrder(
    string $jubelioOrderId,
    string $invoice,
    int $storeId,
    int $locationId,
    array $lineItems,
): Jubelioorder {
    mockJubelioSalesOrder($jubelioOrderId, [
        'salesorder_no' => $invoice,
        'store_id' => $storeId,
        'location_id' => $locationId,
        'sub_total' => collect($lineItems)->sum(fn (array $row) => (float) ($row['price'] ?? 0)),
        'real_total' => collect($lineItems)->sum(fn (array $row) => (float) ($row['price'] ?? 0)),
        'items' => $lineItems,
    ]);

    return Jubelioorder::create([
        'jubelio_order_id' => $jubelioOrderId,
        'source' => 1,
        'invoice' => $invoice,
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);
}

it('cron order processing resolves aria item from jubelio item_id via api payload', function () {
    Queue::fake([UpdateTransactionSummaries::class]);

    ['warehouse' => $warehouse, 'sync' => $sync] = seedJubelioOrderParties();
    $item = seedLinkedJubelioItem($warehouse, 'FABRICBAND-03-BABYBLUE-LIGHT');

    $order = queueJubelioSellOrder(
        'jubelio-id-cron',
        'INV-JUBELIO-ID-CRON',
        $sync->jubelio_store_id,
        $sync->jubelio_location_id,
        [
            jubelioPayloadLine($item, 1, 75000),
        ],
    );

    $this->artisan('jubelio:order-jubelio-to-aria')->assertSuccessful();

    $order->refresh();
    $transaction = Transaction::where('invoice', 'INV-JUBELIO-ID-CRON')->first();

    expect($order->status)->toBe(2)
        ->and($order->error_type)->toBe(10)
        ->and($order->payload)->toBeNull()
        ->and($transaction)->not->toBeNull()
        ->and($transaction->details->first()->item_id)->toBe($item->id);

    Queue::assertPushed(UpdateTransactionSummaries::class, fn ($job) => $job->transactionId === $transaction->id);
});

it('manual order processing resolves jubelio item_id from api fetch', function () {
    Queue::fake([UpdateTransactionSummaries::class]);

    $user = User::factory()->create();
    ['warehouse' => $warehouse, 'sync' => $sync] = seedJubelioOrderParties(12, 22);
    $item = seedLinkedJubelioItem($warehouse, 'AJJ-PL25129-06-XL');

    $order = queueJubelioSellOrder(
        'jubelio-id-manual',
        'INV-JUBELIO-ID-MANUAL',
        $sync->jubelio_store_id,
        $sync->jubelio_location_id,
        [
            jubelioPayloadLine($item, 2, 150000),
        ],
    );

    $this->actingAs($user)
        ->post(route('jubelio.process', $order))
        ->assertRedirect()
        ->assertSessionHas('success');

    $order->refresh();
    $transaction = Transaction::where('invoice', 'INV-JUBELIO-ID-MANUAL')->first();

    expect($order->status)->toBe(2)
        ->and($order->execute_by)->toBe($user->id)
        ->and($order->payload)->toBeNull()
        ->and($transaction->details->first()->item_id)->toBe($item->id)
        ->and((float) $transaction->details->first()->quantity)->toBe(2.0);

    Queue::assertPushed(UpdateTransactionSummaries::class, fn ($job) => $job->transactionId === $transaction->id);
});

it('does not resolve inbound lines by item_code when jubelio item_id is unlinked', function () {
    ['warehouse' => $warehouse, 'sync' => $sync] = seedJubelioOrderParties(13, 23);
    $item = seedLinkedJubelioItem($warehouse, 'MATCHING-CODE-ONLY');

    $order = queueJubelioSellOrder(
        'code-only-mismatch',
        'INV-CODE-ONLY-MISMATCH',
        $sync->jubelio_store_id,
        $sync->jubelio_location_id,
        [
            [
                'item_id' => 888777666,
                'item_code' => $item->code,
                'qty' => 1,
                'price' => 1000,
            ],
        ],
    );

    $result = app(\App\Actions\Jubelio\ProcessJubelioOrder::class)->execute($order);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('888777666')
        ->and(Transaction::where('invoice', 'INV-CODE-ONLY-MISMATCH')->exists())->toBeFalse();
});

it('jubelio order processing fails when jubelio item_id is not linked in aria', function () {
    ['warehouse' => $warehouse, 'sync' => $sync] = seedJubelioOrderParties(14, 24);
    seedLinkedJubelioItem($warehouse, 'AJJ-PL25129-06-XL');

    $order = queueJubelioSellOrder(
        'unknown-jubelio-id',
        'INV-UNKNOWN-JUBELIO-ID',
        $sync->jubelio_store_id,
        $sync->jubelio_location_id,
        [
            [
                'item_id' => 999888777,
                'item_code' => 'TOTALLY-UNKNOWN-SKU',
                'qty' => 1,
                'price' => 1000,
            ],
        ],
    );

    $result = app(\App\Actions\Jubelio\ProcessJubelioOrder::class)->execute($order);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('999888777')
        ->and(Transaction::where('invoice', 'INV-UNKNOWN-JUBELIO-ID')->exists())->toBeFalse();
});

it('jubelio return processing resolves item from jubelio item_id in api payload', function () {
    ['warehouse' => $warehouse, 'customer' => $customer, 'sync' => $sync] = seedJubelioOrderParties(15, 25);
    $item = seedLinkedJubelioItem($warehouse, 'FABRICBAND-03-BABYBLUE-LIGHT');

    Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'INV-SELL-RETURN-JUBELIO-ID',
        'sender_id' => $warehouse->id,
        'sender_type' => $warehouse->type,
        'receiver_id' => $customer->id,
        'receiver_type' => $customer->type,
    ]);

    mockJubelioSalesReturn('return-jubelio-id-1', [
        'return_no' => 'RET-JUBELIO-ID-1',
        'return_id' => 'return-jubelio-id-1',
        'salesorder_no' => 'INV-SELL-RETURN-JUBELIO-ID',
        'store_id' => $sync->jubelio_store_id,
        'location_id' => $sync->jubelio_location_id,
        'sub_total' => 75000,
        'real_total' => 75000,
        'items' => [
            jubelioPayloadLine($item, 1, 75000, ['qty_in_base' => 1]),
        ],
    ]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'return-jubelio-id-1',
        'source' => 1,
        'invoice' => 'RET-JUBELIO-ID-1',
        'type' => 'RETURN',
        'order_status' => 'RETURN',
        'run_count' => 0,
        'status' => 0,
    ]);

    app(\App\Actions\Jubelio\ProcessJubelioOrder::class)->execute($order);

    $returnTrx = Transaction::where('type', Transaction::TYPE_RETURN)->where('invoice', 'RET-JUBELIO-ID-1')->first();

    expect($returnTrx)->not->toBeNull()
        ->and($returnTrx->details->first()->item_id)->toBe($item->id);
});

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

function seedConvertedLegacyItem(
    Addrbook $warehouse,
    string $canonicalCode,
    string $legacyCode,
    float $stock = 10,
): Item {
    $item = Item::factory()->create([
        'type' => ItemType::ITEM->value,
        'code' => $canonicalCode,
        'legacy_code' => $legacyCode,
        'jubelio_item_id' => random_int(10000, 99999),
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
        'transaction_date' => '2026-05-10',
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

it('cron order processing resolves converted legacy fabricband sku from jubelio payload', function () {
    Queue::fake([UpdateTransactionSummaries::class]);

    ['warehouse' => $warehouse, 'sync' => $sync] = seedJubelioOrderParties();
    $item = seedConvertedLegacyItem(
        $warehouse,
        'FABRICBAND-03-BABYBLUE-LIGHT',
        'FABRICBAND-03-LIGHT-BABYBLUE',
    );

    $order = queueJubelioSellOrder(
        'legacy-fabricband-cron',
        'INV-LEGACY-FABRICBAND',
        $sync->jubelio_store_id,
        $sync->jubelio_location_id,
        [
            ['item_code' => 'FABRICBAND-03-LIGHT-BABYBLUE', 'qty' => 1, 'price' => 75000],
        ],
    );

    $this->artisan('jubelio:order-jubelio-to-aria')->assertSuccessful();

    $order->refresh();
    $transaction = Transaction::where('invoice', 'INV-LEGACY-FABRICBAND')->first();

    expect($order->status)->toBe(2)
        ->and($order->error_type)->toBe(10)
        ->and($transaction)->not->toBeNull()
        ->and($transaction->details->first()->item_id)->toBe($item->id);

    Queue::assertPushed(UpdateTransactionSummaries::class, fn ($job) => $job->transactionId === $transaction->id);
});

it('cron order processing resolves converted legacy jacket sku from jubelio payload', function () {
    Queue::fake([UpdateTransactionSummaries::class]);

    ['warehouse' => $warehouse, 'sync' => $sync] = seedJubelioOrderParties(11, 21);
    $item = seedConvertedLegacyItem(
        $warehouse,
        'AJJ-PL25129-06-XL',
        'AJJPL2512906XL',
    );

    $order = queueJubelioSellOrder(
        'legacy-jacket-cron',
        'INV-LEGACY-JACKET',
        $sync->jubelio_store_id,
        $sync->jubelio_location_id,
        [
            ['item_code' => 'AJJPL2512906XL', 'qty' => 1, 'price' => 350000],
        ],
    );

    $this->artisan('jubelio:order-jubelio-to-aria')->assertSuccessful();

    $order->refresh();
    $transaction = Transaction::where('invoice', 'INV-LEGACY-JACKET')->first();

    expect($order->status)->toBe(2)
        ->and($transaction->details->first()->item_id)->toBe($item->id);

    Queue::assertPushed(UpdateTransactionSummaries::class);
});

it('manual order processing resolves legacy sku and dispatches summary job', function () {
    Queue::fake([UpdateTransactionSummaries::class]);

    $user = User::factory()->create();
    ['warehouse' => $warehouse, 'sync' => $sync] = seedJubelioOrderParties(12, 22);
    $item = seedConvertedLegacyItem(
        $warehouse,
        'FABRICBAND-03-BABYBLUE-LIGHT',
        'FABRICBAND-03-LIGHT-BABYBLUE',
    );

    $order = queueJubelioSellOrder(
        'legacy-fabricband-manual',
        'INV-LEGACY-MANUAL',
        $sync->jubelio_store_id,
        $sync->jubelio_location_id,
        [
            ['item_code' => 'FABRICBAND-03-LIGHT-BABYBLUE', 'qty' => 2, 'price' => 150000],
        ],
    );

    $this->actingAs($user)
        ->post(route('jubelio.process', $order))
        ->assertRedirect()
        ->assertSessionHas('success');

    $order->refresh();
    $transaction = Transaction::where('invoice', 'INV-LEGACY-MANUAL')->first();

    expect($order->status)->toBe(2)
        ->and($order->execute_by)->toBe($user->id)
        ->and($transaction->details->first()->item_id)->toBe($item->id)
        ->and((float) $transaction->details->first()->quantity)->toBe(2.0);

    Queue::assertPushed(UpdateTransactionSummaries::class, fn ($job) => $job->transactionId === $transaction->id);
});

it('jubelio order processing also accepts canonical sku after legacy conversion', function () {
    ['warehouse' => $warehouse, 'sync' => $sync] = seedJubelioOrderParties(13, 23);
    $item = seedConvertedLegacyItem(
        $warehouse,
        'AJJ-PL25129-06-XL',
        'AJJPL2512906XL',
    );

    $order = queueJubelioSellOrder(
        'canonical-jacket',
        'INV-CANONICAL-JACKET',
        $sync->jubelio_store_id,
        $sync->jubelio_location_id,
        [
            ['item_code' => 'AJJ-PL25129-06-XL', 'qty' => 1, 'price' => 350000],
        ],
    );

    $this->artisan('jubelio:order-jubelio-to-aria')->assertSuccessful();

    $order->refresh();
    $transaction = Transaction::where('invoice', 'INV-CANONICAL-JACKET')->first();

    expect($order->status)->toBe(2)
        ->and($transaction->details->first()->item_id)->toBe($item->id);
});

it('jubelio order processing fails when sku matches neither code nor legacy_code', function () {
    ['warehouse' => $warehouse, 'sync' => $sync] = seedJubelioOrderParties(14, 24);
    seedConvertedLegacyItem(
        $warehouse,
        'AJJ-PL25129-06-XL',
        'AJJPL2512906XL',
    );

    $order = queueJubelioSellOrder(
        'unknown-sku',
        'INV-UNKNOWN-SKU',
        $sync->jubelio_store_id,
        $sync->jubelio_location_id,
        [
            ['item_code' => 'TOTALLY-UNKNOWN-SKU', 'qty' => 1, 'price' => 1000],
        ],
    );

    $result = app(\App\Actions\Jubelio\ProcessJubelioOrder::class)->execute($order);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('TOTALLY-UNKNOWN-SKU')
        ->and(Transaction::where('invoice', 'INV-UNKNOWN-SKU')->exists())->toBeFalse();
});

it('jubelio return processing resolves legacy sku from payload', function () {
    ['warehouse' => $warehouse, 'customer' => $customer, 'sync' => $sync] = seedJubelioOrderParties(15, 25);
    $item = seedConvertedLegacyItem(
        $warehouse,
        'FABRICBAND-03-BABYBLUE-LIGHT',
        'FABRICBAND-03-LIGHT-BABYBLUE',
    );

    Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'INV-SELL-RETURN-LEGACY',
        'sender_id' => $warehouse->id,
        'sender_type' => $warehouse->type,
        'receiver_id' => $customer->id,
        'receiver_type' => $customer->type,
    ]);

    mockJubelioSalesReturn('return-legacy-1', [
        'return_no' => 'RET-LEGACY-1',
        'salesorder_no' => 'INV-SELL-RETURN-LEGACY',
        'store_id' => $sync->jubelio_store_id,
        'location_id' => $sync->jubelio_location_id,
        'sub_total' => 75000,
        'real_total' => 75000,
        'items' => [
            ['item_code' => 'FABRICBAND-03-LIGHT-BABYBLUE', 'qty_in_base' => 1, 'price' => 75000],
        ],
    ]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'return-legacy-1',
        'source' => 1,
        'invoice' => 'RET-LEGACY-1',
        'type' => 'RETURN',
        'order_status' => 'RETURN',
        'run_count' => 0,
        'status' => 0,
    ]);

    app(\App\Actions\Jubelio\ProcessJubelioOrder::class)->execute($order);

    $returnTrx = Transaction::where('type', Transaction::TYPE_RETURN)->where('invoice', 'RET-LEGACY-1')->first();

    expect($returnTrx)->not->toBeNull()
        ->and($returnTrx->details->first()->item_id)->toBe($item->id);
});

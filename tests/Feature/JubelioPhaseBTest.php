<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubelioorder;
use App\Models\Jubelioreturn;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\JubelioService;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;

it('accepts jubelio return webhook and stores return order', function () {
    config(['services.jubelio.webhook_secret' => 'test-secret']);

    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'INV-SELL-001',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchSalesReturn')
            ->once()
            ->with('ret-100')
            ->andReturn([
                'return_id' => 'ret-100',
                'return_no' => 'RET-100',
                'salesorder_no' => 'INV-SELL-001',
                'store_id' => 1,
                'location_id' => 2,
                'sub_total' => 100000,
                'real_total' => 100000,
                'items' => [],
            ]);
    });

    $body = json_encode(['return_id' => 'ret-100']);
    $sign = hash_hmac('sha256', trim($body).'test-secret', 'test-secret', false);

    $this->call(
        'POST',
        route('jubelio.webhook.return'),
        [],
        [],
        [],
        ['HTTP_SIGN' => $sign, 'CONTENT_TYPE' => 'application/json'],
        $body,
    )->assertSuccessful()
        ->assertJson(['status' => 'ok']);

    expect(Jubelioorder::where('invoice', 'RET-100')->where('type', 'RETURN')->exists())->toBeTrue();

    $stored = Jubelioorder::where('invoice', 'RET-100')->first();
    expect($stored->payload)->toBeNull();
});

it('rejects duplicate jubelio return webhook payloads', function () {
    config(['services.jubelio.webhook_secret' => 'test-secret']);

    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'INV-SELL-002',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    Jubelioorder::create([
        'jubelio_order_id' => 'ret-200',
        'source' => 1,
        'invoice' => 'RET-200',
        'type' => 'RETURN',
        'order_status' => 'RETURN',
        'run_count' => 0,
        'status' => 0,
    ]);

    $this->mock(JubelioService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchSalesReturn')
            ->once()
            ->andReturn([
                'return_id' => 'ret-200',
                'return_no' => 'RET-200',
                'salesorder_no' => 'INV-SELL-002',
            ]);
    });

    $body = json_encode(['return_id' => 'ret-200']);
    $sign = hash_hmac('sha256', trim($body).'test-secret', 'test-secret', false);

    $this->call(
        'POST',
        route('jubelio.webhook.return'),
        [],
        [],
        [],
        ['HTTP_SIGN' => $sign, 'CONTENT_TYPE' => 'application/json'],
        $body,
    )->assertSuccessful()
        ->assertJsonPath('message', 'Data already exists');

    expect(Jubelioorder::where('invoice', 'RET-200')->count())->toBe(1);
});

it('accepts jubelio cancel webhook and stores cancellation record', function () {
    config(['services.jubelio.webhook_secret' => 'test-secret']);

    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'INV-CANCEL-WH',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'jubelio_return' => 0,
    ]);

    $body = json_encode([
        'status' => 'CANCELED',
        'salesorder_id' => 'ord-cancel-1',
        'salesorder_no' => 'INV-CANCEL-WH',
        'payment_method' => 'COD',
        'cancel_reason_detail' => 'Buyer request',
        'location_name' => 'Gudang Pusat',
        'source_name' => 'Tokopedia',
    ]);
    $sign = hash_hmac('sha256', trim($body).'test-secret', 'test-secret', false);

    $this->call(
        'POST',
        route('jubelio.webhook.order'),
        [],
        [],
        [],
        ['HTTP_SIGN' => $sign, 'CONTENT_TYPE' => 'application/json'],
        $body,
    )->assertSuccessful()
        ->assertJson(['status' => 'ok']);

    expect(Jubelioreturn::where('order_id', 'ord-cancel-1')->where('transaction_id', $sell->id)->exists())->toBeTrue();
});

it('rejects duplicate jubelio cancel webhook payloads', function () {
    config(['services.jubelio.webhook_secret' => 'test-secret']);

    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);

    Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'INV-CANCEL-DUP',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);

    Jubelioreturn::create([
        'order_id' => 'ord-cancel-dup',
        'transaction_id' => '1',
        'invoice' => 'INV-CANCEL-DUP',
        'status' => 0,
        'confirmed_by' => 0,
    ]);

    $body = json_encode([
        'status' => 'CANCELED',
        'salesorder_id' => 'ord-cancel-dup',
        'salesorder_no' => 'INV-CANCEL-DUP',
    ]);
    $sign = hash_hmac('sha256', trim($body).'test-secret', 'test-secret', false);

    $this->call(
        'POST',
        route('jubelio.webhook.order'),
        [],
        [],
        [],
        ['HTTP_SIGN' => $sign, 'CONTENT_TYPE' => 'application/json'],
        $body,
    )->assertSuccessful()
        ->assertJsonPath('message', 'Return exists');

    expect(Jubelioreturn::where('order_id', 'ord-cancel-dup')->count())->toBe(1);
});

it('shows jubelio sync push buttons on transaction detail page', function () {
    User::factory()->create();
    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'transactions-show']);
    $user->givePermissionTo('transactions-show');
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'WH Sync Test']);
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create(['jubelio_item_id' => 999]);

    Jubeliosync::create([
        'jubelio_store_id' => 1,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => 2,
        'jubelio_location_name' => 'Jubelio WH',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'total_items' => 1,
    ]);

    $transaction->details()->create([
        'date' => $transaction->date,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'price' => 10000,
        'discount' => 0,
        'total' => 10000,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.show', $transaction))
        ->assertSuccessful()
        ->assertSee('Sinkron Jubelio')
        ->assertSee('Push to Jubelio');
});

it('shows jubelio sync for transactions-show without jubelio-sync permission', function () {
    User::factory()->create();
    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'transactions-show']);
    Permission::firstOrCreate(['name' => 'jubelio-sync']);
    $user->givePermissionTo('transactions-show');

    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'WH Legacy Sync']);
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create(['jubelio_item_id' => 888]);

    Jubeliosync::create([
        'jubelio_store_id' => 1,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => 2,
        'jubelio_location_name' => 'Jubelio WH',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'total_items' => 1,
    ]);

    $transaction->details()->create([
        'date' => $transaction->date,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'price' => 10000,
        'discount' => 0,
        'total' => 10000,
    ]);

    expect($user->can('jubelio-sync'))->toBeFalse();
    expect(Transaction::userCanJubelioTransactionSync($user))->toBeTrue();

    $this->actingAs($user)
        ->get(route('transactions.show', $transaction))
        ->assertSuccessful()
        ->assertSee('Sinkron Jubelio', false)
        ->assertSee('Push to Jubelio', false);
});

it('lists pending jubelio cancellations by default', function () {
    $user = User::factory()->create();

    Jubelioreturn::create([
        'order_id' => 'ord-1',
        'transaction_id' => '1',
        'invoice' => 'INV-CANCEL-PENDING',
        'status' => 0,
        'confirmed_by' => 0,
    ]);

    Jubelioreturn::create([
        'order_id' => 'ord-2',
        'transaction_id' => '2',
        'invoice' => 'INV-CANCEL-SOLVED',
        'status' => 1,
        'confirmed_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('jubelio.returns.index'))
        ->assertSuccessful()
        ->assertSee('INV-CANCEL-PENDING')
        ->assertDontSee('INV-CANCEL-SOLVED');
});

it('can process a jubelio cancellation into a return transaction', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create(['code' => 'SKU-CANCEL-1']);

    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'INV-CANCEL-PROC',
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]);

    $sell->details()->create([
        'date' => $sell->date,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'item_id' => $item->id,
        'quantity' => 2,
        'price' => 50000,
        'discount' => 0,
        'total' => 100000,
    ]);

    $return = Jubelioreturn::create([
        'order_id' => 'ord-cancel',
        'transaction_id' => $sell->id,
        'invoice' => 'INV-CANCEL-PROC',
        'status' => 0,
        'confirmed_by' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('jubelio.returns.process', $return), [
            'return_item' => [$item->id],
            'adjustment' => 0,
        ])
        ->assertRedirect(route('transactions.index'))
        ->assertSessionHas('success');

    $return->refresh();
    $sell->refresh();

    expect($return->status)->toBe(1)
        ->and((int) $sell->jubelio_return)->toBe(2)
        ->and(Transaction::where('type', Transaction::TYPE_RETURN)->where('invoice', 'INV-CANCEL-PROC')->exists())->toBeTrue();
});

it('refetches jubelio order payload from API when processing polled order', function () {
    $user = User::factory()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
    $item = Item::factory()->create(['code' => 'SKU-REFETCH-1']);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => $warehouse->type,
        'item_id' => $item->id,
        'quantity' => 10,
    ]);

    Jubeliosync::create([
        'jubelio_store_id' => 11,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => 22,
        'jubelio_location_name' => 'Gudang',
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'bin_id' => 0,
    ]);

    mockJubelioSalesOrder('api-order-1', [
        'salesorder_no' => 'INV-REFETCH-1',
        'store_id' => 11,
        'location_id' => 22,
        'sub_total' => 100000,
        'real_total' => 100000,
        'transaction_date' => '2026-05-10',
        'items' => [
            ['item_code' => 'SKU-REFETCH-1', 'qty' => 1, 'price' => 100000],
        ],
    ]);

    $order = Jubelioorder::create([
        'jubelio_order_id' => 'api-order-1',
        'source' => 2,
        'invoice' => 'INV-REFETCH-1',
        'type' => 'SELL',
        'order_status' => 'SHIPPED',
        'run_count' => 0,
        'status' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('jubelio.process', $order))
        ->assertRedirect()
        ->assertSessionHas('success');

    $order->refresh();
    expect($order->status)->toBe(2)
        ->and($order->payload)->toBeNull()
        ->and(Transaction::where('invoice', 'INV-REFETCH-1')->exists())->toBeTrue();
});

it('can confirm and clear jubelio sync warnings', function () {
    User::factory()->create();
    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'transactions-show']);
    $user->givePermissionTo('transactions-show');

    $transaction = Transaction::factory()->create([
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
        'submit_a_count' => 1,
        'a_submit_by' => null,
    ]);

    $this->actingAs($user)
        ->post(route('jubelio.transaction.sync-confirm', $transaction), [
            'side' => 1,
            'reference_id' => 'JUB-REF-1',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $transaction->refresh();
    expect($transaction->a_submit_by)->toBe($user->id)
        ->and($transaction->a_reference_id)->toBe('JUB-REF-1');

    $transaction->update([
        'a_submit_by' => null,
        'submit_a_count' => 1,
    ]);

    $this->actingAs($user)
        ->post(route('jubelio.transaction.sync-clear', $transaction), ['side' => 1])
        ->assertRedirect()
        ->assertSessionHas('success');

    $transaction->refresh();
    expect($transaction->submit_a_count)->toBe(0)
        ->and($transaction->hasSyncWarningA())->toBeFalse();
});

it('detects sync warning on transaction model', function () {
    $transaction = Transaction::factory()->create([
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
        'submit_a_count' => 1,
        'a_submit_by' => null,
        'submit_b_count' => 0,
        'b_submit_by' => null,
    ]);

    expect($transaction->hasSyncWarningA())->toBeTrue()
        ->and($transaction->hasSyncWarningB())->toBeFalse();
});

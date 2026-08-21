<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Location;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Services\ExportSellQueryService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'report-export-sell']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-export-sell');
});

it('renders export sell page for authorized users', function () {
    $this->actingAs($this->user)
        ->get(route('transactions.export-sell'))
        ->assertOk()
        ->assertSee('Export Sell', false)
        ->assertSee('value="100"', false);
});

it('forbids export sell without permission', function () {
    $other = User::factory()->create();

    $this->actingAs($other)
        ->get(route('transactions.export-sell'))
        ->assertForbidden();
});

it('lists sell transaction detail lines with links', function () {
    $item = Item::factory()->create(['code' => 'SKU-SELL-1']);
    $sender = Addrbook::factory()->warehouse()->create(['name' => 'WH Export']);
    $receiver = Addrbook::factory()->customer()->create(['name' => 'Customer Export']);

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SELL-EXP-001',
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'date' => now()->toDateString(),
        'quantity' => 3,
        'discount' => 10,
        'total' => 270_000,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.export-sell', ['invoice' => 'SELL-EXP-001']))
        ->assertOk()
        ->assertSee('SELL-EXP-001', false)
        ->assertSee('SKU-SELL-1', false)
        ->assertSee('WH Export', false)
        ->assertSee('Customer Export', false)
        ->assertSee(route('transactions.show', $transaction->id), false)
        ->assertSee(route('items.show', $item->id), false)
        ->assertSee(route('addrbook.type.show', ['warehouse', $sender->id]), false)
        ->assertSee(route('addrbook.type.show', ['customer', $receiver->id]), false);
});

it('filters export sell lines by invoice', function () {
    $visibleTx = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SELL-FILTER-VISIBLE',
    ]);
    $hiddenTx = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SELL-FILTER-HIDDEN',
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $visibleTx->id,
        'transaction_type' => Transaction::TYPE_SELL,
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $hiddenTx->id,
        'transaction_type' => Transaction::TYPE_SELL,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.export-sell', ['invoice' => 'FILTER-VISIBLE']))
        ->assertOk()
        ->assertSee('SELL-FILTER-VISIBLE', false)
        ->assertDontSee('SELL-FILTER-HIDDEN', false);
});

it('exports filtered sell lines to excel', function () {
    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SELL-XLS-001',
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'transaction_type' => Transaction::TYPE_SELL,
    ]);

    $response = $this->actingAs($this->user)->get(route('transactions.export-sell.build', [
        'invoice' => 'SELL-XLS-001',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('filters export sell lines by user location', function () {
    $locationA = Location::create(['name' => 'Export Loc A']);
    $locationB = Location::create(['name' => 'Export Loc B']);

    $addrbookA = Addrbook::factory()->warehouse()->create(['name' => 'WH A']);
    $addrbookB = Addrbook::factory()->warehouse()->create(['name' => 'WH B']);
    $customer = Addrbook::factory()->customer()->create(['name' => 'Cust']);

    $addrbookA->locations()->attach($locationA->id);
    $addrbookB->locations()->attach($locationB->id);

    $visibleTx = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SELL-LOC-VISIBLE',
        'sender_id' => $addrbookA->id,
        'receiver_id' => $customer->id,
    ]);
    $hiddenTx = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SELL-LOC-HIDDEN',
        'sender_id' => $addrbookB->id,
        'receiver_id' => $customer->id,
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $visibleTx->id,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $addrbookA->id,
        'receiver_id' => $customer->id,
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $hiddenTx->id,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $addrbookB->id,
        'receiver_id' => $customer->id,
    ]);

    $scopedUser = User::factory()->create(['location_id' => $locationA->id]);
    $scopedUser->givePermissionTo('report-export-sell');

    $this->actingAs($scopedUser)
        ->get(route('transactions.export-sell'))
        ->assertOk()
        ->assertSee('SELL-LOC-VISIBLE', false)
        ->assertDontSee('SELL-LOC-HIDDEN', false);
});

it('respects per_page options in export sell query service', function () {
    $service = app(ExportSellQueryService::class);

    expect($service->resolvePerPage(request()->merge(['per_page' => 200])))->toBe(200)
        ->and($service->resolvePerPage(request()->merge(['per_page' => 999])))->toBe(100);
});

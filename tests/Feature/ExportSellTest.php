<?php

use App\Http\Controllers\ExportSellController;
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

it('redirects the retired item-sales report to export sell', function () {
    $this->actingAs($this->user)
        ->get('/reports/item-sales')
        ->assertRedirect('/transactions/export-sell');
});

it('renders export sell page for authorized users', function () {
    $this->actingAs($this->user)
        ->get(route('transactions.export-sell'))
        ->assertOk()
        ->assertSee('Export Sell', false)
        ->assertSee('value="100"', false)
        ->assertSee('data-testid="toggle-export-sell-filters"', false)
        ->assertSee('data-testid="export-sell-sender-combobox"', false)
        ->assertSee('data-testid="export-sell-receiver-combobox"', false)
        ->assertSee('data-testid="export-sell-item-combobox"', false)
        ->assertSee('data-testid="toggle-export-sell-tx-adjustment"', false)
        ->assertSee('data-testid="toggle-export-sell-tx-discount"', false)
        ->assertSee('data-testid="toggle-export-sell-tx-total"', false)
        ->assertSee('data-testid="toggle-export-sell-tx-description"', false)
        ->assertSee('data-testid="export-sell-excel-link"', false)
        ->assertSee('data-testid="copy-export-sell-table"', false)
        ->assertSee('x-ref="exportSellTable"', false)
        ->assertSee('showFilters: true', false);
});

it('returns addrbook matches for export sell party lookup', function () {
    $sender = Addrbook::factory()->warehouse()->create(['name' => 'Lookup Warehouse Alpha']);
    Addrbook::factory()->warehouse()->create(['name' => 'Lookup Warehouse Beta']);

    $url = ExportSellController::exportSellPartyLookups()['sender_route'].'&search=Alpha';

    $this->actingAs($this->user)
        ->getJson($url)
        ->assertOk()
        ->assertJsonFragment(['id' => $sender->id, 'name' => 'Lookup Warehouse Alpha'])
        ->assertJsonMissing(['name' => 'Lookup Warehouse Beta']);
});

it('excludes non-inventory parties from export sell party lookup', function () {
    Addrbook::create(['name' => 'Ledger Account Party', 'type' => Addrbook::TYPE_ACCOUNT]);
    Addrbook::create(['name' => 'Virtual Account Party', 'type' => Addrbook::TYPE_V_ACCOUNT]);
    Addrbook::factory()->supplier()->create(['name' => 'Supplier Party Match']);
    Addrbook::create(['name' => 'Bank Party Match', 'type' => Addrbook::TYPE_BANK]);
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Warehouse Party Match']);

    $url = ExportSellController::exportSellPartyLookups()['sender_route'].'&search=Party';

    $names = collect($this->actingAs($this->user)->getJson($url)->assertOk()->json())->pluck('name');

    expect($names)->toContain('Warehouse Party Match')
        ->not->toContain('Ledger Account Party')
        ->not->toContain('Virtual Account Party')
        ->not->toContain('Supplier Party Match')
        ->not->toContain('Bank Party Match');
});

it('party type ids are customer reseller warehouse and vwarehouse only', function () {
    $service = app(ExportSellQueryService::class);

    expect($service->partyTypeIds())->toEqual([
        Addrbook::TYPE_CUSTOMER,
        Addrbook::TYPE_RESELLER,
        Addrbook::TYPE_WAREHOUSE,
        Addrbook::TYPE_V_WAREHOUSE,
    ]);
});

it('filters export sell lines by selected sender id', function () {
    $senderA = Addrbook::factory()->warehouse()->create(['name' => 'Sender Filter A']);
    $senderB = Addrbook::factory()->warehouse()->create(['name' => 'Sender Filter B']);
    $receiver = Addrbook::factory()->customer()->create();

    $visibleTx = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SENDER-FILTER-VISIBLE',
        'sender_id' => $senderA->id,
        'receiver_id' => $receiver->id,
    ]);
    $hiddenTx = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SENDER-FILTER-HIDDEN',
        'sender_id' => $senderB->id,
        'receiver_id' => $receiver->id,
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $visibleTx->id,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $senderA->id,
        'receiver_id' => $receiver->id,
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $hiddenTx->id,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $senderB->id,
        'receiver_id' => $receiver->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.export-sell', ['sender' => $senderA->id]))
        ->assertOk()
        ->assertSee('SENDER-FILTER-VISIBLE', false)
        ->assertDontSee('SENDER-FILTER-HIDDEN', false)
        ->assertSee('Sender Filter A', false);
});

it('filters export sell lines by parent transaction sender when detail sender is null', function () {
    $sender = Addrbook::factory()->warehouse()->create(['name' => 'Parent Sender WH']);
    $receiver = Addrbook::factory()->customer()->create();

    $visibleTx = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'PARENT-SENDER-VISIBLE',
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);
    $hiddenTx = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'PARENT-SENDER-HIDDEN',
        'sender_id' => Addrbook::factory()->warehouse()->create()->id,
        'receiver_id' => $receiver->id,
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $visibleTx->id,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => null,
        'receiver_id' => null,
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $hiddenTx->id,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => null,
        'receiver_id' => null,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.export-sell', ['sender' => $sender->id]))
        ->assertOk()
        ->assertSee('PARENT-SENDER-VISIBLE', false)
        ->assertDontSee('PARENT-SENDER-HIDDEN', false);
});

it('filters export sell lines by sender id greater than one', function () {
    Addrbook::factory()->warehouse()->create();
    $sender = Addrbook::factory()->warehouse()->create(['name' => 'High Id Sender']);
    expect($sender->id)->toBeGreaterThan(1);

    $receiver = Addrbook::factory()->customer()->create();
    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'HIGH-ID-SENDER-FILTER',
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => 0,
        'receiver_id' => 0,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.export-sell', ['sender' => $sender->id]))
        ->assertOk()
        ->assertSee('HIGH-ID-SENDER-FILTER', false);
});

it('returns item matches for export sell item lookup', function () {
    $item = Item::factory()->create(['code' => 'EXPORT-LOOKUP-SKU', 'name' => 'Lookup Export Item']);
    Item::factory()->create(['code' => 'OTHER-SKU', 'name' => 'Other Item']);

    $this->actingAs($this->user)
        ->getJson(route('items.index', ['json' => 1, 'search' => 'EXPORT-LOOKUP']))
        ->assertOk()
        ->assertJsonFragment(['id' => $item->id, 'code' => 'EXPORT-LOOKUP-SKU'])
        ->assertJsonMissing(['code' => 'OTHER-SKU']);
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
        ->assertSee(route('addrbook.type.show', ['customer', $receiver->id]), false)
        ->assertSee('data-testid="copy-export-sell-table"', false)
        ->assertSee('data-copy-col="invoice"', false)
        ->assertSee('data-copy-col="item_code"', false)
        ->assertSee('data-copy-col="qty"', false)
        ->assertSee('data-copy-col="sender"', false)
        ->assertSee('data-copy-col="receiver"', false)
        ->assertSee('copyRowsTable()', false);
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

it('exports optional transaction header columns when requested', function () {
    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SELL-XLS-TX-COLS',
        'adjustment' => 12_500,
        'discount' => 5,
        'total' => -1_000_000,
        'description' => 'Header note for export',
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'transaction_type' => Transaction::TYPE_SELL,
    ]);

    $response = $this->actingAs($this->user)->get(route('transactions.export-sell.build', [
        'invoice' => 'SELL-XLS-TX-COLS',
        'show_tx_adjustment' => 1,
        'show_tx_discount' => 1,
        'show_tx_total' => 1,
        'show_tx_description' => 1,
    ]));

    $response->assertOk();

    $tmp = tempnam(sys_get_temp_dir(), 'export-sell-');
    file_put_contents($tmp, $response->streamedContent());

    $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet();
    unlink($tmp);

    expect($sheet->getCell('I1')->getValue())->toBe('Adjustment')
        ->and($sheet->getCell('J1')->getValue())->toBe('Inv. Discount')
        ->and($sheet->getCell('K1')->getValue())->toBe('Tx Total')
        ->and($sheet->getCell('L1')->getValue())->toBe('Description')
        ->and((float) $sheet->getCell('I2')->getValue())->toBe(12500.0)
        ->and((float) $sheet->getCell('J2')->getValue())->toBe(5.0)
        ->and((float) $sheet->getCell('K2')->getValue())->toBe(-1000000.0)
        ->and($sheet->getCell('L2')->getValue())->toBe('Header note for export');
});

it('omits optional transaction header columns from excel by default', function () {
    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SELL-XLS-DEFAULT-COLS',
        'description' => 'Should stay hidden',
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'transaction_type' => Transaction::TYPE_SELL,
    ]);

    $response = $this->actingAs($this->user)->get(route('transactions.export-sell.build', [
        'invoice' => 'SELL-XLS-DEFAULT-COLS',
    ]));

    $response->assertOk();

    $tmp = tempnam(sys_get_temp_dir(), 'export-sell-');
    file_put_contents($tmp, $response->streamedContent());

    $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet();
    unlink($tmp);

    expect($sheet->getCell('I1')->getValue())->toBe('Sender')
        ->and($sheet->getCell('J1')->getValue())->toBe('Receiver');
});

it('orders export sell lines by date and transaction id ascending', function () {
    $olderTx = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SELL-SORT-OLDER',
        'date' => '2026-01-01',
    ]);
    $newerTx = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SELL-SORT-NEWER',
        'date' => '2026-02-01',
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $newerTx->id,
        'transaction_type' => Transaction::TYPE_SELL,
        'date' => '2026-02-01',
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $olderTx->id,
        'transaction_type' => Transaction::TYPE_SELL,
        'date' => '2026-01-01',
    ]);

    $response = $this->actingAs($this->user)->get(route('transactions.export-sell.build', [
        'type' => Transaction::TYPE_SELL,
        'from' => '2026-01-01',
        'to' => '2026-02-28',
    ]));

    $response->assertOk();

    $tmp = tempnam(sys_get_temp_dir(), 'export-sell-');
    file_put_contents($tmp, $response->streamedContent());

    $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet();
    unlink($tmp);

    expect($sheet->getCell('C2')->getValue())->toBe('SELL-SORT-OLDER')
        ->and($sheet->getCell('C3')->getValue())->toBe('SELL-SORT-NEWER');
});

it('blanks repeated transaction header columns on subsequent detail lines in excel', function () {
    $itemA = Item::factory()->create(['code' => 'GROUP-ITEM-A']);
    $itemB = Item::factory()->create(['code' => 'GROUP-ITEM-B']);

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SELL-GROUPED-COLS',
        'date' => '2026-03-10',
        'adjustment' => 1_000,
        'discount' => 7,
        'total' => -500_000,
        'description' => 'Grouped header note',
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'transaction_type' => Transaction::TYPE_SELL,
        'item_id' => $itemA->id,
        'date' => '2026-03-10',
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'transaction_type' => Transaction::TYPE_SELL,
        'item_id' => $itemB->id,
        'date' => '2026-03-10',
    ]);

    $response = $this->actingAs($this->user)->get(route('transactions.export-sell.build', [
        'invoice' => 'SELL-GROUPED-COLS',
        'show_tx_adjustment' => 1,
        'show_tx_discount' => 1,
        'show_tx_total' => 1,
        'show_tx_description' => 1,
    ]));

    $response->assertOk();

    $tmp = tempnam(sys_get_temp_dir(), 'export-sell-');
    file_put_contents($tmp, $response->streamedContent());

    $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet();
    unlink($tmp);

    expect($sheet->getCell('A2')->getValue())->toBe('10/03/2026')
        ->and($sheet->getCell('E2')->getValue())->toBe('GROUP-ITEM-A')
        ->and((float) $sheet->getCell('I2')->getValue())->toBe(1000.0)
        ->and((float) $sheet->getCell('J2')->getValue())->toBe(7.0)
        ->and((float) $sheet->getCell('K2')->getValue())->toBe(-500000.0)
        ->and($sheet->getCell('L2')->getValue())->toBe('Grouped header note')
        ->and($sheet->getCell('A3')->getValue())->toBeNull()
        ->and($sheet->getCell('E3')->getValue())->toBe('GROUP-ITEM-B')
        ->and($sheet->getCell('I3')->getValue())->toBeNull()
        ->and($sheet->getCell('J3')->getValue())->toBeNull()
        ->and($sheet->getCell('K3')->getValue())->toBeNull()
        ->and($sheet->getCell('L3')->getValue())->toBeNull();
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

it('lists non-cash transaction detail lines by default', function () {
    $supplier = Addrbook::factory()->supplier()->create(['name' => 'Supplier Export']);
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'WH Export']);

    $sellTx = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => 'SELL-EXP-ALL-1',
        'sender_id' => $warehouse->id,
        'receiver_id' => Addrbook::factory()->customer()->create()->id,
    ]);
    $buyTx = Transaction::factory()->create([
        'type' => Transaction::TYPE_BUY,
        'invoice' => 'BUY-EXP-ALL-1',
        'sender_id' => $supplier->id,
        'receiver_id' => $warehouse->id,
    ]);
    $cashTx = Transaction::factory()->create([
        'type' => Transaction::TYPE_CASH_IN,
        'invoice' => 'CASH-EXP-HIDDEN',
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $sellTx->id,
        'transaction_type' => Transaction::TYPE_SELL,
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $buyTx->id,
        'transaction_type' => Transaction::TYPE_BUY,
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $cashTx->id,
        'transaction_type' => Transaction::TYPE_CASH_IN,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.export-sell'))
        ->assertOk()
        ->assertSee('SELL-EXP-ALL-1', false)
        ->assertSee('BUY-EXP-ALL-1', false)
        ->assertDontSee('CASH-EXP-HIDDEN', false);
});

it('respects per_page options in export sell query service', function () {
    $service = app(ExportSellQueryService::class);

    expect($service->resolvePerPage(request()->merge(['per_page' => 200])))->toBe(200)
        ->and($service->resolvePerPage(request()->merge(['per_page' => 999])))->toBe(100);
});

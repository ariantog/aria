<?php

use App\Models\Addrbook;
use App\Models\Transaction;
use App\Models\User;

/**
 * Smoke tests: every Blade-migrated page must render without throwing.
 *
 * These catch missing view variables, enum/array handling mistakes and layout regressions
 * that manual clicking tends to miss. The first user created gets id 1, which this app
 * treats as the one and only superadmin, so it bypasses every Gate check.
 */
beforeEach(function () {
    $this->user = User::factory()->create();

    expect($this->user->is_superadmin)->toBeTrue();

    $supplier  = Addrbook::factory()->supplier()->create(['name' => 'Test Supplier']);
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Test Warehouse']);

    Transaction::factory()->create([
        'type'           => Transaction::TYPE_BUY,
        'invoice_number' => 'INV-SMOKE-1',
        'sender_type'    => (string) Addrbook::TYPE_SUPPLIER,
        'sender_id'      => $supplier->id,
        'receiver_type'  => (string) Addrbook::TYPE_WAREHOUSE,
        'receiver_id'    => $warehouse->id,
        'grand_total'    => 1_000_000,
        'total_items'    => 5,
        'user_id'        => $this->user->id,
    ]);
});

it('renders the dashboard', function () {
    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Dashboard', false);
});

it('renders the transactions index with its rows', function () {
    $this->actingAs($this->user)
        ->get('/transactions')
        ->assertOk()
        ->assertSee('Grand Total', false)
        ->assertSee('INV-SMOKE-1', false)
        ->assertSee('Test Supplier', false);
});

it('sorts and filters the transactions index', function () {
    $this->actingAs($this->user)
        ->get('/transactions?sort=grand_total&direction=asc&type='.Transaction::TYPE_BUY)
        ->assertOk()
        ->assertSee('INV-SMOKE-1', false);
});

it('applies a filter that excludes every row without erroring', function () {
    $this->actingAs($this->user)
        ->get('/transactions?invoice_number=NOPE-DOES-NOT-EXIST')
        ->assertOk()
        ->assertDontSee('INV-SMOKE-1', false);
});

it('renders the create form for each item transaction type', function (string $type) {
    $this->actingAs($this->user)
        ->get("/transactions/{$type}/create")
        ->assertOk()
        ->assertSee('Line Items', false);
})->with(['buy', 'sell', 'move']);

it('renders the cash in page', function () {
    $this->actingAs($this->user)
        ->get('/transactions/cash-in')
        ->assertOk()
        ->assertSee('Cash Entries', false);
});

it('renders the cash out page', function () {
    $this->actingAs($this->user)
        ->get('/transactions/cash-out')
        ->assertOk()
        ->assertSee('Cash Entries', false);
});

it('renders the transfer page', function () {
    $this->actingAs($this->user)
        ->get('/transactions/transfer')
        ->assertOk()
        ->assertSee('Transfer Money', false);
});

it('renders the adjust page', function () {
    $this->actingAs($this->user)
        ->get('/transactions/adjust')
        ->assertOk()
        ->assertSee('New Adjust', false);
});

it('renders the transaction show page', function () {
    $transaction = Transaction::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->get(route('transactions.show', $transaction))
        ->assertOk();
});

it('renders the deleted transactions index', function () {
    $this->actingAs($this->user)
        ->get(route('transactions.deleted.index'))
        ->assertOk();
});

/**
 * Data-driven GET-200 smoke coverage for the remaining migrated pages.
 * Kept as a single iterating test to stay fast.
 */
it('renders migrated GET pages with a 200', function (string $route) {
    $this->actingAs($this->user)
        ->get($route)
        ->assertOk();
})->with([
    // Settings
    'settings profile' => 'settings/profile',
    'settings password' => 'settings/password',
    'settings appearance' => 'settings/appearance',

    // Produksi
    'produksi index' => 'produksi',
    'produksi setoran' => 'produksi/setoran',

    // Reports
    'report warehouse-item' => 'reports/warehouse-item',
    'report purchase' => 'reports/purchase',
    'report expense' => 'reports/expense',
    'report cash-flow' => 'reports/cash-flow',
    'report nett-cash-sby' => 'reports/nett-cash-sby',
    'report item-sales' => 'reports/item-sales',
    'report compare' => 'reports/compare',
    'report inventory-health' => 'reports/inventory-health',
    'report warehouse-arrangement' => 'reports/warehouse-arrangement',

    // Admin / misc index pages
    'addrbook' => 'addrbook',
    'items' => 'items',
    'users' => 'users',
    'roles' => 'roles',
    'system-settings' => 'system-settings',
    'jubelio index' => 'jubelio',
    'jubelio get orders' => 'jubelio-get-orders',
    'restock index' => 'restock',
]);

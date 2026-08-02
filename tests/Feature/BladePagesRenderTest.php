<?php

use App\Models\User;

/**
 * Smoke tests: every Blade-migrated page must render for a superadmin
 * without throwing (no 500s, no missing view/variable errors).
 */
beforeEach(function () {
    $this->user = User::where('username', 'superadmin')->first();

    if (! $this->user) {
        $this->markTestSkipped('superadmin user not seeded.');
    }
});

it('renders the dashboard', function () {
    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Dashboard', false);
});

it('renders the transactions index with seeded rows', function () {
    $this->actingAs($this->user)
        ->get('/transactions')
        ->assertOk()
        ->assertSee('Transactions', false)
        ->assertSee('Grand Total', false);
});

it('sorts and filters the transactions index', function () {
    $this->actingAs($this->user)
        ->get('/transactions?sort=grand_total&direction=asc&type=2')
        ->assertOk()
        ->assertSee('Grand Total', false);
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

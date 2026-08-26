<?php

use App\Models\Addrbook;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('filters multi-type lookup endpoints by search term', function () {
    $customer = Addrbook::create(['name' => 'Zeta Customer', 'type' => Addrbook::TYPE_CUSTOMER]);
    $reseller = Addrbook::create(['name' => 'Zeta Reseller', 'type' => Addrbook::TYPE_RESELLER]);
    Addrbook::create(['name' => 'Alpha Customer', 'type' => Addrbook::TYPE_CUSTOMER]);
    Addrbook::create(['name' => 'Zeta Supplier', 'type' => Addrbook::TYPE_SUPPLIER]);

    // Simulate the browser combobox: the endpoint already carries addrbook_type[]
    // query params and the search term is appended with '&'.
    $base = route('transactions.lookup', [
        'type' => 'sell', 'role' => 'receiver',
        'addrbook_type' => [Addrbook::TYPE_CUSTOMER, Addrbook::TYPE_RESELLER],
    ]);
    $url = $base.'&search=Zeta&json=true';

    $names = collect($this->getJson($url)->assertOk()->json())->pluck('name');

    expect($names)->toContain('Zeta Customer')
        ->toContain('Zeta Reseller')
        ->not->toContain('Alpha Customer')
        ->not->toContain('Zeta Supplier');
});

it('keeps the search parameter separate when the endpoint has no query string', function () {
    Addrbook::create(['name' => 'Solo Warehouse', 'type' => Addrbook::TYPE_WAREHOUSE]);
    Addrbook::create(['name' => 'Other Warehouse', 'type' => Addrbook::TYPE_WAREHOUSE]);

    $url = route('transactions.lookup', ['type' => 'adjust', 'role' => 'sender']).'?search=Solo';
    $names = collect($this->getJson($url)->assertOk()->json())->pluck('name');

    expect($names)->toContain('Solo Warehouse')->not->toContain('Other Warehouse');
});

it('matches addrbook names when spaces are used as token separators', function () {
    Addrbook::create(['name' => 'North Jakarta Warehouse', 'type' => Addrbook::TYPE_WAREHOUSE]);
    Addrbook::create(['name' => 'South Jakarta Warehouse', 'type' => Addrbook::TYPE_WAREHOUSE]);
    Addrbook::create(['name' => 'North Bandung Warehouse', 'type' => Addrbook::TYPE_WAREHOUSE]);

    $url = route('transactions.lookup', ['type' => 'move', 'role' => 'sender']).'?search=North Jakarta';
    $names = collect($this->getJson($url)->assertOk()->json())->pluck('name');

    expect($names)->toContain('North Jakarta Warehouse')
        ->not->toContain('South Jakarta Warehouse')
        ->not->toContain('North Bandung Warehouse');
});

it('returns no addrbook results until the search term is longer than two characters', function () {
    Addrbook::create(['name' => 'Zeta Customer', 'type' => Addrbook::TYPE_CUSTOMER]);

    $url = route('transactions.lookup', ['type' => 'sell', 'role' => 'receiver', 'addrbook_type' => Addrbook::TYPE_CUSTOMER]);

    $this->getJson($url)->assertOk()->assertExactJson([]);
    $this->getJson($url.'&search=Ze')->assertOk()->assertExactJson([]);
    $this->getJson($url.'&search=Zet')->assertOk()->assertJsonCount(1);
});

it('allows export sell users to use sell transaction party lookup', function () {
    Permission::firstOrCreate(['name' => 'report-export-sell']);
    $user = User::factory()->create();
    $user->givePermissionTo('report-export-sell');

    Addrbook::create(['name' => 'Export Sell Warehouse', 'type' => Addrbook::TYPE_WAREHOUSE]);

    $url = route('transactions.lookup', [
        'type' => 'sell',
        'role' => 'sender',
        'addrbook_type' => Addrbook::TYPE_WAREHOUSE,
    ]).'&search=Export';

    $this->actingAs($user)
        ->getJson($url)
        ->assertOk()
        ->assertJsonFragment(['name' => 'Export Sell Warehouse']);
});

it('caps addrbook lookup results at eight rows', function () {
    foreach (range(1, 10) as $i) {
        Addrbook::create(['name' => "Lookup Customer {$i}", 'type' => Addrbook::TYPE_CUSTOMER]);
    }

    $url = route('transactions.lookup', ['type' => 'sell', 'role' => 'receiver', 'addrbook_type' => Addrbook::TYPE_CUSTOMER])
        .'&search=Lookup';

    expect($this->getJson($url)->assertOk()->json())->toHaveCount(8);
});

it('limits cash in lookup to ledger customer and reseller parties', function () {
    Addrbook::create(['name' => 'Cash Ledger', 'type' => Addrbook::TYPE_ACCOUNT]);
    Addrbook::create(['name' => 'Cash Customer', 'type' => Addrbook::TYPE_CUSTOMER]);
    Addrbook::create(['name' => 'Cash Reseller', 'type' => Addrbook::TYPE_RESELLER]);
    Addrbook::create(['name' => 'Cash Supplier', 'type' => Addrbook::TYPE_SUPPLIER]);
    Addrbook::create(['name' => 'Cash Warehouse', 'type' => Addrbook::TYPE_WAREHOUSE]);

    $url = route('transactions.lookup', [
        'type' => 'cash-in',
        'role' => 'sender',
        'addrbook_type' => Addrbook::cashPartyTypes(),
    ]).'&search=Cash';

    $names = collect($this->getJson($url)->assertOk()->json())->pluck('name');

    expect($names)->toContain('Cash Ledger')
        ->toContain('Cash Customer')
        ->toContain('Cash Reseller')
        ->not->toContain('Cash Supplier')
        ->not->toContain('Cash Warehouse');
});

it('limits cash out lookup to ledger customer and reseller parties', function () {
    Addrbook::create(['name' => 'Pay Ledger', 'type' => Addrbook::TYPE_ACCOUNT]);
    Addrbook::create(['name' => 'Pay Customer', 'type' => Addrbook::TYPE_CUSTOMER]);
    Addrbook::create(['name' => 'Pay Supplier', 'type' => Addrbook::TYPE_SUPPLIER]);

    $url = route('transactions.lookup', [
        'type' => 'cash-out',
        'role' => 'receiver',
        'addrbook_type' => Addrbook::cashPartyTypes(),
    ]).'&search=Pay';

    $names = collect($this->getJson($url)->assertOk()->json())->pluck('name');

    expect($names)->toContain('Pay Ledger')
        ->toContain('Pay Customer')
        ->not->toContain('Pay Supplier');
});

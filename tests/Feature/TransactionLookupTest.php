<?php

use App\Models\Addrbook;
use App\Models\User;

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

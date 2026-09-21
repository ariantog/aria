<?php

use App\Enums\AddrbookType;
use App\Models\Addrbook;
use App\Models\Location;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    User::factory()->create();

    $this->user = User::factory()->create();
    $this->location = Location::create(['name' => 'Store A']);
    $this->customer = Addrbook::factory()->create(['type' => AddrbookType::Customer, 'name' => 'Customer One']);

    foreach (Location::getPermissions() as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }
    Permission::firstOrCreate(['name' => 'addrbook-customer-edit']);
    $this->user->givePermissionTo([
        Location::getPermissions()['edit'],
        'addrbook-customer-edit',
    ]);
});

it('shows the location customer assignment page', function () {
    $this->actingAs($this->user)
        ->get(route('locations.customers', $this->location))
        ->assertOk()
        ->assertSee('Manage addrbook contacts linked to this location');
});

it('links a supplier to a location from the assignment page', function () {
    $supplier = Addrbook::factory()->supplier()->create(['name' => 'Loc Supplier']);

    $this->actingAs($this->user)
        ->post(route('locations.customers.attach', $this->location), [
            'customer_id' => $supplier->id,
        ])
        ->assertRedirect(route('locations.customers', $this->location))
        ->assertSessionHas('success');

    expect($this->location->customers()->pluck('customers.id'))->toContain($supplier->id);
});

it('finds non-customer addrbook entries in location search', function () {
    $supplier = Addrbook::factory()->supplier()->create(['name' => 'Unique Loc Supplier']);

    $this->actingAs($this->user)
        ->get(route('locations.customers', ['location' => $this->location, 'q' => 'Unique Loc Supplier']))
        ->assertOk()
        ->assertSee('Unique Loc Supplier')
        ->assertSee('Supplier');
});

it('rejects linking a ledger account to a location', function () {
    $ledger = Addrbook::factory()->account()->create(['name' => 'Expense Ledger']);

    $this->actingAs($this->user)
        ->post(route('locations.customers.attach', $this->location), [
            'customer_id' => $ledger->id,
        ])
        ->assertRedirect(route('locations.customers', $this->location))
        ->assertSessionHas('error');

    expect($this->location->customers()->pluck('customers.id'))->not->toContain($ledger->id);
});

it('links a customer to a location from the assignment page', function () {
    $this->actingAs($this->user)
        ->post(route('locations.customers.attach', $this->location), [
            'customer_id' => $this->customer->id,
        ])
        ->assertRedirect(route('locations.customers', $this->location))
        ->assertSessionHas('success');

    expect($this->location->customers()->pluck('customers.id'))->toContain($this->customer->id);
});

it('removes a customer from a location', function () {
    $this->location->customers()->attach($this->customer->id);

    $this->actingAs($this->user)
        ->delete(route('locations.customers.detach', [$this->location, $this->customer]))
        ->assertRedirect(route('locations.customers', $this->location))
        ->assertSessionHas('success');

    expect($this->location->customers()->pluck('customers.id'))->not->toContain($this->customer->id);
});

it('syncs customer locations from the addrbook edit form', function () {
    $locationB = Location::create(['name' => 'Store B']);

    $this->actingAs($this->user)
        ->put(route('addrbook.update', $this->customer), [
            'name' => $this->customer->name,
            'type' => AddrbookType::Customer->value,
            'location_ids' => [$this->location->id, $locationB->id],
        ])
        ->assertRedirect(\App\Models\Addrbook::typeIndexRoute(\App\Models\Addrbook::TYPE_CUSTOMER));

    expect($this->customer->fresh()->locations->pluck('id')->all())
        ->toEqual([$this->location->id, $locationB->id]);
});

it('shows location on the users list', function () {
    Permission::firstOrCreate(['name' => 'users-list']);
    $this->user->givePermissionTo('users-list');
    $this->user->update(['location_id' => $this->location->id]);

    $this->actingAs($this->user)
        ->get(route('users.index'))
        ->assertOk()
        ->assertSee('Store A')
        ->assertDontSee('Email', false);
});

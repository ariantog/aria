<?php

use App\Models\Addrbook;
use App\Models\Operation;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->withoutVite();
    // Assuming a role or permissions seed isn't strictly required for these controllers since Gate wasn't explicitly added yet, or we'll bypass it.
});

test('can view operations page', function () {
    $response = $this->actingAs($this->user)->get('/journals/operations');
    $response->assertStatus(200);
});

test('can create operation', function () {
    $response = $this->actingAs($this->user)->post('/journals/operations', [
        'name' => 'Operational Expenses',
        'description' => 'Daily expenses',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('operations', ['name' => 'Operational Expenses']);
});

test('can create account list linked to operation', function () {
    $operation = Operation::create(['name' => 'Assets']);

    $response = $this->actingAs($this->user)->post('/journals/account-list', [
        'name' => 'Bank BCA',
        'description' => 'Main Bank Account',
        'operation_id' => $operation->id,
    ]);

    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('customers', [
        'name' => 'Bank BCA',
        'type' => Addrbook::TYPE_ACCOUNT,
        'parent_id' => $operation->id,
    ]);

    $account = Addrbook::where('name', 'Bank BCA')->first();
    $this->assertDatabaseHas('customerstat', [
        'customer_id' => $account->id,
        'balance' => 0,
    ]);
});

it('can view operation ledger page', function () {
    $operation = Operation::factory()->create();
    $account = Addrbook::factory()->create([
        'type' => Addrbook::TYPE_ACCOUNT,
        'parent_id' => $operation->id,
    ]);

    $response = $this->actingAs($this->user)->get("/journals/account-list/{$account->id}/ledger");

    $response->assertStatus(200);
});

it('can view operation details page', function () {
    $operation = Operation::factory()->create();

    $response = $this->actingAs($this->user)->get("/journals/operations/{$operation->id}");

    $response->assertStatus(200);
});

test('can view ledger for account', function () {
    $operation = Operation::create(['name' => 'Income']);
    $account = Addrbook::create([
        'name' => 'Sales Info',
        'type' => Addrbook::TYPE_ACCOUNT,
        'parent_id' => $operation->id,
    ]);

    $response = $this->actingAs($this->user)->get("/journals/account-list/{$account->id}/ledger");
    $response->assertStatus(200);
});

test('account list shows operation from legacy parent_id', function () {
    $operation = Operation::create(['name' => 'Operational Expenses']);

    Addrbook::create([
        'name' => 'Office Rent',
        'type' => Addrbook::TYPE_ACCOUNT,
        'parent_id' => $operation->id,
    ]);

    $response = $this->actingAs($this->user)->get('/journals/account-list');

    $response->assertOk()
        ->assertSee('Office Rent')
        ->assertSee('Operational Expenses')
        ->assertDontSee('Uncategorized');
});

test('journal account list is sorted by name by default', function () {
    $operation = Operation::create(['name' => 'Expenses']);

    Addrbook::create([
        'name' => 'Alpha Ledger',
        'type' => Addrbook::TYPE_ACCOUNT,
        'parent_id' => $operation->id,
    ]);
    Addrbook::create([
        'name' => 'Zebra Ledger',
        'type' => Addrbook::TYPE_ACCOUNT,
        'parent_id' => $operation->id,
    ]);

    $this->actingAs($this->user)
        ->get('/journals/account-list')
        ->assertOk()
        ->assertSeeInOrder(['Alpha Ledger', 'Zebra Ledger'], false);
});

test('operation account list is sorted by name by default', function () {
    $operation = Operation::create(['name' => 'Expenses']);

    Addrbook::create([
        'name' => 'Alpha Ledger',
        'type' => Addrbook::TYPE_ACCOUNT,
        'parent_id' => $operation->id,
    ]);
    Addrbook::create([
        'name' => 'Zebra Ledger',
        'type' => Addrbook::TYPE_ACCOUNT,
        'parent_id' => $operation->id,
    ]);

    $this->actingAs($this->user)
        ->get("/journals/operations/{$operation->id}")
        ->assertOk()
        ->assertSeeInOrder(['Alpha Ledger', 'Zebra Ledger'], false);
});

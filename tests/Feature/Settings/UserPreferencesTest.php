<?php

use App\Models\Addrbook;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\UserPreferenceService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->supplier = Addrbook::factory()->supplier()->create(['name' => 'Pref Supplier']);
    $this->warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Pref Warehouse']);
    $this->bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK, 'name' => 'Pref Bank']);
    $this->customer = Addrbook::factory()->customer()->create(['name' => 'Pref Customer']);
});

test('user can save transaction defaults', function () {
    $this->actingAs($this->user)
        ->put(route('transaction-defaults.update'), [
            'default_supplier_id' => $this->supplier->id,
            'default_warehouse_id' => $this->warehouse->id,
            'default_cash_in_bank_id' => $this->bank->id,
        ])
        ->assertRedirect(route('transaction-defaults.edit'))
        ->assertSessionHas('success');

    expect(UserPreference::where('user_id', $this->user->id)->count())->toBe(3);
    expect(app(UserPreferenceService::class)->get($this->user, 'transactions.default_supplier_id'))->toBe($this->supplier->id);
});

test('transaction create prefill uses user defaults', function () {
    app(UserPreferenceService::class)->updateTransactionDefaults($this->user, [
        'default_supplier_id' => $this->supplier->id,
        'default_warehouse_id' => $this->warehouse->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.create', ['type' => 'buy']))
        ->assertOk()
        ->assertSee('Pref Supplier', false)
        ->assertSee('Pref Warehouse', false);
});

test('buy prefill falls back to global restock defaults when user has no preference', function () {
    Setting::updateOrCreate(['slug' => 'restock.default_supplier_id'], [
        'group' => 'Restock',
        'name' => 'Default Supplier',
        'value' => $this->supplier->id,
    ]);
    Setting::updateOrCreate(['slug' => 'restock.default_receiver_id'], [
        'group' => 'Restock',
        'name' => 'Default Receiver',
        'value' => $this->warehouse->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('transactions.create', ['type' => 'buy']))
        ->assertOk()
        ->assertSee('Pref Supplier', false)
        ->assertSee('Pref Warehouse', false);
});

test('cash in form preselects default bank account', function () {
    app(UserPreferenceService::class)->set($this->user, 'transactions.default_cash_in_bank_id', $this->bank->id);

    $this->actingAs($this->user)
        ->get(route('transactions.cash-in'))
        ->assertOk()
        ->assertSee('value="'.$this->bank->id.'"', false);
});

test('user can save appearance preference', function () {
    $this->actingAs($this->user)
        ->patch(route('appearance.update'), [
            'appearance' => 'dark',
            'font_size' => 'large',
        ])
        ->assertRedirect(route('appearance.edit'))
        ->assertSessionHas('success');

    $preferences = app(UserPreferenceService::class);
    expect($preferences->appearanceFor($this->user))->toBe('dark')
        ->and($preferences->fontSizeFor($this->user))->toBe('large');
});

test('transaction defaults lookup respects addrbook type', function () {
    $this->actingAs($this->user)
        ->getJson(route('transaction-defaults.lookup', ['type' => 'supplier', 'search' => 'Pref']))
        ->assertOk()
        ->assertJsonFragment(['id' => $this->supplier->id, 'name' => 'Pref Supplier'])
        ->assertJsonMissing(['id' => $this->warehouse->id]);
});

test('any authenticated user can access transaction defaults without special permission', function () {
    $regularUser = User::factory()->create();

    expect($regularUser->is_superadmin)->toBeFalse();

    $this->actingAs($regularUser)
        ->get(route('transaction-defaults.edit'))
        ->assertOk()
        ->assertSee('Transaction defaults', false)
        ->assertSee('Default supplier', false)
        ->assertSee('Save defaults', false);
});

test('transaction defaults lookup returns contacts linked to the user location', function () {
    $location = \App\Models\Location::factory()->create();
    $supplier = Addrbook::factory()->supplier()->create(['name' => 'Loc Supplier']);
    $supplier->locations()->attach($location->id);

    $user = User::factory()->create(['location_id' => $location->id]);

    $this->actingAs($user)
        ->getJson(route('transaction-defaults.lookup', ['type' => 'supplier', 'search' => 'Loc']))
        ->assertOk()
        ->assertJsonFragment(['name' => 'Loc Supplier']);
});

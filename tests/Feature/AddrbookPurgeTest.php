<?php

use App\Models\Addrbook;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DataRetentionService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->superadmin = User::query()->find(1) ?? User::factory()->create(['id' => 1]);
    $this->otherUser = User::factory()->create();
    expect($this->otherUser->id)->not->toBe(User::SUPERADMIN_ID);
});

it('renders the delete addrbook page for superadmin only', function () {
    $this->actingAs($this->superadmin)
        ->get(route('data-retention.addrbook-purge.index'))
        ->assertSuccessful()
        ->assertSee('Delete Addrbook');

    $this->actingAs($this->otherUser)
        ->get(route('data-retention.addrbook-purge.index'))
        ->assertForbidden();
});

it('previews an addrbook without transactions as deletable', function () {
    $addrbook = Addrbook::factory()->account()->create(['name' => 'Unused Ledger']);

    $this->actingAs($this->superadmin)
        ->get(route('data-retention.addrbook-purge.index', ['addrbook_id' => $addrbook->id]))
        ->assertSuccessful()
        ->assertSee('Unused Ledger')
        ->assertSee('No transactions — eligible for deletion')
        ->assertSee('data-testid="addrbook-purge-deletable"', false);
});

it('previews an addrbook with transactions as not deletable', function () {
    $addrbook = Addrbook::factory()->warehouse()->create(['name' => 'Busy Warehouse']);
    Transaction::factory()->create([
        'sender_id' => $addrbook->id,
        'receiver_id' => $addrbook->id,
    ]);

    $this->actingAs($this->superadmin)
        ->get(route('data-retention.addrbook-purge.index', ['addrbook_id' => $addrbook->id]))
        ->assertSuccessful()
        ->assertSee('Busy Warehouse')
        ->assertSee('Present in transactions — cannot delete')
        ->assertDontSee('data-testid="addrbook-purge-form"', false);
});

it('hard deletes an addrbook that never appeared in transactions', function () {
    $addrbook = Addrbook::factory()->customer()->create(['name' => 'Ghost Customer']);

    $this->actingAs($this->superadmin)
        ->post(route('data-retention.addrbook-purge.destroy'), [
            'addrbook_id' => $addrbook->id,
            'confirm' => 'DELETE-ADDRBOOK',
        ])
        ->assertRedirect(route('data-retention.addrbook-purge.index'))
        ->assertSessionHas('success');

    expect(DB::table('customers')->where('id', $addrbook->id)->exists())->toBeFalse();
});

it('rejects deleting an addrbook that appears in transactions', function () {
    $addrbook = Addrbook::factory()->bank()->create(['name' => 'Active Bank']);
    Transaction::factory()->create([
        'sender_id' => $addrbook->id,
        'receiver_id' => $addrbook->id,
    ]);

    $this->actingAs($this->superadmin)
        ->post(route('data-retention.addrbook-purge.destroy'), [
            'addrbook_id' => $addrbook->id,
            'confirm' => 'DELETE-ADDRBOOK',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(DB::table('customers')->where('id', $addrbook->id)->exists())->toBeTrue();
});

it('checks only the transactions table for eligibility', function () {
    $addrbook = Addrbook::factory()->supplier()->create();
    $retention = app(DataRetentionService::class);

    expect($retention->addrbookAppearsInTransactions($addrbook->id))->toBeFalse();

    Transaction::factory()->create([
        'sender_id' => $addrbook->id,
        'receiver_id' => $addrbook->id,
    ]);

    expect($retention->addrbookAppearsInTransactions($addrbook->id))->toBeTrue();
});

it('lists deletable addrbooks by type', function () {
    $eligible = Addrbook::factory()->account()->create(['name' => 'Unused Ledger A']);
    $blocked = Addrbook::factory()->account()->create(['name' => 'Used Ledger']);
    Transaction::factory()->create([
        'sender_id' => $blocked->id,
        'receiver_id' => $blocked->id,
    ]);

    $this->actingAs($this->superadmin)
        ->get(route('data-retention.addrbook-purge.index', ['type' => Addrbook::TYPE_ACCOUNT]))
        ->assertSuccessful()
        ->assertSee('Browse by type')
        ->assertSee('Unused Ledger A')
        ->assertDontSee('Used Ledger');
});

it('shows an inline delete panel when a list row is selected', function () {
    $addrbook = Addrbook::factory()->account()->create(['name' => 'Pick Me Ledger']);

    $this->actingAs($this->superadmin)
        ->get(route('data-retention.addrbook-purge.index', [
            'type' => Addrbook::TYPE_ACCOUNT,
            'addrbook_id' => $addrbook->id,
        ]))
        ->assertSuccessful()
        ->assertSee('Pick Me Ledger')
        ->assertSee('Selected addrbook')
        ->assertSee('data-testid="addrbook-purge-form"', false)
        ->assertSee('View addrbook →');
});

it('bulk deletes selected addrbooks on the current page', function () {
    $first = Addrbook::factory()->warehouse()->create(['name' => 'Empty Warehouse A']);
    $second = Addrbook::factory()->warehouse()->create(['name' => 'Empty Warehouse B']);

    $this->actingAs($this->superadmin)
        ->post(route('data-retention.addrbook-purge.purge'), [
            'type' => Addrbook::TYPE_WAREHOUSE,
            'page' => 1,
            'page_addrbook_ids' => [$first->id, $second->id],
            'keep_ids' => [$second->id],
            'confirm' => 'DELETE-ADDRBOOK',
        ])
        ->assertRedirect(route('data-retention.addrbook-purge.index', ['type' => Addrbook::TYPE_WAREHOUSE]))
        ->assertSessionHas('success');

    expect(DB::table('customers')->where('id', $first->id)->exists())->toBeFalse()
        ->and(DB::table('customers')->where('id', $second->id)->exists())->toBeTrue();
});

it('lists deletable addrbooks using a not-in union of transaction party ids', function () {
    DB::enableQueryLog();

    app(DataRetentionService::class)->countDeletableAddrbooks(Addrbook::TYPE_CUSTOMER);

    $sql = collect(DB::getQueryLog())->pluck('query')->join(' ');

    expect($sql)->toContain('not in')
        ->and($sql)->toContain('union');
});

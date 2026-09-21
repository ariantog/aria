<?php

use App\Models\Addrbook;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DataRetentionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            'keep_ids' => [$second->id],
            'confirm' => 'DELETE-ADDRBOOK',
        ])
        ->assertRedirect(route('data-retention.addrbook-purge.index', ['type' => Addrbook::TYPE_WAREHOUSE]))
        ->assertSessionHas('success');

    expect(DB::table('customers')->where('id', $first->id)->exists())->toBeFalse()
        ->and(DB::table('customers')->where('id', $second->id)->exists())->toBeTrue();
});

it('bulk delete only purges unchecked rows on the server-resolved page', function () {
    $retention = app(DataRetentionService::class);
    $first = Addrbook::factory()->warehouse()->create(['name' => 'AAA Warehouse']);
    $second = Addrbook::factory()->warehouse()->create(['name' => 'ZZZ Warehouse']);

    expect($retention->deletableAddrbookIdsOnPage(Addrbook::TYPE_WAREHOUSE, 1, 1))->toBe([$first->id]);

    $purged = $retention->purgeDeletableAddrbooksOnPage(
        Addrbook::TYPE_WAREHOUSE,
        1,
        keepIds: [],
        perPage: 1,
    );

    expect($purged)->toBe(1)
        ->and(DB::table('customers')->where('id', $first->id)->exists())->toBeFalse()
        ->and(DB::table('customers')->where('id', $second->id)->exists())->toBeTrue();
});

it('bulk delete ignores keep ids that are not on the current page', function () {
    $retention = app(DataRetentionService::class);
    $onPage = Addrbook::factory()->warehouse()->create(['name' => 'AAA Warehouse']);
    $offPage = Addrbook::factory()->warehouse()->create(['name' => 'ZZZ Warehouse']);

    $purged = $retention->purgeDeletableAddrbooksOnPage(
        Addrbook::TYPE_WAREHOUSE,
        1,
        keepIds: [$offPage->id],
        perPage: 1,
    );

    expect($purged)->toBe(1)
        ->and(DB::table('customers')->where('id', $onPage->id)->exists())->toBeFalse()
        ->and(DB::table('customers')->where('id', $offPage->id)->exists())->toBeTrue();
});

it('lists deletable addrbooks using a not-in union of transaction party ids', function () {
    DB::enableQueryLog();

    app(DataRetentionService::class)->countDeletableAddrbooks(Addrbook::TYPE_CUSTOMER);

    $sql = collect(DB::getQueryLog())->pluck('query')->join(' ');

    expect($sql)->toContain('not in')
        ->and($sql)->toContain('union');
});

it('hard deletes related rows such as customerstat, warehouse_item, and jubeliosyncs', function () {
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Empty Warehouse']);
    $item = \App\Models\Item::factory()->create();

    DB::table('customerstat')->insert([
        'customer_id' => $warehouse->id,
        'balance' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('warehouse_item')->insert([
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'quantity' => 4,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    if (Schema::hasTable('jubeliosyncs')) {
        DB::table('jubeliosyncs')->insert([
            'jubelio_store_id' => 1,
            'jubelio_store_name' => 'Test Store',
            'jubelio_location_id' => 1,
            'jubelio_location_name' => 'Test Location',
            'warehouse_id' => $warehouse->id,
            'customer_id' => Addrbook::factory()->customer()->create()->id,
            'bin_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $this->actingAs($this->superadmin)
        ->post(route('data-retention.addrbook-purge.destroy'), [
            'addrbook_id' => $warehouse->id,
            'confirm' => 'DELETE-ADDRBOOK',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(DB::table('customers')->where('id', $warehouse->id)->exists())->toBeFalse()
        ->and(DB::table('customerstat')->where('customer_id', $warehouse->id)->exists())->toBeFalse()
        ->and(DB::table('warehouse_item')->where('warehouse_id', $warehouse->id)->exists())->toBeFalse();

    if (Schema::hasTable('jubeliosyncs')) {
        expect(DB::table('jubeliosyncs')->where('warehouse_id', $warehouse->id)->exists())->toBeFalse();
    }
});

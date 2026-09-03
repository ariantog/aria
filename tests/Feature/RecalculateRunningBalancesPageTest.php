<?php

use App\Models\Addrbook;
use App\Models\AddrbookStat;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Services\PermissionGenerator;
use App\Services\TransactionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Carbon::setTestNow('2026-08-27');
});

afterEach(function () {
    Carbon::setTestNow();
});

function seedPostedBuyForBalancePage(
    Addrbook $supplier,
    Addrbook $warehouse,
    Item $item,
    string $date,
    int $qty,
    int $price,
): Transaction {
    $service = app(TransactionService::class);
    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_BUY,
        'date' => $date,
        'sender_id' => $supplier->id,
        'sender_type' => $supplier->type,
        'receiver_id' => $warehouse->id,
        'receiver_type' => $warehouse->type,
        'total' => $qty * $price,
        'status' => Transaction::STATUS_COMPLETED,
    ]);
    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
        'quantity' => $qty,
        'price' => $price,
        'total' => $qty * $price,
    ]);
    $service->handleTransaction($transaction);

    return $transaction->fresh();
}

it('renders the recalculate running balances page for superadmin', function () {
    $user = User::factory()->create();
    expect($user->is_superadmin)->toBeTrue();

    $this->actingAs($user)
        ->get(route('recalculate-running-balances.index'))
        ->assertSuccessful()
        ->assertSee('Recalculate Running Balances', false)
        ->assertSee('app:recalculate-running-balances', false)
        ->assertSee('data-testid="recalculate-running-balances-form"', false)
        ->assertSee('Running Balances', false);
});

it('forbids users without settings view permission', function () {
    User::factory()->create();
    $user = User::factory()->create();
    expect($user->is_superadmin)->toBeFalse();

    $this->actingAs($user)
        ->get(route('recalculate-running-balances.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('recalculate-running-balances.run'), ['confirm' => '1'])
        ->assertForbidden();
});

it('lets a settings viewer open the page but not run the rebuild', function () {
    app(PermissionGenerator::class)->generateForModule('Setting');

    User::factory()->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Setting::getPermissions()['view']);

    $this->actingAs($viewer)
        ->get(route('recalculate-running-balances.index'))
        ->assertSuccessful()
        ->assertSee('system settings edit permission', false)
        ->assertDontSee('data-testid="recalculate-running-balances-submit"', false);

    $this->actingAs($viewer)
        ->post(route('recalculate-running-balances.run'), ['confirm' => '1'])
        ->assertForbidden();
});

it('rebuilds stale running balances from the settings page', function () {
    $user = User::factory()->create();
    $supplier = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER, 'name' => 'UI Supplier']);
    $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
    $item = Item::factory()->create(['qty' => 0]);

    $aug20 = seedPostedBuyForBalancePage($supplier, $warehouse, $item, '2026-08-20', 5, 2000);
    $aug01 = seedPostedBuyForBalancePage($supplier, $warehouse, $item, '2026-08-01', 10, 5000);

    DB::table('transactions')->where('id', $aug01->id)->update(['sender_balance' => 1]);
    DB::table('transactions')->where('id', $aug20->id)->update(['sender_balance' => 2]);
    AddrbookStat::where('customer_id', $supplier->id)->update(['balance' => 0]);

    $this->actingAs($user)
        ->post(route('recalculate-running-balances.run'), [
            'confirm' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect((float) $aug01->fresh()->sender_balance)->toBe(50000.0)
        ->and((float) $aug20->fresh()->sender_balance)->toBe(60000.0)
        ->and((float) AddrbookStat::where('customer_id', $supplier->id)->value('balance'))->toBe(60000.0);
});

it('rebuilds one addrbook from a date when those filters are posted', function () {
    $user = User::factory()->create();
    $supplierA = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER, 'name' => 'Supplier A']);
    $supplierB = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER, 'name' => 'Supplier B']);
    $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
    $item = Item::factory()->create(['qty' => 0]);

    $earlyA = seedPostedBuyForBalancePage($supplierA, $warehouse, $item, '2026-07-01', 1, 1000);
    $lateA = seedPostedBuyForBalancePage($supplierA, $warehouse, $item, '2026-08-10', 2, 1000);
    $lateB = seedPostedBuyForBalancePage($supplierB, $warehouse, $item, '2026-08-10', 3, 1000);

    DB::table('transactions')->whereIn('id', [$lateA->id, $lateB->id])->update(['sender_balance' => 9]);

    $this->actingAs($user)
        ->post(route('recalculate-running-balances.run'), [
            'confirm' => '1',
            'addrbook_id' => $supplierA->id,
            'from' => '2026-08-01',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect((float) $earlyA->fresh()->sender_balance)->toBe(1000.0)
        ->and((float) $lateA->fresh()->sender_balance)->toBe(3000.0)
        ->and((float) $lateB->fresh()->sender_balance)->toBe(9.0);
});

it('requires confirmation before running', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('recalculate-running-balances.index'))
        ->post(route('recalculate-running-balances.run'), [])
        ->assertRedirect(route('recalculate-running-balances.index'))
        ->assertSessionHasErrors('confirm');
});

it('looks up addrbooks for the combobox', function () {
    $user = User::factory()->create();
    $supplier = Addrbook::factory()->create(['type' => Addrbook::TYPE_SUPPLIER, 'name' => 'Lookup Supplier']);

    $this->actingAs($user)
        ->get(route('recalculate-running-balances.lookup', ['search' => 'Lookup']))
        ->assertSuccessful()
        ->assertJsonFragment(['id' => $supplier->id]);

    $this->actingAs($user)
        ->get(route('recalculate-running-balances.lookup', ['search' => (string) $supplier->id]))
        ->assertSuccessful()
        ->assertJsonFragment(['id' => $supplier->id]);
});

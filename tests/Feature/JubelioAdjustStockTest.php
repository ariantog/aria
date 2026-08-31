<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubeliosync;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\JubelioService;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

function seedAdjustStockUser(): User
{
    User::factory()->create();
    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'transactions-show']);
    $user->givePermissionTo('transactions-show');

    return $user;
}

function seedAdjustStockMapping(Addrbook $warehouse): Jubeliosync
{
    return Jubeliosync::create([
        'jubelio_store_id' => 1,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => 10,
        'jubelio_location_name' => 'Jubelio '.$warehouse->name,
        'warehouse_id' => $warehouse->id,
        'customer_id' => Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER])->id,
        'bin_id' => 0,
    ]);
}

function seedMoveForAdjust(Item $item): Transaction
{
    $sender = Addrbook::factory()->warehouse()->create(['name' => 'WH Move From']);
    $receiver = Addrbook::factory()->warehouse()->create(['name' => 'WH Move To']);
    seedAdjustStockMapping($sender);
    seedAdjustStockMapping($receiver);

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_MOVE,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'invoice' => 'INV-MOVE-SYNC',
    ]);
    $transaction->details()->create([
        'date' => $transaction->date,
        'transaction_type' => Transaction::TYPE_MOVE,
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'item_id' => $item->id,
        'quantity' => 3,
        'price' => 1000,
        'discount' => 0,
        'total' => 3000,
    ]);

    return $transaction;
}

function fakeJubelioAdjustToken(): void
{
    config([
        'services.jubelio.active' => true,
        'services.jubelio.url' => 'https://api.jubelio.com/login',
        'services.jubelio.email' => 'test@example.com',
        'services.jubelio.password' => 'secret',
        'services.jubelio.verify_ssl' => false,
    ]);

    Setting::create([
        'group' => 'Jubelio',
        'name' => 'Jubelio Token',
        'slug' => JubelioService::TOKEN_SETTING_SLUG,
        'value' => [
            'token' => 'test-token',
            'expires_at' => now()->addHours(5)->toDateTimeString(),
        ],
    ]);
}

beforeEach(function () {
    config(['services.jubelio.active' => true]);
});

it('marks a move side synced when jubelio returns item_adj_id', function () {
    fakeJubelioAdjustToken();
    Http::fake([
        'https://api2.jubelio.com/inventory/adjustments/warehouse' => Http::response([
            'item_adj_id' => 44121,
            'item_adj_no' => 'ADJ-44121',
        ], 200),
    ]);

    $user = seedAdjustStockUser();
    $item = Item::factory()->create(['jubelio_item_id' => 901, 'code' => 'SKU-MOVE']);
    $transaction = seedMoveForAdjust($item);

    $this->actingAs($user)
        ->post(route('jubelio.adjustStok', $transaction), [
            'side' => 1,
            'whType' => 2,
            'adjustType' => 2,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $transaction->refresh();

    expect($transaction->a_submit_by)->toBe($user->id)
        ->and($transaction->a_reference_id)->toBe('44121')
        ->and($transaction->hasSyncWarningA())->toBeFalse();
});

it('does not mark a move synced when jubelio returns 200 with an error message', function () {
    fakeJubelioAdjustToken();
    Http::fake([
        'https://api2.jubelio.com/inventory/adjustments/warehouse' => Http::response([
            'message' => 'Qty exceeds available stock',
        ], 200),
    ]);

    $user = seedAdjustStockUser();
    $item = Item::factory()->create(['jubelio_item_id' => 902, 'code' => 'SKU-MOVE-ERR']);
    $transaction = seedMoveForAdjust($item);

    $this->actingAs($user)
        ->from(route('transactions.show', $transaction))
        ->post(route('jubelio.adjustStok', $transaction), [
            'side' => 1,
            'whType' => 2,
            'adjustType' => 2,
        ])
        ->assertRedirect(route('transactions.show', $transaction))
        ->assertSessionHas('error', 'Qty exceeds available stock')
        ->assertSessionHas('errorMessage', 'Qty exceeds available stock');

    $transaction->refresh();

    expect($transaction->a_submit_by)->toBeNull()
        ->and($transaction->a_reference_id)->toBeNull()
        ->and($transaction->submit_a_count)->toBe(0)
        ->and($transaction->hasSyncWarningA())->toBeFalse();
});

it('keeps an unclear warning when jubelio returns 200 without an id or error', function () {
    fakeJubelioAdjustToken();
    Http::fake([
        'https://api2.jubelio.com/inventory/adjustments/warehouse' => Http::response(['status' => 'success'], 200),
    ]);

    $user = seedAdjustStockUser();
    $item = Item::factory()->create(['jubelio_item_id' => 903, 'code' => 'SKU-MOVE-AMBIG']);
    $transaction = seedMoveForAdjust($item);

    $this->actingAs($user)
        ->from(route('transactions.show', $transaction))
        ->post(route('jubelio.adjustStok', $transaction), [
            'side' => 2,
            'whType' => 1,
            'adjustType' => 1,
        ])
        ->assertRedirect()
        ->assertSessionHas('error')
        ->assertSessionMissing('success');

    $transaction->refresh();

    expect($transaction->b_submit_by)->toBeNull()
        ->and($transaction->hasSyncWarningB())->toBeTrue()
        ->and($transaction->submit_b_count)->toBe(1);
});

it('does not leave a warning after a clear http error so the user can retry', function () {
    fakeJubelioAdjustToken();
    Http::fake([
        'https://api2.jubelio.com/inventory/adjustments/warehouse' => Http::response([
            'message' => 'Validation error',
        ], 400),
    ]);

    $user = seedAdjustStockUser();
    $item = Item::factory()->create(['jubelio_item_id' => 904, 'code' => 'SKU-MOVE-400']);
    $transaction = seedMoveForAdjust($item);

    $this->actingAs($user)
        ->post(route('jubelio.adjustStok', $transaction), [
            'side' => 1,
            'whType' => 2,
            'adjustType' => 2,
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Validation error');

    $transaction->refresh();

    expect($transaction->hasSyncWarningA())->toBeFalse()
        ->and($transaction->a_submit_by)->toBeNull()
        ->and($transaction->submit_a_count)->toBe(0);
});

it('shows the jubelio error flash on the transaction page', function () {
    $user = seedAdjustStockUser();
    $item = Item::factory()->create(['jubelio_item_id' => 905]);
    $transaction = seedMoveForAdjust($item);

    $html = $this->actingAs($user)
        ->withSession(['errorMessage' => 'Qty exceeds available stock'])
        ->get(route('transactions.show', $transaction))
        ->assertSuccessful()
        ->getContent();

    expect($html)->toContain('Qty exceeds available stock');
});

it('requires a jubelio reference id before confirming an unclear sync', function () {
    $user = seedAdjustStockUser();
    $transaction = Transaction::factory()->create([
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
        'submit_a_count' => 1,
        'a_submit_by' => null,
    ]);

    $this->actingAs($user)
        ->from(route('jubelio.transaction.detail-sync', $transaction))
        ->post(route('jubelio.transaction.sync-confirm', $transaction), [
            'side' => 1,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('reference_id');

    $transaction->refresh();

    expect($transaction->a_submit_by)->toBeNull()
        ->and($transaction->hasSyncWarningA())->toBeTrue();
});

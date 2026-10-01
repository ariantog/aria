<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\Jubelio\JubelioLegacyCatchupStockRestoreService;

beforeEach(function () {
    $this->user = User::factory()->create();
    config([
        'jubelio_legacy_catchup.transaction_date_before' => '2025-12-31',
        'jubelio_legacy_catchup.created_after' => '2026-06-30 20:39:03',
    ]);

    config(['jubelio_legacy_catchup.cron_user_id' => Transaction::JUBELIO_CRON_USER_ID]);
});

it('renders the jubelio legacy catchup repair page for superadmin', function () {
    $this->actingAs($this->user)
        ->get(route('jubelio-legacy-catchup-repair.index'))
        ->assertOk()
        ->assertSee('Jubelio legacy catch-up repair', false)
        ->assertSee('data-testid="jubelio-catchup-vwh"', false);
});

it('forbids non-superadmin users', function () {
    $other = User::factory()->create();
    expect($other->is_superadmin)->toBeFalse();

    $this->actingAs($other)
        ->get(route('jubelio-legacy-catchup-repair.index'))
        ->assertForbidden();
});

it('creates a move from virtual warehouse to sell sender for problematic jubelio sells', function () {
    config(['jubelio_legacy_catchup.cron_user_id' => $this->user->id]);

    $physical = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Fisik']);
    $virtual = Addrbook::factory()->create(['type' => Addrbook::TYPE_V_WAREHOUSE, 'name' => 'V Jubelio Repair']);
    $customer = Addrbook::factory()->customer()->create();
    $item = Item::factory()->create();

    WarehouseItem::create([
        'warehouse_id' => $physical->id,
        'warehouse_type' => $physical->type,
        'item_id' => $item->id,
        'quantity' => 2,
    ]);

    $sell = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_JUBELIO,
        'user_id' => $this->user->id,
        'date' => '2025-08-01',
        'invoice' => 'SP-LEGACY-BAD-1',
        'sender_id' => $physical->id,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'receiver_id' => $customer->id,
        'receiver_type' => (string) Addrbook::TYPE_CUSTOMER,
        'total' => -100_000,
        'total_items' => 5,
    ]);
    $sell->forceFill(['created_at' => '2026-07-15 10:00:00'])->save();

    $sell->details()->create([
        'item_id' => $item->id,
        'date' => $sell->date,
        'transaction_type' => Transaction::TYPE_SELL,
        'sender_id' => $physical->id,
        'receiver_id' => $customer->id,
        'quantity' => 5,
        'price' => 20_000,
        'discount' => 0,
        'total' => 100_000,
    ]);

    $this->actingAs($this->user)
        ->post(route('jubelio-legacy-catchup-repair.restore'), [
            'virtual_warehouse_id' => $virtual->id,
            'transaction_ids' => [$sell->id],
            'confirm' => '1',
        ])
        ->assertRedirect(route('jubelio-legacy-catchup-repair.index'))
        ->assertSessionHas('success');

    $move = Transaction::query()
        ->where('type', Transaction::TYPE_MOVE)
        ->where('invoice', 'RESTORE-SELL-'.$sell->id)
        ->first();

    expect($move)->not->toBeNull()
        ->and((int) $move->sender_id)->toBe($virtual->id)
        ->and((int) $move->receiver_id)->toBe($physical->id);

    $stock = WarehouseItem::query()
        ->where('warehouse_id', $physical->id)
        ->where('item_id', $item->id)
        ->value('quantity');

    expect((float) $stock)->toBe(7.0);

    $service = app(JubelioLegacyCatchupStockRestoreService::class);
    expect($service->alreadyRestoredSellIds([$sell->id]))->toBe([$sell->id]);
});

<?php

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\ItemInsightMonth;
use App\Models\ItemInsightRanking;
use App\Models\ReadonlyExport;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Models\WarehouseItem;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    foreach (ReadonlyExport::getPermissions() as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
});

function grokRestockUser(array $extraPermissions = []): User
{
    $user = User::factory()->create(['active' => true]);
    if ($user->id === User::SUPERADMIN_ID) {
        $user = User::factory()->create(['active' => true]);
    }
    $user->givePermissionTo(ReadonlyExport::getPermissions()['export']);
    if ($extraPermissions !== []) {
        $user->givePermissionTo($extraPermissions);
    }

    return $user;
}

function grokRestockHealthItem(string $name, string $code, ItemType $type = ItemType::ASSET_LANCAR): Item
{
    return Item::factory()->create([
        'name' => $name,
        'code' => $code,
        'type' => $type,
    ]);
}

function grokRestockHealthStock(Item $item, Addrbook $warehouse, float $qty): void
{
    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'warehouse_type' => Addrbook::TYPE_WAREHOUSE,
        'quantity' => $qty,
    ]);
}

function grokRestockHealthSell(
    User $user,
    Addrbook $warehouse,
    Addrbook $customer,
    Item $item,
    float $qty,
    string $date,
    string $invoice,
): void {
    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => $invoice,
        'date' => $date,
        'status' => Transaction::STATUS_COMPLETED,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'user_id' => $user->id,
    ]);

    TransactionDetail::factory()->create([
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
        'transaction_type' => Transaction::TYPE_SELL,
        'date' => $date,
        'quantity' => $qty,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
    ]);
}

it('registers grok restock readonly routes', function (): void {
    expect(route('api.readonly.restock.context'))->toContain('/api/readonly/restock/context');
    expect(route('api.readonly.restock.insight-period'))->toContain('/api/readonly/restock/insight-period');
});

it('forbids restock context without export-readonly', function (): void {
    $user = User::factory()->create(['active' => true]);
    if ($user->id === User::SUPERADMIN_ID) {
        $user = User::factory()->create(['active' => true]);
    }
    $this->actingAs($user);

    $this->getJson('/api/readonly/restock/context')->assertForbidden();
});

it('returns asset lancar context by default and excludes manufactured items', function (): void {
    $this->travelTo('2026-06-01');
    $user = grokRestockUser();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();

    $asset = grokRestockHealthItem('Grok Asset', 'GROK-ASSET', ItemType::ASSET_LANCAR);
    grokRestockHealthStock($asset, $warehouse, 5);
    grokRestockHealthSell($user, $warehouse, $customer, $asset, 30, now()->subDays(10)->toDateString(), 'GROK-A');

    $manufactured = grokRestockHealthItem('Grok Mfg', 'GROK-MFG', ItemType::ITEM);
    grokRestockHealthStock($manufactured, $warehouse, 5);
    grokRestockHealthSell($user, $warehouse, $customer, $manufactured, 30, now()->subDays(10)->toDateString(), 'GROK-M');

    $response = $this->actingAs($user)->getJson('/api/readonly/restock/context');
    $response->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('item_id');
    expect($ids)->toContain($asset->id)
        ->and($ids)->not->toContain($manufactured->id);

    $assetRow = collect($response->json('data'))->firstWhere('item_id', $asset->id);
    expect($assetRow['sku'])->toBe($asset->code)
        ->and($assetRow)->toHaveKeys(['aria_suggested_qty', 'qty_restock', 'health_key', 'monthly_net']);
    expect($response->json('meta.item_type'))->toBe(ItemType::ASSET_LANCAR->value);
});

it('filters context to best selling rankings when requested', function (): void {
    $this->travelTo('2026-06-01');
    $user = grokRestockUser();
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();

    $ranked = grokRestockHealthItem('Ranked', 'GROK-RANKED', ItemType::ASSET_LANCAR);
    grokRestockHealthStock($ranked, $warehouse, 3);
    grokRestockHealthSell($user, $warehouse, $customer, $ranked, 40, now()->subDays(5)->toDateString(), 'GROK-R1');

    $other = grokRestockHealthItem('Other', 'GROK-OTHER', ItemType::ASSET_LANCAR);
    grokRestockHealthStock($other, $warehouse, 3);
    grokRestockHealthSell($user, $warehouse, $customer, $other, 10, now()->subDays(5)->toDateString(), 'GROK-R2');

    ItemInsightMonth::create([
        'year' => 2026,
        'month' => 5,
        'row_count' => 2,
        'calculated_at' => now(),
    ]);
    ItemInsightRanking::create([
        'year' => 2026,
        'month' => 5,
        'category' => ItemInsightRanking::CATEGORY_BEST_SELLING,
        'rank' => 1,
        'item_id' => $ranked->id,
        'item_name' => $ranked->name,
        'item_code' => $ranked->code,
        'net_qty' => 40,
        'net_value' => 4_000_000,
        'cost_total' => 1_000_000,
        'profit' => 3_000_000,
        'margin_pct' => 75,
        'daily_velocity' => 1.2,
    ]);

    $response = $this->actingAs($user)->getJson('/api/readonly/restock/context?best_selling_only=1');
    $response->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('item_id');
    expect($ids)->toContain($ranked->id)
        ->and($ids)->not->toContain($other->id);
    expect(collect($response->json('data'))->first()['best_selling_rank'])->toBe(1);
});

it('authenticates restock context with bearer token', function (): void {
    $user = grokRestockUser();
    config([
        'readonly_export.bearer_token' => 'grok-restock-secret',
        'readonly_export.username' => $user->username,
    ]);

    $this->withToken('grok-restock-secret')
        ->getJson('/api/readonly/restock/context')
        ->assertSuccessful();
});

it('returns latest insight period metadata', function (): void {
    $user = grokRestockUser();
    ItemInsightMonth::create([
        'year' => 2026,
        'month' => 4,
        'row_count' => 10,
        'calculated_at' => now(),
    ]);

    $this->actingAs($user)
        ->getJson('/api/readonly/restock/insight-period')
        ->assertSuccessful()
        ->assertJsonPath('data.year', 2026)
        ->assertJsonPath('data.month', 4)
        ->assertJsonPath('meta.calculated', true);
});

it('blocks grok apply when server flag is off', function (): void {
    config(['readonly_export.restock_grok_apply_enabled' => false]);
    $user = grokRestockUser(['restock-grok-apply']);
    $this->actingAs($user);

    $this->postJson('/api/readonly/restock/apply', [
        'direction' => 'set_restock',
        'rows' => [['sku' => 'TEST-SKU', 'qty' => 1]],
    ])->assertForbidden();
});

it('forbids grok apply without restock-grok-apply permission', function (): void {
    config(['readonly_export.restock_grok_apply_enabled' => true]);
    $user = grokRestockUser();
    $this->actingAs($user);

    $this->postJson('/api/readonly/restock/apply/preview', [
        'direction' => 'set_restock',
        'rows' => [['sku' => 'TEST-SKU', 'qty' => 1]],
    ])->assertForbidden();
});
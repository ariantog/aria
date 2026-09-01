<?php

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    expect($this->user->is_superadmin)->toBeTrue();
});

function seedItemTransactionLine(Item $item, array $transactionAttrs = [], array $detailAttrs = []): Transaction
{
    $date = $transactionAttrs['date'] ?? now()->toDateString();

    $transaction = Transaction::factory()->create(array_merge([
        'date' => $date,
    ], $transactionAttrs));

    TransactionDetail::factory()->create(array_merge([
        'transaction_id' => $transaction->id,
        'item_id' => $item->id,
        'date' => $date,
        'transaction_type' => $transaction->type,
        'sender_id' => $transaction->sender_id,
        'receiver_id' => $transaction->receiver_id,
    ], $detailAttrs));

    return $transaction;
}

it('lists customer reseller warehouse vwarehouse and supplier as item transaction parties', function () {
    expect(Addrbook::itemTransactionPartyTypes())->toEqual([
        Addrbook::TYPE_CUSTOMER,
        Addrbook::TYPE_RESELLER,
        Addrbook::TYPE_WAREHOUSE,
        Addrbook::TYPE_V_WAREHOUSE,
        Addrbook::TYPE_SUPPLIER,
    ]);
});

it('renders item transaction filters on items and assetlancar pages', function (string $routeName, ItemType $itemType) {
    $item = Item::factory()->create(['type' => $itemType, 'name' => 'Filter SKU']);

    $this->actingAs($this->user)
        ->get(route($routeName, $item))
        ->assertOk()
        ->assertSee('Transaction History', false)
        ->assertSee('data-testid="item-transaction-filters"', false)
        ->assertSee('data-testid="item-tx-from"', false)
        ->assertSee('data-testid="item-tx-to"', false)
        ->assertSee('data-testid="item-tx-invoice"', false)
        ->assertSee('data-testid="item-tx-sender-combobox"', false)
        ->assertSee('data-testid="item-tx-receiver-combobox"', false);
})->with([
    'items' => ['items.transactions', ItemType::ITEM],
    'assetlancar' => ['assetlancar.transactions', ItemType::ASSET_LANCAR],
]);

it('filters item transactions by date range', function () {
    $item = Item::factory()->create();

    seedItemTransactionLine($item, ['date' => '2023-01-01', 'invoice' => 'ITEM-JAN-1']);
    seedItemTransactionLine($item, ['date' => '2023-01-15', 'invoice' => 'ITEM-JAN-15']);
    seedItemTransactionLine($item, ['date' => '2023-02-01', 'invoice' => 'ITEM-FEB-1']);

    $this->actingAs($this->user)
        ->get(route('items.transactions', ['item' => $item, 'from' => '2023-01-01', 'to' => '2023-01-31']))
        ->assertOk()
        ->assertSee('ITEM-JAN-1', false)
        ->assertSee('ITEM-JAN-15', false)
        ->assertDontSee('ITEM-FEB-1', false)
        ->assertSee('value="2023-01-01"', false)
        ->assertSee('value="2023-01-31"', false);
});

it('filters item transactions by invoice substring', function () {
    $item = Item::factory()->create();

    seedItemTransactionLine($item, ['invoice' => 'TRX-999']);
    seedItemTransactionLine($item, ['invoice' => 'TRX-000']);

    $this->actingAs($this->user)
        ->get(route('items.transactions', ['item' => $item, 'invoice' => '999']))
        ->assertOk()
        ->assertSee('TRX-999', false)
        ->assertDontSee('TRX-000', false)
        ->assertSee('value="999"', false);
});

it('filters item transactions by sender', function () {
    $item = Item::factory()->create();
    $senderA = Addrbook::factory()->warehouse()->create(['name' => 'Sender Filter A']);
    $senderB = Addrbook::factory()->warehouse()->create(['name' => 'Sender Filter B']);
    $receiver = Addrbook::factory()->customer()->create();

    seedItemTransactionLine($item, [
        'invoice' => 'SENDER-VISIBLE',
        'sender_id' => $senderA->id,
        'sender_type' => (string) $senderA->type,
        'receiver_id' => $receiver->id,
        'receiver_type' => (string) $receiver->type,
    ]);
    seedItemTransactionLine($item, [
        'invoice' => 'SENDER-HIDDEN',
        'sender_id' => $senderB->id,
        'sender_type' => (string) $senderB->type,
        'receiver_id' => $receiver->id,
        'receiver_type' => (string) $receiver->type,
    ]);

    $this->actingAs($this->user)
        ->get(route('items.transactions', ['item' => $item, 'sender' => $senderA->id]))
        ->assertOk()
        ->assertSee('SENDER-VISIBLE', false)
        ->assertDontSee('SENDER-HIDDEN', false)
        ->assertSee('Sender Filter A', false);
});

it('filters item transactions by receiver', function () {
    $item = Item::factory()->create();
    $sender = Addrbook::factory()->warehouse()->create();
    $receiverA = Addrbook::factory()->customer()->create(['name' => 'Receiver Filter A']);
    $receiverB = Addrbook::factory()->customer()->create(['name' => 'Receiver Filter B']);

    seedItemTransactionLine($item, [
        'invoice' => 'RECEIVER-VISIBLE',
        'sender_id' => $sender->id,
        'sender_type' => (string) $sender->type,
        'receiver_id' => $receiverA->id,
        'receiver_type' => (string) $receiverA->type,
    ]);
    seedItemTransactionLine($item, [
        'invoice' => 'RECEIVER-HIDDEN',
        'sender_id' => $sender->id,
        'sender_type' => (string) $sender->type,
        'receiver_id' => $receiverB->id,
        'receiver_type' => (string) $receiverB->type,
    ]);

    $this->actingAs($this->user)
        ->get(route('items.transactions', ['item' => $item, 'receiver' => $receiverA->id]))
        ->assertOk()
        ->assertSee('RECEIVER-VISIBLE', false)
        ->assertDontSee('RECEIVER-HIDDEN', false)
        ->assertSee('Receiver Filter A', false);
});

it('applies the same filters on assetlancar transactions', function () {
    $item = Item::factory()->create(['type' => ItemType::ASSET_LANCAR]);
    $sender = Addrbook::factory()->supplier()->create(['name' => 'Asset Sender']);
    $receiver = Addrbook::factory()->warehouse()->create(['name' => 'Asset Receiver']);

    seedItemTransactionLine($item, [
        'date' => '2024-03-10',
        'invoice' => 'ASSET-MATCH',
        'sender_id' => $sender->id,
        'sender_type' => (string) $sender->type,
        'receiver_id' => $receiver->id,
        'receiver_type' => (string) $receiver->type,
    ]);
    seedItemTransactionLine($item, [
        'date' => '2024-04-10',
        'invoice' => 'ASSET-OTHER',
        'sender_id' => Addrbook::factory()->supplier()->create()->id,
        'receiver_id' => $receiver->id,
        'receiver_type' => (string) $receiver->type,
    ]);

    $this->actingAs($this->user)
        ->get(route('assetlancar.transactions', [
            'item' => $item,
            'from' => '2024-03-01',
            'to' => '2024-03-31',
            'invoice' => 'MATCH',
            'sender' => $sender->id,
            'receiver' => $receiver->id,
        ]))
        ->assertOk()
        ->assertSee('ASSET-MATCH', false)
        ->assertDontSee('ASSET-OTHER', false)
        ->assertSee('Asset Sender', false)
        ->assertSee('Asset Receiver', false)
        ->assertSee(route('assetlancar.transactions', $item), false);
});

it('does not mix in another item when filters match that other invoice', function () {
    $item = Item::factory()->create();
    $other = Item::factory()->create();

    seedItemTransactionLine($item, ['invoice' => 'OWN-INV']);
    seedItemTransactionLine($other, ['invoice' => 'OTHER-INV']);

    $this->actingAs($this->user)
        ->get(route('items.transactions', ['item' => $item, 'invoice' => 'INV']))
        ->assertOk()
        ->assertSee('OWN-INV', false)
        ->assertDontSee('OTHER-INV', false);
});

it('shows the filtered empty state when nothing matches', function () {
    $item = Item::factory()->create();
    seedItemTransactionLine($item, ['invoice' => 'TRX-001']);

    $this->actingAs($this->user)
        ->get(route('items.transactions', ['item' => $item, 'invoice' => 'NO-SUCH-INVOICE']))
        ->assertOk()
        ->assertDontSee('TRX-001', false)
        ->assertSee('No transactions match these filters.', false);
});

it('returns only inventory parties from item party lookup', function () {
    $customer = Addrbook::factory()->customer()->create(['name' => 'Party Customer Match']);
    $reseller = Addrbook::create(['name' => 'Party Reseller Match', 'type' => Addrbook::TYPE_RESELLER]);
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Party Warehouse Match']);
    $vwarehouse = Addrbook::create(['name' => 'Party Vwarehouse Match', 'type' => Addrbook::TYPE_V_WAREHOUSE]);
    $supplier = Addrbook::factory()->supplier()->create(['name' => 'Party Supplier Match']);
    Addrbook::create(['name' => 'Party Bank Match', 'type' => Addrbook::TYPE_BANK]);
    Addrbook::create(['name' => 'Party Account Match', 'type' => Addrbook::TYPE_ACCOUNT]);
    Addrbook::create(['name' => 'Party Vaccount Match', 'type' => Addrbook::TYPE_V_ACCOUNT]);

    $names = collect(
        $this->actingAs($this->user)
            ->getJson(route('items.party-lookup', ['search' => 'Party']))
            ->assertOk()
            ->json()
    )->pluck('name');

    expect($names)
        ->toContain($customer->name)
        ->toContain($reseller->name)
        ->toContain($warehouse->name)
        ->toContain($vwarehouse->name)
        ->toContain($supplier->name)
        ->not->toContain('Party Bank Match')
        ->not->toContain('Party Account Match')
        ->not->toContain('Party Vaccount Match');
});

it('returns no party lookup results until the search term is longer than two characters', function () {
    Addrbook::factory()->customer()->create(['name' => 'Zeta Customer']);

    $this->actingAs($this->user)
        ->getJson(route('items.party-lookup'))
        ->assertOk()
        ->assertExactJson([]);

    $this->actingAs($this->user)
        ->getJson(route('items.party-lookup', ['search' => 'Ze']))
        ->assertOk()
        ->assertExactJson([]);

    $this->actingAs($this->user)
        ->getJson(route('items.party-lookup', ['search' => 'Zet']))
        ->assertOk()
        ->assertJsonCount(1);
});

it('allows item viewers to use party lookup', function () {
    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'items-list']);
    $user->givePermissionTo('items-list');

    Addrbook::factory()->warehouse()->create(['name' => 'Lookup Warehouse Alpha']);

    $this->actingAs($user)
        ->getJson(route('items.party-lookup', ['search' => 'Alpha']))
        ->assertOk()
        ->assertJsonFragment(['name' => 'Lookup Warehouse Alpha']);
});

it('allows asset lancar viewers to use party lookup', function () {
    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'assetLancar-list']);
    $user->givePermissionTo('assetLancar-list');

    Addrbook::factory()->supplier()->create(['name' => 'Lookup Supplier Alpha']);

    $this->actingAs($user)
        ->getJson(route('items.party-lookup', ['search' => 'Alpha']))
        ->assertOk()
        ->assertJsonFragment(['name' => 'Lookup Supplier Alpha']);
});

it('forbids party lookup without item or asset permissions', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('items.party-lookup', ['search' => 'Alpha']))
        ->assertForbidden();
});

<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Jubelio\JubelioTransactionSyncPresenter;
use Spatie\Permission\Models\Permission;

function seedJubelioSyncForWarehouse(Addrbook $warehouse): Jubeliosync
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

function seedTransactionShowUser(): User
{
    User::factory()->create();
    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'transactions-show']);
    $user->givePermissionTo('transactions-show');

    return $user;
}

function seedItemTransactionDetail(Transaction $transaction, Item $item): void
{
    $transaction->details()->create([
        'date' => $transaction->date,
        'transaction_type' => (int) $transaction->type,
        'sender_id' => $transaction->sender_id,
        'receiver_id' => $transaction->receiver_id,
        'item_id' => $item->id,
        'quantity' => 2,
        'price' => 1000,
        'discount' => 0,
        'total' => 2000,
    ]);
}

beforeEach(function () {
    config(['services.jubelio.active' => true]);
});

describe('jubelio sync button counts on transaction show', function () {
    it('shows one push button for sell when sender warehouse is mapped', function () {
        $user = seedTransactionShowUser();
        $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'WH Sell']);
        $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
        $item = Item::factory()->create(['jubelio_item_id' => 101]);
        seedJubelioSyncForWarehouse($warehouse);

        $transaction = Transaction::factory()->create([
            'type' => Transaction::TYPE_SELL,
            'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
            'sender_id' => $warehouse->id,
            'receiver_id' => $customer->id,
        ]);
        seedItemTransactionDetail($transaction, $item);

        $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertSuccessful()
            ->assertSee('Sinkron Jubelio', false)
            ->assertSee('Push to Jubelio — WH Sell', false)
            ->assertSee('Push to Jubelio', false);
    });

    it('shows no push buttons for sell when sender warehouse is not mapped', function () {
        $user = seedTransactionShowUser();
        $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'WH Unmapped']);
        $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
        $item = Item::factory()->create(['jubelio_item_id' => 102]);

        $transaction = Transaction::factory()->create([
            'type' => Transaction::TYPE_SELL,
            'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
            'sender_id' => $warehouse->id,
            'receiver_id' => $customer->id,
        ]);
        seedItemTransactionDetail($transaction, $item);

        $html = $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertSuccessful()
            ->getContent();

        expect($html)->not->toContain('Sinkron Jubelio')
            ->and(substr_count($html, 'Push to Jubelio'))->toBe(0);
    });

    it('shows one push button for buy when receiver warehouse is mapped', function () {
        $user = seedTransactionShowUser();
        $supplier = Addrbook::factory()->supplier()->create();
        $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'WH Buy']);
        $item = Item::factory()->create(['jubelio_item_id' => 103]);
        seedJubelioSyncForWarehouse($warehouse);

        $transaction = Transaction::factory()->create([
            'type' => Transaction::TYPE_BUY,
            'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
            'sender_id' => $supplier->id,
            'receiver_id' => $warehouse->id,
        ]);
        seedItemTransactionDetail($transaction, $item);

        $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertSuccessful()
            ->assertSee('Push to Jubelio — WH Buy', false);
    });

    it('shows one push button for return when receiver warehouse is mapped', function () {
        $user = seedTransactionShowUser();
        $customer = Addrbook::factory()->create(['type' => Addrbook::TYPE_CUSTOMER]);
        $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'WH Return']);
        $item = Item::factory()->create(['jubelio_item_id' => 104]);
        seedJubelioSyncForWarehouse($warehouse);

        $transaction = Transaction::factory()->create([
            'type' => Transaction::TYPE_RETURN,
            'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
            'sender_id' => $customer->id,
            'receiver_id' => $warehouse->id,
        ]);
        seedItemTransactionDetail($transaction, $item);

        $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertSuccessful()
            ->assertSee('Push to Jubelio — WH Return', false);
    });

    it('shows one push button for return-supplier when sender warehouse is mapped', function () {
        $user = seedTransactionShowUser();
        $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'WH RetSup']);
        $supplier = Addrbook::factory()->supplier()->create();
        $item = Item::factory()->create(['jubelio_item_id' => 105]);
        seedJubelioSyncForWarehouse($warehouse);

        $transaction = Transaction::factory()->create([
            'type' => Transaction::TYPE_RETURN_SUPPLIER,
            'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
            'sender_id' => $warehouse->id,
            'receiver_id' => $supplier->id,
        ]);
        seedItemTransactionDetail($transaction, $item);

        $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertSuccessful()
            ->assertSee('Push to Jubelio — WH RetSup', false);
    });

    it('shows two push buttons for move when both warehouses are mapped', function () {
        $user = seedTransactionShowUser();
        $sender = Addrbook::factory()->warehouse()->create(['name' => 'WH Move A']);
        $receiver = Addrbook::factory()->warehouse()->create(['name' => 'WH Move B']);
        $item = Item::factory()->create(['jubelio_item_id' => 106]);
        seedJubelioSyncForWarehouse($sender);
        seedJubelioSyncForWarehouse($receiver);

        $transaction = Transaction::factory()->create([
            'type' => Transaction::TYPE_MOVE,
            'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
        ]);
        seedItemTransactionDetail($transaction, $item);

        $html = $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertSuccessful()
            ->getContent();

        expect(substr_count($html, 'Push to Jubelio — WH Move A'))->toBe(1)
            ->and(substr_count($html, 'Push to Jubelio — WH Move B'))->toBe(1);
    });

    it('shows one push button for move when only sender warehouse is mapped', function () {
        $user = seedTransactionShowUser();
        $sender = Addrbook::factory()->warehouse()->create(['name' => 'WH Move Sender']);
        $receiver = Addrbook::factory()->warehouse()->create(['name' => 'WH Move Plain']);
        $item = Item::factory()->create(['jubelio_item_id' => 107]);
        seedJubelioSyncForWarehouse($sender);

        $transaction = Transaction::factory()->create([
            'type' => Transaction::TYPE_MOVE,
            'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
        ]);
        seedItemTransactionDetail($transaction, $item);

        $html = $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertSuccessful()
            ->getContent();

        expect(substr_count($html, 'Push to Jubelio — WH Move Sender'))->toBe(1)
            ->and(substr_count($html, 'Push to Jubelio — WH Move Plain'))->toBe(0);
    });

    it('shows one push button for move when only receiver warehouse is mapped', function () {
        $user = seedTransactionShowUser();
        $sender = Addrbook::factory()->warehouse()->create(['name' => 'WH Move Plain 2']);
        $receiver = Addrbook::factory()->warehouse()->create(['name' => 'WH Move Receiver']);
        $item = Item::factory()->create(['jubelio_item_id' => 108]);
        seedJubelioSyncForWarehouse($receiver);

        $transaction = Transaction::factory()->create([
            'type' => Transaction::TYPE_MOVE,
            'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
        ]);
        seedItemTransactionDetail($transaction, $item);

        $html = $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertSuccessful()
            ->getContent();

        expect(substr_count($html, 'Push to Jubelio — WH Move Receiver'))->toBe(1)
            ->and(substr_count($html, 'Push to Jubelio — WH Move Plain 2'))->toBe(0);
    });

    it('shows no push buttons for move when neither warehouse is mapped', function () {
        $user = seedTransactionShowUser();
        $sender = Addrbook::factory()->warehouse()->create(['name' => 'WH A']);
        $receiver = Addrbook::factory()->warehouse()->create(['name' => 'WH B']);
        $item = Item::factory()->create(['jubelio_item_id' => 109]);

        $transaction = Transaction::factory()->create([
            'type' => Transaction::TYPE_MOVE,
            'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
        ]);
        seedItemTransactionDetail($transaction, $item);

        $html = $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertSuccessful()
            ->getContent();

        expect($html)->not->toContain('Sinkron Jubelio')
            ->and(substr_count($html, 'Push to Jubelio'))->toBe(0);
    });
});

it('normalizes string warehouse ids from mysql pluck when comparing parties', function () {
    $presenter = app(JubelioTransactionSyncPresenter::class);
    $sender = Addrbook::factory()->warehouse()->create();
    $receiver = Addrbook::factory()->warehouse()->create();

    Jubeliosync::create([
        'jubelio_store_id' => 1,
        'jubelio_store_name' => 'Store',
        'jubelio_location_id' => 11,
        'jubelio_location_name' => 'Loc',
        'warehouse_id' => $sender->id,
        'customer_id' => 1,
        'bin_id' => 0,
    ]);

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_MOVE,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);

    $presented = $presenter->present($transaction, $presenter->syncMap());

    expect($presented['sync_cek'])->toBe('S')
        ->and($presented['adjust_type_a'])->toBe(2)
        ->and($presented['jubelio_a'])->toBe('Loc');
});

it('shows two push buttons on jubelio detail sync for dual-mapped move', function () {
    $user = seedTransactionShowUser();
    $sender = Addrbook::factory()->warehouse()->create(['name' => 'WH Detail A']);
    $receiver = Addrbook::factory()->warehouse()->create(['name' => 'WH Detail B']);
    $item = Item::factory()->create(['jubelio_item_id' => 200]);
    seedJubelioSyncForWarehouse($sender);
    seedJubelioSyncForWarehouse($receiver);

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_MOVE,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);
    seedItemTransactionDetail($transaction, $item);

    $html = $this->actingAs($user)
        ->get(route('jubelio.transaction.detail-sync', $transaction))
        ->assertSuccessful()
        ->assertDontSee('Transaksi Otomatis', false)
        ->assertSee('Sender (Side A)', false)
        ->assertSee('Receiver (Side B)', false)
        ->getContent();

    expect(substr_count($html, 'Push to Jubelio'))->toBe(3);
});

it('shows no sync cards on jubelio detail sync when move warehouses are unmapped', function () {
    $user = seedTransactionShowUser();
    $sender = Addrbook::factory()->warehouse()->create();
    $receiver = Addrbook::factory()->warehouse()->create();
    $item = Item::factory()->create(['jubelio_item_id' => 201]);

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_MOVE,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);
    seedItemTransactionDetail($transaction, $item);

    $this->actingAs($user)
        ->get(route('jubelio.transaction.detail-sync', $transaction))
        ->assertSuccessful()
        ->assertDontSee('Sender (Side A)', false)
        ->assertDontSee('Receiver (Side B)', false)
        ->assertDontSee('Push to Jubelio</button>', false);
});

it('shows move sync for superadmin when sender warehouse is mapped', function () {
    $superadmin = User::factory()->create(['id' => 1]);
    $sender = Addrbook::factory()->warehouse()->create(['name' => 'WH Super Move']);
    $receiver = Addrbook::factory()->warehouse()->create(['name' => 'WH Plain']);
    $item = Item::factory()->create(['jubelio_item_id' => 301]);
    seedJubelioSyncForWarehouse($sender);

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_MOVE,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);
    seedItemTransactionDetail($transaction, $item);

    expect($superadmin->is_superadmin)->toBeTrue();

    $this->actingAs($superadmin)
        ->get(route('transactions.show', $transaction))
        ->assertSuccessful()
        ->assertSee('Sinkron Jubelio', false)
        ->assertSee('Push to Jubelio — WH Super Move', false);
});

it('does not show move sync when submit_type is not L10 aria submit (1)', function () {
    $user = seedTransactionShowUser();
    $sender = Addrbook::factory()->warehouse()->create(['name' => 'WH Legacy Submit']);
    $receiver = Addrbook::factory()->warehouse()->create();
    $item = Item::factory()->create(['jubelio_item_id' => 302]);
    seedJubelioSyncForWarehouse($sender);

    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_MOVE,
        'submit_type' => 0,
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);
    seedItemTransactionDetail($transaction, $item);

    $html = $this->actingAs($user)
        ->get(route('transactions.show', $transaction))
        ->assertSuccessful()
        ->getContent();

    expect($html)->not->toContain('Sinkron Jubelio')
        ->and(substr_count($html, 'Push to Jubelio'))->toBe(0);
});

it('defaults submit_type to L10 aria submit on create when omitted', function () {
    $transaction = Transaction::factory()->create([
        'type' => Transaction::TYPE_MOVE,
        'submit_type' => null,
    ]);

    expect($transaction->fresh()->submit_type)->toBe(Transaction::SUBMIT_TYPE_MANUAL)
        ->and($transaction->isManual())->toBeTrue();
});

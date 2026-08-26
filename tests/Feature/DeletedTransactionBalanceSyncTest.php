<?php

use App\Models\Addrbook;
use App\Models\AddrbookStat;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
});

function latestRunningBalanceFor(Addrbook $addrbook): ?float
{
    $entityType = (int) $addrbook->type;

    $transaction = Transaction::query()
        ->where(function ($q) use ($addrbook, $entityType) {
            $q->where(function ($q2) use ($addrbook, $entityType) {
                $q2->where('sender_id', $addrbook->id)
                    ->where('sender_type', $entityType);
            })->orWhere(function ($q2) use ($addrbook, $entityType) {
                $q2->where('receiver_id', $addrbook->id)
                    ->where('receiver_type', $entityType);
            });
        })
        ->orderByDesc('date')
        ->orderByDesc('id')
        ->first();

    if (! $transaction) {
        return 0.0;
    }

    if ((int) $transaction->sender_id === $addrbook->id && (int) $transaction->sender_type === $entityType) {
        return (float) $transaction->sender_balance;
    }

    return (float) $transaction->receiver_balance;
}

function expectStatMatchesLatestRunningBalance(Addrbook $addrbook): void
{
    $statBalance = (float) AddrbookStat::where('customer_id', $addrbook->id)->value('balance');
    expect($statBalance)->toBe(latestRunningBalanceFor($addrbook));
}

test('deleting latest bank transfer keeps customerstat aligned with latest running balance', function () {
    $bankSource = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $bankDest = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);

    $this->post(route('transactions.transfer.store'), [
        'date' => now()->subDay()->format('Y-m-d'),
        'sender' => $bankSource->id,
        'receiver' => $bankDest->id,
        'total' => 5000,
        'invoice' => 'TRF-OLDER',
    ])->assertRedirect();

    $olderTransfer = Transaction::latest('id')->first();

    $this->post(route('transactions.transfer.store'), [
        'date' => now()->format('Y-m-d'),
        'sender' => $bankSource->id,
        'receiver' => $bankDest->id,
        'total' => 1500,
        'invoice' => 'TRF-LATEST',
    ])->assertRedirect();

    $latestTransfer = Transaction::latest('id')->first();

    expectStatMatchesLatestRunningBalance($bankSource);
    expectStatMatchesLatestRunningBalance($bankDest);

    $this->delete(route('transactions.destroy', $latestTransfer))
        ->assertRedirect(route('transactions.index'));

    expect(Transaction::find($latestTransfer->id))->toBeNull();
    expectStatMatchesLatestRunningBalance($bankSource);
    expectStatMatchesLatestRunningBalance($bankDest);
});

test('deleting middle bank transfer keeps customerstat aligned with latest running balance', function () {
    $bankSource = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $bankDest = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);

    $this->post(route('transactions.transfer.store'), [
        'date' => now()->subDays(2)->format('Y-m-d'),
        'sender' => $bankSource->id,
        'receiver' => $bankDest->id,
        'total' => 1000,
    ])->assertRedirect();

    $this->post(route('transactions.transfer.store'), [
        'date' => now()->subDay()->format('Y-m-d'),
        'sender' => $bankSource->id,
        'receiver' => $bankDest->id,
        'total' => 2000,
    ])->assertRedirect();

    $middleTransfer = Transaction::orderBy('date')->orderBy('id')->skip(1)->first();

    $this->post(route('transactions.transfer.store'), [
        'date' => now()->format('Y-m-d'),
        'sender' => $bankSource->id,
        'receiver' => $bankDest->id,
        'total' => 500,
    ])->assertRedirect();

    $this->delete(route('transactions.destroy', $middleTransfer))
        ->assertRedirect(route('transactions.index'));

    expectStatMatchesLatestRunningBalance($bankSource);
    expectStatMatchesLatestRunningBalance($bankDest);
});

test('deleting legacy-signed transfer keeps customerstat aligned with latest running balance', function () {
    $bankSource = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $bankDest = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);

    $this->post(route('transactions.transfer.store'), [
        'date' => now()->format('Y-m-d'),
        'sender' => $bankSource->id,
        'receiver' => $bankDest->id,
        'total' => 15000000,
    ])->assertRedirect();

    $transfer = Transaction::latest('id')->first();

    DB::table('transactions')->where('id', $transfer->id)->update([
        'total' => 15000000,
        'real_total' => 15000000,
    ]);

    $this->delete(route('transactions.destroy', $transfer))
        ->assertRedirect(route('transactions.index'));

    expectStatMatchesLatestRunningBalance($bankSource);
    expectStatMatchesLatestRunningBalance($bankDest);
});

test('deleting transfer created with legacy positive total keeps customerstat aligned', function () {
    $bankSource = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);
    $bankDest = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);

    $transfer = Transaction::factory()->create([
        'type' => Transaction::TYPE_TRANSFER,
        'date' => now()->format('Y-m-d'),
        'sender_id' => $bankSource->id,
        'sender_type' => $bankSource->type,
        'receiver_id' => $bankDest->id,
        'receiver_type' => $bankDest->type,
        'total' => 15000000,
        'real_total' => 15000000,
        'status' => Transaction::STATUS_COMPLETED,
    ]);

    app(\App\Services\TransactionService::class)->handleTransaction($transfer);

    $this->delete(route('transactions.destroy', $transfer))
        ->assertRedirect(route('transactions.index'));

    expectStatMatchesLatestRunningBalance($bankSource);
    expectStatMatchesLatestRunningBalance($bankDest);
});

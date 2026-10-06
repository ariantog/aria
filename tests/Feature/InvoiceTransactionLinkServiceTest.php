<?php

use App\Models\Addrbook;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InvoiceTransactionLinkService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->service = app(InvoiceTransactionLinkService::class);
    $this->invoice = 'INV/LINK/FORMULA/1';
});

it('treats linking as complete when cash-in plus return equals sell plus cash-out', function () {
    $warehouse = Addrbook::factory()->warehouse()->create();
    $customer = Addrbook::factory()->customer()->create();
    $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);

    Transaction::factory()->create([
        'type' => Transaction::TYPE_SELL,
        'invoice' => $this->invoice,
        'sender_id' => $warehouse->id,
        'receiver_id' => $customer->id,
        'total' => -800_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
    ]);

    Transaction::factory()->create([
        'type' => Transaction::TYPE_CASH_OUT,
        'invoice' => $this->invoice,
        'sender_id' => $bank->id,
        'receiver_id' => $customer->id,
        'total' => -200_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
    ]);

    Transaction::factory()->create([
        'type' => Transaction::TYPE_CASH_IN,
        'invoice' => $this->invoice,
        'sender_id' => $customer->id,
        'receiver_id' => $bank->id,
        'total' => 600_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
    ]);

    Transaction::factory()->create([
        'type' => Transaction::TYPE_RETURN,
        'invoice' => $this->invoice,
        'sender_id' => $customer->id,
        'receiver_id' => $warehouse->id,
        'total' => 400_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
    ]);

    $totals = $this->service->totalsForInvoice($this->invoice);

    expect($totals['credit'])->toBe(1_000_000.0)
        ->and($totals['debit'])->toBe(1_000_000.0)
        ->and($totals['is_complete'])->toBeTrue();
});

it('sums multiple transactions of the same type on one invoice number', function () {
    $customer = Addrbook::factory()->customer()->create();
    $bank = Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK]);

    Transaction::factory()->create([
        'type' => Transaction::TYPE_CASH_IN,
        'invoice' => $this->invoice,
        'sender_id' => $customer->id,
        'receiver_id' => $bank->id,
        'total' => 300_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
    ]);

    Transaction::factory()->create([
        'type' => Transaction::TYPE_CASH_IN,
        'invoice' => $this->invoice,
        'sender_id' => $customer->id,
        'receiver_id' => $bank->id,
        'total' => 200_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $this->user->id,
    ]);

    expect($this->service->totalsForInvoice($this->invoice)['cash_in'])->toBe(500_000.0);
});

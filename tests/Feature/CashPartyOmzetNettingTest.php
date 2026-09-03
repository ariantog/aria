<?php

use App\Models\Addrbook;
use App\Models\ReportingEntity;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Reporting\CashPartyOmzetNetting;
use Illuminate\Support\Facades\Artisan;

it('nets cash out to the same party when computing pph final omzet', function () {
    $entity = ReportingEntity::create(['name' => 'Pribadi Konsinyasi', 'slug' => 'pribadi-konsinyasi', 'is_pkp' => false]);
    $bank = Addrbook::create(['name' => 'BCA Konsinyasi', 'type' => Addrbook::TYPE_BANK]);
    $entity->banks()->attach($bank->id, ['is_active' => true]);
    $customer = Addrbook::factory()->customer()->create(['name' => 'Pemilik Barang']);

    $userId = User::factory()->create()->id;

    Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2025-08-05',
        'type' => Transaction::TYPE_CASH_IN,
        'sender_type' => Addrbook::TYPE_CUSTOMER,
        'sender_id' => $customer->id,
        'receiver_type' => Addrbook::TYPE_BANK,
        'receiver_id' => $bank->id,
        'invoice' => 'CIN-CONSIGN-350',
        'total' => 350_000,
        'real_total' => 350_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $userId,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2025-08-06',
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_type' => Addrbook::TYPE_BANK,
        'sender_id' => $bank->id,
        'receiver_type' => Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $customer->id,
        'invoice' => 'COUT-CONSIGN-250',
        'total' => -250_000,
        'real_total' => -250_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $userId,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    Artisan::call('reporting:rebuild-summaries');

    $netting = app(CashPartyOmzetNetting::class);
    $rows = $netting->netRows(2025, 8, [$entity->id]);

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['cash_in_gross'])->toBe(350_000.0)
        ->and($rows->first()['cash_out_gross'])->toBe(250_000.0)
        ->and($rows->first()['net_omzet'])->toBe(100_000.0)
        ->and($rows->first()['pph_final'])->toBe(500.0)
        ->and($netting->totalPphFinal(2025, 8, [$entity->id]))->toBe(500.0);

    $summary = \App\Models\ReportingMonthlyTaxSummary::query()
        ->where('reporting_entity_id', $entity->id)
        ->where('year', 2025)
        ->where('month', 8)
        ->first();

    expect($summary)->not->toBeNull()
        ->and((float) $summary->pph_final)->toBe(500.0);
});

it('does not net cash out to a different party', function () {
    $entity = ReportingEntity::create(['name' => 'Pribadi Net', 'slug' => 'pribadi-net', 'is_pkp' => false]);
    $bank = Addrbook::create(['name' => 'BCA Net', 'type' => Addrbook::TYPE_BANK]);
    $entity->banks()->attach($bank->id, ['is_active' => true]);
    $customerA = Addrbook::factory()->customer()->create(['name' => 'Customer A']);
    $customerB = Addrbook::factory()->customer()->create(['name' => 'Customer B']);
    $userId = User::factory()->create()->id;

    Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2025-09-01',
        'type' => Transaction::TYPE_CASH_IN,
        'sender_type' => Addrbook::TYPE_CUSTOMER,
        'sender_id' => $customerA->id,
        'receiver_type' => Addrbook::TYPE_BANK,
        'receiver_id' => $bank->id,
        'total' => 350_000,
        'real_total' => 350_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $userId,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2025-09-02',
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_type' => Addrbook::TYPE_BANK,
        'sender_id' => $bank->id,
        'receiver_type' => Addrbook::TYPE_CUSTOMER,
        'receiver_id' => $customerB->id,
        'total' => -250_000,
        'real_total' => -250_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $userId,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    Artisan::call('reporting:rebuild-summaries');

    $rows = app(CashPartyOmzetNetting::class)->netRows(2025, 9, [$entity->id]);

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['net_omzet'])->toBe(350_000.0)
        ->and($rows->first()['pph_final'])->toBe(1_750.0);
});

it('does not net cash out to expense ledgers', function () {
    $entity = ReportingEntity::create(['name' => 'Pribadi Ledger', 'slug' => 'pribadi-ledger', 'is_pkp' => false]);
    $bank = Addrbook::create(['name' => 'BCA Ledger', 'type' => Addrbook::TYPE_BANK]);
    $entity->banks()->attach($bank->id, ['is_active' => true]);
    $customer = Addrbook::factory()->customer()->create(['name' => 'Customer Ledger']);
    $ledger = Addrbook::create(['name' => 'Biaya Umum', 'type' => Addrbook::TYPE_ACCOUNT]);
    $userId = User::factory()->create()->id;

    Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2025-10-01',
        'type' => Transaction::TYPE_CASH_IN,
        'sender_type' => Addrbook::TYPE_CUSTOMER,
        'sender_id' => $customer->id,
        'receiver_type' => Addrbook::TYPE_BANK,
        'receiver_id' => $bank->id,
        'total' => 350_000,
        'real_total' => 350_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $userId,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    Transaction::withoutEvents(fn () => Transaction::create([
        'date' => '2025-10-02',
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_type' => Addrbook::TYPE_BANK,
        'sender_id' => $bank->id,
        'receiver_type' => Addrbook::TYPE_ACCOUNT,
        'receiver_id' => $ledger->id,
        'total' => -250_000,
        'real_total' => -250_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => $userId,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ]));

    Artisan::call('reporting:rebuild-summaries');

    $rows = app(CashPartyOmzetNetting::class)->netRows(2025, 10, [$entity->id]);

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['net_omzet'])->toBe(350_000.0);
});

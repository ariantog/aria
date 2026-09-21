<?php

use App\Enums\ReportingLedgerRole;
use App\Support\NewDomainChartOfAccounts;
use App\Support\ProductionLedgerCopy;

it('gives every catalogued production ledger a non-empty description', function () {
    foreach (ProductionLedgerCopy::copy() as $id => $row) {
        expect($row['description'] ?? '')->not->toBeEmpty("ledger {$id} is missing description");
    }
});

it('includes locked soft-deletes from the simplification plan', function () {
    expect(ProductionLedgerCopy::softDeleteIds())
        ->toContain(817)
        ->toContain(1644)
        ->toContain(2731)
        ->toContain(2805)
        ->toContain(2806)
        ->toContain(2808)
        ->toContain(2809)
        ->not->toContain(830)
        ->not->toContain(2938);
});

it('keeps entity tax ledgers active for cash out', function () {
    $keep = ProductionLedgerCopy::cashOutTaxLedgerIds();
    $deleted = ProductionLedgerCopy::softDeleteIds();
    $copy = ProductionLedgerCopy::copy();
    $roles = ProductionLedgerCopy::roles();

    expect($keep)->toContain(2802)
        ->and($keep)->toContain(2818)
        ->and($keep)->toContain(2106)
        ->and($keep)->toContain(2797)
        ->and($keep)->toContain(2849)
        ->and($keep)->toContain(2861)
        ->and($keep)->toContain(2862)
        ->and($keep)->toContain(2863)
        ->and($keep)->toContain(2865)
        ->and($keep)->toContain(2883)
        ->and($keep)->toContain(2884)
        ->and($keep)->toContain(2885)
        ->and($keep)->toContain(2896)
        ->and($keep)->toContain(2941)
        ->and($keep)->toContain(2944)
        ->and(array_intersect($keep, $deleted))->toBeEmpty();

    foreach ($keep as $id) {
        expect($copy[$id]['description'] ?? '')->not->toBeEmpty("ledger {$id} is missing cash-out copy");
        if ($id === 2818) {
            expect($roles[$id] ?? null)->toBeNull();

            continue;
        }
        expect($roles[$id] ?? null)->toBe(ReportingLedgerRole::TaxPayment->value);
    }
});

it('matches typical chart names for new-domain copy fallback', function () {
    $row = ProductionLedgerCopy::rowFromTypicalName('Biaya Shopee');

    expect($row)->not->toBeNull()
        ->and($row['description'])->toBe(NewDomainChartOfAccounts::ledgerByName('Biaya Shopee')['description'])
        ->and($row['hint'])->toBe(NewDomainChartOfAccounts::ledgerByName('Biaya Shopee')['hint']);
});

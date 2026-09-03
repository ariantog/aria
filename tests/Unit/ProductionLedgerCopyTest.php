<?php

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
        ->not->toContain(830)
        ->not->toContain(2938);
});

it('matches typical chart names for new-domain copy fallback', function () {
    $row = ProductionLedgerCopy::rowFromTypicalName('Biaya Shopee');

    expect($row)->not->toBeNull()
        ->and($row['description'])->toBe(NewDomainChartOfAccounts::ledgerByName('Biaya Shopee')['description'])
        ->and($row['hint'])->toBe(NewDomainChartOfAccounts::ledgerByName('Biaya Shopee')['hint']);
});

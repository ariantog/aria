<?php

use App\Services\LegacyAclMapper;

it('maps transaction ACL rows to new permissions', function () {
    $mapper = new LegacyAclMapper;

    expect($mapper->map(LegacyAclMapper::TRANSACTIONS, 'index'))->toBe(['transactions-list'])
        ->and($mapper->map(LegacyAclMapper::TRANSACTIONS, 'sell'))->toBe(['transactions-type-sell'])
        ->and($mapper->map(LegacyAclMapper::TRANSACTIONS, 'cash-in'))->toBe(['transactions-type-cash-in'])
        ->and($mapper->map(LegacyAclMapper::TRANSACTIONS, 'detail'))->toBe(['transactions-show', 'transactions-transaction-sync']);
});

it('maps printer transaction ACL to transaction sync permissions', function () {
    $mapper = new LegacyAclMapper;

    expect($mapper->map(LegacyAclMapper::PRINTER, 'transaction'))->toBe([
        'transactions-transaction-sync',
        'transactions-show',
    ]);
});

it('maps customer addrbook ACL rows to new permissions', function () {
    $mapper = new LegacyAclMapper;

    expect($mapper->map(LegacyAclMapper::CUSTOMERS, 'index'))->toBe(['addrbook-customer-list'])
        ->and($mapper->map(LegacyAclMapper::CUSTOMERS, 'create'))->toBe(['addrbook-customer-create']);
});

it('maps hide balance ACL to bank hidden balance permission', function () {
    $mapper = new LegacyAclMapper;

    expect($mapper->map(LegacyAclMapper::HIDE, 'balance'))->toBe(['addrbook-bank-account-hidden-balance']);
});

it('returns empty array for unknown legacy ACL rows', function () {
    $mapper = new LegacyAclMapper;

    expect($mapper->map(9999, 'index'))->toBe([]);
});

it('maps asset tetap ACL to dedicated permissions', function () {
    $mapper = new LegacyAclMapper;

    expect($mapper->map(LegacyAclMapper::ASSET_TETAP, 'index'))->toBe(['assetTetap-list'])
        ->and($mapper->map(LegacyAclMapper::ASSET_TETAP, 'create'))->toBe(['assetTetap-create']);
});

it('maps legacy cron runner settings ACL to cron manager permissions', function () {
    $mapper = new LegacyAclMapper;

    expect($mapper->map(LegacyAclMapper::SETTINGS, 'cron-runner'))->toBe([
        'setting-cron-manager-view',
        'setting-cron-manager-edit',
    ]);
});

it('maps removed legacy report ACL rows to the replacement reports', function () {
    $mapper = new LegacyAclMapper;

    expect($mapper->map(LegacyAclMapper::REPORTS, 'cash'))->toBe(['report-nett-cash'])
        ->and($mapper->map(LegacyAclMapper::REPORTS, 'cash-flow'))->toBe(['report-laba-rugi'])
        ->and($mapper->map(LegacyAclMapper::REPORTS, 'profit-loss'))->toBe(['report-laba-rugi'])
        ->and($mapper->map(LegacyAclMapper::REPORTS, 'revenue'))->toBe(['report-laba-rugi']);
});

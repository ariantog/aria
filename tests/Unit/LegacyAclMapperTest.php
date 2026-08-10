<?php

use App\Services\LegacyAclMapper;

it('maps transaction ACL rows to new permissions', function () {
    $mapper = new LegacyAclMapper;

    expect($mapper->map(LegacyAclMapper::TRANSACTIONS, 'index'))->toBe(['transactions-list'])
        ->and($mapper->map(LegacyAclMapper::TRANSACTIONS, 'sell'))->toBe(['transactions-type-sell'])
        ->and($mapper->map(LegacyAclMapper::TRANSACTIONS, 'cash-in'))->toBe(['transactions-type-cash-in']);
});

it('maps customer addrbook ACL rows to new permissions', function () {
    $mapper = new LegacyAclMapper;

    expect($mapper->map(LegacyAclMapper::CUSTOMERS, 'index'))->toContain('addrbook-customer-list', 'addrbook-list')
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

it('maps legacy cron runner settings ACL to cron manager permissions', function () {
    $mapper = new LegacyAclMapper;

    expect($mapper->map(LegacyAclMapper::SETTINGS, 'cron-runner'))->toBe([
        'setting-cron-manager-view',
        'setting-cron-manager-edit',
    ]);
});

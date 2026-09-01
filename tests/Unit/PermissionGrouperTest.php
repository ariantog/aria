<?php

use App\Support\PermissionGrouper;
use Tests\TestCase;

uses(TestCase::class);

it('maps L12 permissions to model-aware groups', function () {
    expect(PermissionGrouper::resolveGroup('items-create'))->toBe('Items');
    expect(PermissionGrouper::resolveGroup('items-convert-legacy'))->toBe('Items');
    expect(PermissionGrouper::resolveGroup('assetLancar-list'))->toBe('Asset Lancar');
    expect(PermissionGrouper::resolveGroup('assetTetap-list'))->toBe('Asset Tetap');
    expect(PermissionGrouper::resolveGroup('users-roles-list'))->toBe('Roles');
    expect(PermissionGrouper::resolveGroup('checklist-templates-edit'))->toBe('Checklist Peran');
    expect(PermissionGrouper::resolveGroup('checklist-templates-delete'))->toBe('Checklist Peran');
    expect(PermissionGrouper::resolveGroup('setting-general-view'))->toBe('Settings');
    expect(PermissionGrouper::resolveGroup('journal-account-list'))->toBe('Journals — Accounts');
    expect(PermissionGrouper::resolveGroup('production-worker-list'))->toBe('Production — Workers');
    expect(PermissionGrouper::resolveGroup('invoice-maker-list'))->toBe('Invoice Maker');
    expect(PermissionGrouper::resolveGroup('transactions-edit-invoice'))->toBe('Transactions');
});

it('puts legacy orphan permission names in Other, not general', function () {
    expect(PermissionGrouper::resolveGroup('create'))->toBe('Other');
    expect(PermissionGrouper::resolveGroup('list'))->toBe('Other');
});

it('does not use a general catch-all label for known permissions', function () {
    expect(PermissionGrouper::resolveGroup('items-create'))->not->toBe('general');
    expect(PermissionGrouper::resolveGroup('items-create'))->not->toBe('Other');
});

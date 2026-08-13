<?php

use App\Support\PermissionGrouper;
use Tests\TestCase;

uses(TestCase::class);

it('maps L12 permissions to model-aware groups', function () {
    expect(PermissionGrouper::resolveGroup('items-create'))->toBe('Items');
    expect(PermissionGrouper::resolveGroup('assetLancar-list'))->toBe('Asset Lancar');
    expect(PermissionGrouper::resolveGroup('users-roles-list'))->toBe('Roles');
    expect(PermissionGrouper::resolveGroup('setting-general-view'))->toBe('Settings');
    expect(PermissionGrouper::resolveGroup('journal-account-list'))->toBe('Journals — Accounts');
    expect(PermissionGrouper::resolveGroup('production-worker-list'))->toBe('Production — Workers');
});

it('puts legacy orphan permission names in Other, not general', function () {
    expect(PermissionGrouper::resolveGroup('create'))->toBe('Other');
    expect(PermissionGrouper::resolveGroup('list'))->toBe('Other');
});

it('does not use a general catch-all label for known permissions', function () {
    expect(PermissionGrouper::resolveGroup('items-create'))->not->toBe('general');
    expect(PermissionGrouper::resolveGroup('items-create'))->not->toBe('Other');
});

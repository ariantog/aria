<?php

use App\Models\Report;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('no longer registers the item-sales report permission', function () {
    expect(Report::getPermissions())->not->toHaveKey('view-item-sales')
        ->and(Report::getPermissions())->not->toContain('report-item-sales');
});

it('remaps report-item-sales grants onto report-export-sell and drops the old permission', function () {
    $old = Permission::firstOrCreate(['name' => 'report-item-sales', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'report-export-sell', 'guard_name' => 'web']);

    $role = Role::firstOrCreate(['name' => 'item-sales-viewer', 'guard_name' => 'web']);
    $role->givePermissionTo($old);

    User::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo($old);

    $migration = require database_path('migrations/2026_08_30_100000_remap_item_sales_permission_to_export_sell.php');
    $migration->up();

    expect(Permission::query()->where('name', 'report-item-sales')->exists())->toBeFalse();
    expect($role->fresh()->hasPermissionTo('report-export-sell'))->toBeTrue();
    expect($user->fresh()->hasPermissionTo('report-export-sell'))->toBeTrue();
});

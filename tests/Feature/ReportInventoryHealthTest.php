<?php

use App\Models\Report;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    User::factory()->create();

    $this->user = User::factory()->create();

    Permission::firstOrCreate(['name' => Report::getPermissions()['view-inventory-health'], 'guard_name' => 'web']);
});

test('unauthorized user cannot view inventory health report', function () {
    $this->actingAs($this->user)
        ->get(route('reports.inventory-health'))
        ->assertForbidden();
});

test('authorized user can view inventory health report', function () {
    $this->user->givePermissionTo(Report::getPermissions()['view-inventory-health']);

    $this->actingAs($this->user)
        ->get(route('reports.inventory-health'))
        ->assertOk()
        ->assertSee('Inventory Health', false);
});

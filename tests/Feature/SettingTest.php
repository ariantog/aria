<?php

use App\Models\Setting;
use App\Models\User;
use Spatie\Permission\Models\Permission;


beforeEach(function () {
    // The first user created gets id 1, which bypasses all Gate checks
    // (see AppServiceProvider::boot Gate::before). Create a throwaway user
    // first so the acting user does NOT have id 1.
    User::factory()->create();

    $this->user = User::factory()->create();

    // Create permissions
    $permissions = Setting::getPermissions();
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }
});

test('unauthorized user cannot view settings', function () {
    $this->actingAs($this->user)
        ->get(route('system-settings.index'))
        ->assertStatus(403);
});

test('authorized user can view settings', function () {
    $this->user->givePermissionTo(Setting::getPermissions()['view']);

    $this->actingAs($this->user)
        ->get(route('system-settings.index'))
        ->assertStatus(200);
});

test('authorized user can view create setting page', function () {
    $this->user->givePermissionTo(Setting::getPermissions()['create']);

    $this->actingAs($this->user)
        ->get(route('system-settings.create'))
        ->assertStatus(200);
});

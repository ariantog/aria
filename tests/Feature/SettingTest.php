<?php

use App\Models\Setting;
use App\Models\User;
use Spatie\Permission\Models\Permission;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
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

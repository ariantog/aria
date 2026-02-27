<?php

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->location = Location::create([
        'name' => 'Test Location',
        'address' => 'Test Address',
        'description' => 'Test Description',
    ]);

    // Create permissions
    foreach (Location::getPermissions() as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }
});

test('user with permission can view location list', function () {
    // Note: currently index is public, but buttons rely on permissions.
    // If we were enforcing it on index, we'd need to give permission.
    // For now, just checking it loads.
    $this->actingAs($this->user)
        ->get(route('locations.index'))
        ->assertOk();
});

test('user with permission can view create location page', function () {
    $this->user->givePermissionTo(Location::getPermissions()['create']);

    $this->actingAs($this->user)
        ->get(route('locations.create'))
        ->assertOk();
});

test('user without permission cannot view create location page', function () {
    $this->actingAs($this->user)
        ->get(route('locations.create'))
        ->assertForbidden();
});

test('user with permission can store location', function () {
    $this->user->givePermissionTo(Location::getPermissions()['create']);

    $this->actingAs($this->user)
        ->post(route('locations.store'), [
            'name' => 'New Location',
            'address' => 'New Address',
        ])
        ->assertRedirect(route('locations.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('locations', ['name' => 'New Location']);
});

test('user without permission cannot store location', function () {
    $this->actingAs($this->user)
        ->post(route('locations.store'), [
            'name' => 'New Location',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('locations', ['name' => 'New Location']);
});

test('user with permission can view edit location page', function () {
    $this->user->givePermissionTo(Location::getPermissions()['edit']);

    $this->actingAs($this->user)
        ->get(route('locations.edit', $this->location))
        ->assertOk();
});

test('user without permission cannot view edit location page', function () {
    $this->actingAs($this->user)
        ->get(route('locations.edit', $this->location))
        ->assertForbidden();
});

test('user with permission can update location', function () {
    $this->user->givePermissionTo(Location::getPermissions()['edit']);

    $this->actingAs($this->user)
        ->put(route('locations.update', $this->location), [
            'name' => 'Updated Location',
            'address' => 'Updated Address',
        ])
        ->assertRedirect(route('locations.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('locations', ['name' => 'Updated Location']);
});

test('user without permission cannot update location', function () {
    $this->actingAs($this->user)
        ->put(route('locations.update', $this->location), [
            'name' => 'Updated Location',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('locations', ['name' => 'Updated Location']);
});

test('user with permission can delete location', function () {
    $this->user->givePermissionTo(Location::getPermissions()['delete']);

    $this->actingAs($this->user)
        ->delete(route('locations.destroy', $this->location))
        ->assertRedirect(route('locations.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('locations', ['id' => $this->location->id]);
});

test('user without permission cannot delete location', function () {
    $this->actingAs($this->user)
        ->delete(route('locations.destroy', $this->location))
        ->assertForbidden();

    $this->assertDatabaseHas('locations', ['id' => $this->location->id]);
});

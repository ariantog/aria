<?php

use App\Models\Borongan;
use App\Models\User;
use App\Models\Worker;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Seed permissions if they don't exist
    foreach (Borongan::getPermissions() as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->admin = User::factory()->create();
    $this->user = User::factory()->create();

    $role = Role::findOrCreate('superadmin');
    $this->admin->assignRole($role);
});

it('denies access to list borongan for unauthorized user', function () {
    $response = $this->actingAs($this->user)->get('/borongan');
    $response->assertStatus(403);
});

it('allows access to list borongan for authorized user', function () {
    $this->user->givePermissionTo('borongan-list');

    $response = $this->actingAs($this->user)->get('/borongan');
    $response->assertStatus(200);
    $response->assertViewIs('borongan.index');
});

it('denies access to create borongan for unauthorized user', function () {
    $response = $this->actingAs($this->user)->get('/borongan/create');
    $response->assertStatus(403);
});

it('allows access to create borongan for authorized user', function () {
    $this->user->givePermissionTo('borongan-create');

    $response = $this->actingAs($this->user)->get('/borongan/create');
    $response->assertStatus(200);
    $response->assertViewIs('borongan.create');
});

it('denies access to view borongan details for unauthorized user', function () {
    $worker = Worker::create(['name' => 'John Jahit', 'type' => Worker::TYPE_JAHIT]);
    $borongan = Borongan::create([
        'date' => now()->toDateString(),
        'user_id' => $this->admin->id,
        'jahit_id' => $worker->id,
        'total' => 100000,
        'total_items' => 10,
        'from' => now()->subDays(7)->toDateString(),
        'to' => now()->toDateString(),
    ]);

    $response = $this->actingAs($this->user)->get("/borongan/{$borongan->id}");
    $response->assertStatus(403);
});

it('allows access to view borongan details for authorized user', function () {
    $this->user->givePermissionTo('borongan-view');

    $worker = Worker::create(['name' => 'John Jahit', 'type' => Worker::TYPE_JAHIT]);
    $borongan = Borongan::create([
        'date' => now()->toDateString(),
        'user_id' => $this->admin->id,
        'jahit_id' => $worker->id,
        'total' => 100000,
        'total_items' => 10,
        'from' => now()->subDays(7)->toDateString(),
        'to' => now()->toDateString(),
    ]);

    $response = $this->actingAs($this->user)->get("/borongan/{$borongan->id}");
    $response->assertStatus(200);
    $response->assertViewIs('borongan.show');
});

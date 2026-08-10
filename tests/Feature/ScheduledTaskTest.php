<?php

use App\Models\ScheduledTask;
use App\Models\Setting;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    User::factory()->create();

    $this->user = User::factory()->create();

    foreach (array_merge(Setting::getPermissions(), ScheduledTask::getPermissions()) as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
});

test('unauthorized user cannot view cron manager', function () {
    $this->actingAs($this->user)
        ->get(route('scheduled-tasks.index'))
        ->assertForbidden();
});

test('authorized user can view cron manager', function () {
    $this->user->givePermissionTo(ScheduledTask::getPermissions()['view']);

    $this->actingAs($this->user)
        ->get(route('scheduled-tasks.index'))
        ->assertOk()
        ->assertSee('Cron Manager', false);
});

test('cron-only user can access cron manager without general settings permission', function () {
    $this->user->givePermissionTo(ScheduledTask::getPermissions()['view']);

    $this->actingAs($this->user)
        ->get(route('system-settings.index'))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->get(route('scheduled-tasks.index'))
        ->assertOk();
});

test('cron edit permission is required to toggle tasks', function () {
    $this->user->givePermissionTo(ScheduledTask::getPermissions()['view']);

    $task = ScheduledTask::create([
        'name' => 'Test Task',
        'command' => 'test:command-'.uniqid(),
        'frequency' => 'daily',
        'is_active' => true,
        'description' => 'Test',
    ]);

    $this->actingAs($this->user)
        ->post(route('scheduled-tasks.toggle', $task))
        ->assertForbidden();
});

test('authorized user can toggle scheduled tasks', function () {
    $this->user->givePermissionTo([
        ScheduledTask::getPermissions()['view'],
        ScheduledTask::getPermissions()['edit'],
    ]);

    $task = ScheduledTask::create([
        'name' => 'Test Task',
        'command' => 'test:command-'.uniqid(),
        'frequency' => 'daily',
        'is_active' => true,
        'description' => 'Test',
    ]);

    $this->actingAs($this->user)
        ->post(route('scheduled-tasks.toggle', $task))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($task->fresh()->is_active)->toBeFalse();
});

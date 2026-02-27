<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

test('items create page can be rendered', function () {
    $user = User::factory()->create();
    $permission = Permission::create(['name' => 'items-create']);
    $user->givePermissionTo($permission);

    $response = $this->actingAs($user)
        ->get(route('items.create'));

    $response->assertStatus(200);
});

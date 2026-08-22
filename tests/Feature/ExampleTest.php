<?php

use App\Models\User;

test('guests can visit the home page', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('authenticated users are redirected from home to the dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertRedirect(route('dashboard', absolute: false));
});

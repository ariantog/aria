<?php

use App\Models\User;

it('returns 404 for the removed expense and cash-flow reports', function (string $path) {
    $user = User::factory()->create([
        'active' => true,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get($path)
        ->assertNotFound();
})->with([
    'expense' => '/reports/expense',
    'cash-flow' => '/reports/cash-flow',
]);

it('does not list the removed reports in the sidebar', function () {
    $user = User::factory()->create();

    expect($user->is_superadmin)->toBeTrue();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Nett Cash', false)
        ->assertSee('Laba Rugi', false)
        ->assertDontSee('>Cash Flow<', false)
        ->assertDontSee('Laporan Biaya', false);
});

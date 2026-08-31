<?php

use App\Models\Report;
use App\Models\User;

it('no longer registers leftover finance report permissions', function (string $name) {
    expect(Report::getPermissions())->not->toHaveKey('view-purchase')
        ->and(Report::getPermissions())->not->toContain($name);
})->with([
    'report-cash-flow',
    'report-expense',
    'report-purchase',
    'cash-flow',
]);

it('returns 404 for the removed leftover finance reports', function (string $path) {
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
    'purchase' => '/reports/purchase',
]);

it('does not list the removed reports in the sidebar', function () {
    $user = User::factory()->create();

    expect($user->is_superadmin)->toBeTrue();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Nett Cash', false)
        ->assertSee('Laba Rugi', false)
        ->assertSee('Piutang Usaha', false)
        ->assertDontSee('>Cash Flow<', false)
        ->assertDontSee('Laporan Biaya', false)
        ->assertDontSee('/reports/purchase', false)
        ->assertDontSee('>Pembelian<', false);
});

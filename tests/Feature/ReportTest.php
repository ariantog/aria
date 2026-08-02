<?php

use App\Models\Addrbook;
use App\Models\MonthlyCategorySummary;
use App\Models\User;
use Spatie\Permission\Models\Permission;


test('cash flow report can be viewed', function () {
    // 1. Setup
    $user = User::factory()->create([
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    // Create permission
    Permission::create(['name' => 'reports-cash-flow']);
    $user->givePermissionTo('reports-cash-flow');

    // Create some data
    MonthlyCategorySummary::create([
        'year' => 2026,
        'month' => 4,
        'addrbook_type' => Addrbook::TYPE_CUSTOMER,
        'cash_in' => 1000.00,
        'cash_out' => 500.00,
        'sell' => 1500.00,
        'buy' => 0.00,
        'return' => 100.00,
        'return_supplier' => 0.00,
    ]);

    MonthlyCategorySummary::create([
        'year' => 2026,
        'month' => 4,
        'addrbook_type' => Addrbook::TYPE_BANK,
        'cash_in' => 2000.00,
        'cash_out' => 1000.00,
        'sell' => 0.00,
        'buy' => 0.00,
        'return' => 0.00,
        'return_supplier' => 0.00,
    ]);

    // 2. Action
    $response = $this->actingAs($user)
        ->get(route('reports.cash-flow', ['year' => 2026, 'month' => 4]));

    // 3. Assertion
    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Reports/CashFlow')
        ->has('groupBySender')
        ->has('groupByReceiver')
    );
});

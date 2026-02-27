<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TransactionPriceSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_buy_transaction_has_cost_price_source()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('transactions.create', ['type' => 'buy']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Create')
                ->where('config.price_source', 'cost')
            );
    }

    public function test_sell_transaction_has_price_price_source()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('transactions.create', ['type' => 'sell']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Create')
                ->where('config.price_source', 'price')
            );
    }
}

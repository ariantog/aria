<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionPriceSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_buy_transaction_has_cost_price_source()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('transactions.create', ['type' => 'buy']));
        $response->assertOk();
        $response->assertViewIs('transactions.create');
        $this->assertSame('cost', $response->viewData('config')['price_source']);

        $response->assertSee('const _PriceSource = "cost"', false);
        $response->assertSee('resolveRowPrice', false);
    }

    public function test_sell_transaction_has_price_price_source()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('transactions.create', ['type' => 'sell']));
        $response->assertOk();
        $response->assertViewIs('transactions.create');
        $this->assertSame('price', $response->viewData('config')['price_source']);
        $response->assertSee('const _PriceSource = "price"', false);
        $response->assertSee('resolveRowPrice', false);
    }

    public function test_return_transaction_uses_item_price_column()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('transactions.create', ['type' => 'return']));
        $response->assertOk();
        $this->assertSame('price', $response->viewData('config')['price_source']);
        $response->assertSee('const _PriceSource = "price"', false);
    }

    public function test_return_supplier_transaction_uses_item_cost_column()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('transactions.create', ['type' => 'return-supplier']));
        $response->assertOk();
        $this->assertSame('cost', $response->viewData('config')['price_source']);
        $response->assertSee('const _PriceSource = "cost"', false);
    }
}

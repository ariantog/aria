<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionFilterSortTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_it_filters_by_date_range(): void
    {
        Transaction::factory()->create(['date' => '2023-01-01', 'invoice_number' => 'INV-001']);
        Transaction::factory()->create(['date' => '2023-01-15', 'invoice_number' => 'INV-002']);
        Transaction::factory()->create(['date' => '2023-02-01', 'invoice_number' => 'INV-003']);

        $response = $this->get(route('transactions.index', [
            'from' => '2023-01-01',
            'to' => '2023-01-31',
        ]));

        $response->assertStatus(200);
        $data = $response->original->getData()['page']['props']['transactions']['data'];

        $this->assertCount(2, $data);
        $this->assertEquals('INV-002', $data[0]['invoice_number']);
        $this->assertEquals('INV-001', $data[1]['invoice_number']);
    }

    public function test_it_filters_by_type(): void
    {
        Transaction::factory()->create(['type' => Transaction::TYPE_BUY, 'invoice_number' => 'BUY-1']);
        Transaction::factory()->create(['type' => Transaction::TYPE_SELL, 'invoice_number' => 'SELL-1']);

        $response = $this->get(route('transactions.index', ['type' => Transaction::TYPE_BUY]));

        $data = $response->original->getData()['page']['props']['transactions']['data'];
        $this->assertCount(1, $data);
        $this->assertEquals('BUY-1', $data[0]['invoice_number']);
    }

    public function test_it_filters_by_grand_total_range(): void
    {
        Transaction::factory()->create(['grand_total' => 50000]);
        Transaction::factory()->create(['grand_total' => 150000]);
        Transaction::factory()->create(['grand_total' => 250000]);

        $response = $this->get(route('transactions.index', [
            'min_total' => 100000,
            'max_total' => 200000,
        ]));

        $data = $response->original->getData()['page']['props']['transactions']['data'];
        $this->assertCount(1, $data);
        $this->assertEquals(150000, $data[0]['grand_total']);
    }

    public function test_it_filters_by_specific_invoice_number(): void
    {
        Transaction::factory()->create(['invoice_number' => 'TRX-999']);
        Transaction::factory()->create(['invoice_number' => 'TRX-000']);

        $response = $this->get(route('transactions.index', ['invoice_number' => '999']));

        $data = $response->original->getData()['page']['props']['transactions']['data'];
        $this->assertCount(1, $data);
        $this->assertEquals('TRX-999', $data[0]['invoice_number']);
    }

    public function test_it_sorts_by_column(): void
    {
        Transaction::factory()->create(['grand_total' => 100, 'invoice_number' => 'INV-1']);
        Transaction::factory()->create(['grand_total' => 200, 'invoice_number' => 'INV-2']);

        $response = $this->get(route('transactions.index', [
            'sort' => 'grand_total',
            'direction' => 'asc',
        ]));

        $data = $response->original->getData()['page']['props']['transactions']['data'];
        $this->assertEquals(100, $data[0]['grand_total']);
    }
}

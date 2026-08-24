<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The transactions index is a plain server-rendered table (no client-side data grid), so
 * filtering and sorting are asserted against the rendered HTML rather than a JSON endpoint.
 */
class TransactionFilterSortTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    /** Fetch the index page HTML for the given query string. */
    private function indexHtml(array $query = []): string
    {
        $response = $this->get(route('transactions.index', $query));
        $response->assertOk();

        return $response->getContent();
    }

    /** Assert $first appears before $second in the rendered table. */
    private function assertOrder(string $html, string $first, string $second): void
    {
        $posFirst = strpos($html, $first);
        $posSecond = strpos($html, $second);

        $this->assertNotFalse($posFirst, "Expected to find {$first} in the table.");
        $this->assertNotFalse($posSecond, "Expected to find {$second} in the table.");
        $this->assertLessThan($posSecond, $posFirst, "Expected {$first} to be listed before {$second}.");
    }

    public function test_it_filters_by_date_range(): void
    {
        Transaction::factory()->create(['date' => '2023-01-01', 'invoice' => 'INV-001']);
        Transaction::factory()->create(['date' => '2023-01-15', 'invoice' => 'INV-002']);
        Transaction::factory()->create(['date' => '2023-02-01', 'invoice' => 'INV-003']);

        $html = $this->indexHtml(['from' => '2023-01-01', 'to' => '2023-01-31']);

        $this->assertStringContainsString('INV-001', $html);
        $this->assertStringContainsString('INV-002', $html);
        $this->assertStringNotContainsString('INV-003', $html);

        // Default ordering is newest first.
        $this->assertOrder($html, 'INV-002', 'INV-001');
    }

    public function test_it_filters_by_type(): void
    {
        Transaction::factory()->create(['type' => Transaction::TYPE_BUY, 'invoice' => 'BUY-1']);
        Transaction::factory()->create(['type' => Transaction::TYPE_SELL, 'invoice' => 'SELL-1']);

        $html = $this->indexHtml(['type' => Transaction::TYPE_BUY]);

        $this->assertStringContainsString('BUY-1', $html);
        $this->assertStringNotContainsString('SELL-1', $html);
    }

    public function test_it_filters_by_total_range(): void
    {
        Transaction::factory()->create(['total' => 50000, 'invoice' => 'LOW']);
        Transaction::factory()->create(['total' => 150000, 'invoice' => 'MID']);
        Transaction::factory()->create(['total' => 250000, 'invoice' => 'HIGH']);

        $html = $this->indexHtml(['min_total' => 100000, 'max_total' => 200000]);

        $this->assertStringContainsString('MID', $html);
        $this->assertStringNotContainsString('>LOW<', $html);
        $this->assertStringNotContainsString('>HIGH<', $html);
    }

    public function test_it_filters_by_specific_invoice(): void
    {
        Transaction::factory()->create(['invoice' => 'TRX-999']);
        Transaction::factory()->create(['invoice' => 'TRX-000']);

        $html = $this->indexHtml(['invoice' => '999']);

        $this->assertStringContainsString('TRX-999', $html);
        $this->assertStringNotContainsString('TRX-000', $html);
    }

    public function test_it_sorts_by_column(): void
    {
        Transaction::factory()->create(['total' => 100, 'invoice' => 'INV-CHEAP']);
        Transaction::factory()->create(['total' => 200, 'invoice' => 'INV-PRICEY']);

        $ascending = $this->indexHtml(['sort' => 'total', 'direction' => 'asc']);
        $this->assertOrder($ascending, 'INV-CHEAP', 'INV-PRICEY');

        $descending = $this->indexHtml(['sort' => 'total', 'direction' => 'desc']);
        $this->assertOrder($descending, 'INV-PRICEY', 'INV-CHEAP');
    }

    public function test_it_shows_an_empty_state_when_nothing_matches(): void
    {
        Transaction::factory()->create(['invoice' => 'TRX-001']);

        $html = $this->indexHtml(['invoice' => 'NO-SUCH-INVOICE']);

        $this->assertStringNotContainsString('TRX-001', $html);
    }
}

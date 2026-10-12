<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

use App\Services\JubelioService;
use Mockery\MockInterface;

/**
 * Recent Jubelio transaction_date for mocks (within order_queue_max_age_days).
 */
function jubelioRecentTransactionDate(): string
{
    return now()->subDays(2)->toIso8601String();
}

function jubelioRecentTransactionDateOnly(): string
{
    return now()->subDays(2)->toDateString();
}

/**
 * Jubelio sales-order list row with defaults for queue eligibility tests.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function jubelioEligibleListRow(array $overrides = []): array
{
    return array_merge([
        'internal_status' => 'SHIPPED',
        'is_canceled' => 'N',
        'transaction_date' => jubelioRecentTransactionDate(),
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $payload
 */
/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function enrichJubelioPayloadItems(array $payload): array
{
    if (! isset($payload['items']) || ! is_array($payload['items'])) {
        return $payload;
    }

    $payload['items'] = collect($payload['items'])->map(function (array $line) {
        if ((int) ($line['item_id'] ?? 0) > 0) {
            return $line;
        }

        $code = trim((string) ($line['item_code'] ?? $line['sku'] ?? ''));
        if ($code === '') {
            return $line;
        }

        $item = \App\Models\Item::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
            ->first();

        if (! $item) {
            return $line;
        }

        $jubelioItemId = (int) ($item->jubelio_item_id ?? 0);
        if ($jubelioItemId <= 0) {
            $jubelioItemId = random_int(100000, 999999);
            $item->forceFill(['jubelio_item_id' => $jubelioItemId])->save();
        }

        $line['item_id'] = $jubelioItemId;

        return $line;
    })->all();

    return $payload;
}

function jubelioPayloadLine(\App\Models\Item $item, float $qty, float $price, array $extra = []): array
{
    $jubelioItemId = (int) ($item->jubelio_item_id ?? 0);
    if ($jubelioItemId <= 0) {
        $jubelioItemId = random_int(100000, 999999);
        $item->forceFill(['jubelio_item_id' => $jubelioItemId])->save();
    }

    return array_merge([
        'item_id' => $jubelioItemId,
        'item_code' => $item->code,
        'qty' => $qty,
        'price' => $price,
    ], $extra);
}

function stubJubelioFetchSalesOrderOptional(MockInterface $mock): void
{
    $mock->shouldReceive('fetchSalesOrder')
        ->zeroOrMoreTimes()
        ->andReturn(null);
}

function mockJubelioSalesOrder(string $jubelioOrderId, array $payload): void
{
    $payload = enrichJubelioPayloadItems(array_merge([
        'internal_status' => 'SHIPPED',
        'transaction_date' => jubelioRecentTransactionDate(),
    ], $payload));

    test()->mock(JubelioService::class, function (MockInterface $mock) use ($jubelioOrderId, $payload) {
        $mock->shouldReceive('fetchSalesOrder')
            ->with($jubelioOrderId)
            ->andReturn($payload);
    });
}

/**
 * @param  array<string, mixed>  $payload
 */
function mockJubelioSalesReturn(string $jubelioOrderId, array $payload): void
{
    $payload = enrichJubelioPayloadItems(array_merge([
        'transaction_date' => jubelioRecentTransactionDate(),
    ], $payload));

    test()->mock(JubelioService::class, function (MockInterface $mock) use ($jubelioOrderId, $payload) {
        $mock->shouldReceive('fetchSalesReturn')
            ->with($jubelioOrderId)
            ->andReturn($payload);
    });
}

/**
 * Shopee recommended-item row shape for ShopeeAdsApiService mocks.
 *
 * @param  list<string>  $tags
 * @return array{item_id: int, sku_tags: list<string>, item_status: list<string>, ongoing_ad_types: list<string>}
 */
function shopeeRecommendedItem(int $itemId, array $tags = ['best selling']): array
{
    return [
        'item_id' => $itemId,
        'sku_tags' => $tags,
        'item_status' => ['normal'],
        'ongoing_ad_types' => [],
    ];
}

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
 * @param  array<string, mixed>  $payload
 */
function mockJubelioSalesOrder(string $jubelioOrderId, array $payload): void
{
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

<?php

use App\Support\TransactionItemSortOptions;
use Illuminate\Http\Request;

it('defaults transaction item sort to sku ascending', function () {
    expect(TransactionItemSortOptions::defaults())->toBe([
        'col' => 'sku',
        'dir' => 'asc',
    ]);
});

it('reads transaction item sort from the request', function () {
    $request = Request::create('/transactions/1/print', 'GET', [
        'sort_col' => 'qty',
        'sort_dir' => 'desc',
    ]);

    expect(TransactionItemSortOptions::fromRequest($request))->toBe([
        'col' => 'qty',
        'dir' => 'desc',
    ]);
});

it('falls back to defaults for invalid transaction item sort params', function () {
    $request = Request::create('/transactions/1/print', 'GET', [
        'sort_col' => 'not-a-column',
        'sort_dir' => 'sideways',
    ]);

    expect(TransactionItemSortOptions::fromRequest($request))->toBe([
        'col' => 'sku',
        'dir' => 'asc',
    ]);
});

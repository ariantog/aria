<?php

use App\Support\TransactionItemViewColumns;
use Illuminate\Http\Request;

it('returns default transaction item view columns', function () {
    expect(TransactionItemViewColumns::defaults())->toBe([
        'image' => true,
        'barcode' => true,
        'sku' => false,
        'name' => true,
        'description' => false,
    ]);
});

it('parses transaction item view columns from request query params', function () {
    $request = Request::create('/transactions/1/print', 'GET', [
        'image' => '0',
        'barcode' => '1',
        'sku' => '1',
        'name' => '0',
        'desc' => '1',
    ]);

    expect(TransactionItemViewColumns::fromRequest($request))->toBe([
        'image' => false,
        'barcode' => true,
        'sku' => true,
        'name' => false,
        'description' => true,
    ]);
});

it('builds query string from transaction item view columns', function () {
    expect(TransactionItemViewColumns::toQueryString([
        'image' => false,
        'barcode' => true,
        'sku' => true,
        'name' => false,
        'description' => true,
    ]))->toBe('image=0&barcode=1&sku=1&name=0&desc=1');
});

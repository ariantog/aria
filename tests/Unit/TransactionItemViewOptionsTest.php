<?php

use App\Support\TransactionItemViewOptions;
use Illuminate\Http\Request;

it('returns default transaction item view options', function () {
    expect(TransactionItemViewOptions::defaults())->toBe([
        'showImage' => true,
        'showBarcode' => true,
        'showSku' => false,
        'showName' => true,
        'showDescription' => false,
    ]);
});

it('parses transaction item view options from request query params', function () {
    $request = Request::create('/transactions/1/print', 'GET', [
        'image' => '0',
        'barcode' => '1',
        'sku' => '1',
        'name' => '0',
        'desc' => '1',
    ]);

    expect(TransactionItemViewOptions::fromRequest($request))->toBe([
        'showImage' => false,
        'showBarcode' => true,
        'showSku' => true,
        'showName' => false,
        'showDescription' => true,
    ]);
});

it('counts leading item view columns', function () {
    expect(TransactionItemViewOptions::leadingColumnCount([
        'showImage' => false,
        'showBarcode' => true,
        'showSku' => true,
        'showName' => false,
        'showDescription' => true,
    ]))->toBe(3);
});

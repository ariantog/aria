<?php

use App\Models\Item;
use App\Support\TransactionItemViewOptions;
use Illuminate\Http\Request;

it('returns default transaction item view options', function () {
    expect(TransactionItemViewOptions::defaults())->toBe([
        'showImage' => true,
        'showBarcode' => true,
        'showSku' => false,
        'showLegacyCode' => false,
        'showName' => true,
        'showDescription' => false,
    ]);
});

it('parses transaction item view options from request query params', function () {
    $request = Request::create('/transactions/1/print', 'GET', [
        'image' => '0',
        'barcode' => '1',
        'sku' => '1',
        'legacy' => '1',
        'name' => '0',
        'desc' => '1',
    ]);

    expect(TransactionItemViewOptions::fromRequest($request))->toBe([
        'showImage' => false,
        'showBarcode' => true,
        'showSku' => true,
        'showLegacyCode' => true,
        'showName' => false,
        'showDescription' => true,
    ]);
});

it('auto-enables sku when legacy code is requested without sku', function () {
    $request = Request::create('/transactions/1/print', 'GET', [
        'legacy' => '1',
        'sku' => '0',
    ]);

    expect(TransactionItemViewOptions::fromRequest($request))->toMatchArray([
        'showLegacyCode' => true,
        'showSku' => true,
    ]);
});

it('counts leading item view columns including shared sku column once', function () {
    expect(TransactionItemViewOptions::leadingColumnCount([
        'showImage' => false,
        'showBarcode' => true,
        'showSku' => false,
        'showLegacyCode' => true,
        'showName' => false,
        'showDescription' => true,
    ]))->toBe(3);
});

it('hides the shared sku column when sku and legacy code are unchecked', function () {
    $itemView = TransactionItemViewOptions::defaults();

    expect(TransactionItemViewOptions::showSkuColumn($itemView))->toBeFalse()
        ->and(TransactionItemViewOptions::skuColumnLabel($itemView))->toBe('');
});

it('shows sku code without legacy fallback when only sku is enabled', function () {
    $item = new Item([
        'code' => 'NEW-SKU',
        'legacy_code' => 'OLD-SKU',
    ]);

    $itemView = array_merge(TransactionItemViewOptions::defaults(), [
        'showSku' => true,
        'showLegacyCode' => false,
    ]);

    expect(TransactionItemViewOptions::skuColumnValue($item, $itemView))->toBe('NEW-SKU')
        ->and(TransactionItemViewOptions::skuColumnLabel($itemView))->toBe('SKU');
});

it('shows legacy code with code fallback when legacy code is enabled', function () {
    $withLegacy = new Item([
        'code' => 'NEW-SKU',
        'legacy_code' => 'OLD-SKU',
    ]);
    $withoutLegacy = new Item([
        'code' => 'NEW-SKU',
        'legacy_code' => null,
    ]);

    $itemView = array_merge(TransactionItemViewOptions::defaults(), [
        'showSku' => true,
        'showLegacyCode' => true,
    ]);

    expect(TransactionItemViewOptions::skuColumnValue($withLegacy, $itemView))->toBe('OLD-SKU')
        ->and(TransactionItemViewOptions::skuColumnValue($withoutLegacy, $itemView))->toBe('NEW-SKU')
        ->and(TransactionItemViewOptions::skuColumnLabel($itemView))->toBe('Legacy code');
});

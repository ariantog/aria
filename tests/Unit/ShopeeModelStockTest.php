<?php

use App\Services\Shopee\ShopeeModelStock;

it('sums sellable stock across locations when no location filter', function () {
    $model = [
        'stock_info_v2' => [
            'seller_stock' => [
                ['location_id' => 'IDZ', 'stock' => 5],
                ['location_id' => 'IDA', 'stock' => 3],
            ],
        ],
    ];

    expect(ShopeeModelStock::sellableQuantity($model))->toBe(8);
});

it('filters sellable stock by shopee location id', function () {
    $model = [
        'stock_info_v2' => [
            'seller_stock' => [
                ['location_id' => 'IDZ', 'stock' => 5],
                ['location_id' => 'IDA', 'stock' => 3],
            ],
        ],
    ];

    expect(ShopeeModelStock::sellableQuantity($model, 'IDZ'))->toBe(5)
        ->and(ShopeeModelStock::sellableQuantity($model, 'MISSING'))->toBe(0);
});

it('picks model by id or sku', function () {
    $models = [
        ['model_id' => 10, 'model_sku' => 'AAA-S'],
        ['model_id' => 11, 'model_sku' => 'AAA-M'],
    ];

    expect(ShopeeModelStock::pickModel($models, 11, null)['model_id'])->toBe(11)
        ->and(ShopeeModelStock::pickModel($models, 0, 'aaa-m')['model_id'])->toBe(11)
        ->and(ShopeeModelStock::pickModel($models, 0, 'WRONG-SKU'))->toBeNull();
});

it('pickModelBySku returns null when no exact sku match', function () {
    $models = [
        ['model_id' => 10, 'model_sku' => 'KNEESUPPORT-21-NAVY-S'],
        ['model_id' => 11, 'model_sku' => 'KNEESUPPORT-21-RED-S'],
    ];

    expect(ShopeeModelStock::pickModelBySku($models, 'KNEESUPPORT-21-NAVY-S')['model_id'])->toBe(10)
        ->and(ShopeeModelStock::pickModelBySku($models, 'KNEESUPPORT-21'))->toBeNull();
});

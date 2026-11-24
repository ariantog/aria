<?php

use App\Services\Shopee\ShopeeShippingDocumentTypeResolver;

beforeEach(function () {
    config(['services.shopee_admin.shipping_document_type' => 'NORMAL_AIR_WAYBILL']);
});

it('prefers shopee suggested thermal type over config normal', function () {
    $resolver = new ShopeeShippingDocumentTypeResolver;

    $picked = $resolver->pickFromParameterRow([
        'suggest_shipping_document_type' => 'THERMAL_AIR_WAYBILL',
        'selectable_shipping_document_type_list' => [
            'NORMAL_AIR_WAYBILL',
            'THERMAL_AIR_WAYBILL',
        ],
    ]);

    expect($picked)->toBe('THERMAL_AIR_WAYBILL');
});

it('falls back to thermal in selectable list when suggest is empty', function () {
    $resolver = new ShopeeShippingDocumentTypeResolver;

    $picked = $resolver->pickFromParameterRow([
        'selectable_shipping_document_type_list' => ['NORMAL_AIR_WAYBILL', 'THERMAL_AIR_WAYBILL'],
    ]);

    expect($picked)->toBe('THERMAL_AIR_WAYBILL');
});

it('uses config default when parameter row is empty', function () {
    $resolver = new ShopeeShippingDocumentTypeResolver;

    expect($resolver->pickFromParameterRow([]))->toBe('NORMAL_AIR_WAYBILL');
});
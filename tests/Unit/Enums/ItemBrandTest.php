<?php

use App\Enums\ItemBrand;

test('fromPcode maps manufactured prefixes including CX digit brands', function (string $pcode, ItemBrand $expected) {
    expect(ItemBrand::fromPcode($pcode))->toBe($expected);
})->with([
    'cx9' => ['CX90233-23', ItemBrand::CX9],
    'cx0' => ['CX00122-04', ItemBrand::CX0],
    'hj' => ['HJ00022-01', ItemBrand::HJ],
    'unknown' => ['ZZ12345-01', ItemBrand::NO_BRAND],
]);

<?php

use App\Enums\ItemType;

it('coerces item type from enum int or string', function () {
    expect(ItemType::coerce(ItemType::ASSET_LANCAR))->toBe(ItemType::ASSET_LANCAR);
    expect(ItemType::coerce(1))->toBe(ItemType::ITEM);
    expect(ItemType::coerce('2'))->toBe(ItemType::ASSET_LANCAR);
    expect(ItemType::coerce(4))->toBeNull();
    expect(ItemType::coerce(null))->toBeNull();
});

<?php

use App\Enums\AddrbookType;
use App\Models\Addrbook;
use Tests\TestCase;

uses(TestCase::class);

it('resolves addrbook type slug from enum instance without int cast', function () {
    $addrbook = new Addrbook(['name' => 'Test Customer']);
    $addrbook->setAttribute('type', AddrbookType::Customer);

    expect($addrbook->type_slug)->toBe('customer');
    expect($addrbook->type_name)->toBe('Customer');
    expect($addrbook->typeValue())->toBe(AddrbookType::Customer->value);
});

it('coerces addrbook type from int or enum', function () {
    expect(Addrbook::typeValueFrom(AddrbookType::Warehouse))->toBe(2);
    expect(Addrbook::typeValueFrom(3))->toBe(3);
    expect(AddrbookType::coerce('4'))->toBe(AddrbookType::Supplier);
});

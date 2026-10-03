<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Setting;
use App\Models\User;
use App\Support\ItemInventorySettings;
use App\Support\ItemQuantityValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = User::query()->find(1) ?? User::factory()->create(['id' => 1]);
    $this->actingAs($user);
});

test('service create is always tanpa stok and respects decimal setting', function () {
    Setting::updateOrCreate(
        ['slug' => ItemInventorySettings::SETTING_DECIMAL_SERVICES],
        ['group' => 'Stuff', 'name' => 'Decimal Quantity — Services', 'value' => '1']
    );

    $response = $this->post(route('services.store'), [
        'name' => 'Print Vinyl',
        'code' => 'SVC-PRINT-VINYL',
        'price' => 50000,
        'allow_decimal_quantity' => '1',
    ]);

    $response->assertRedirect();
    $item = Item::query()->where('code', 'SVC-PRINT-VINYL')->first();
    expect($item)->not->toBeNull();
    expect($item->type)->toBe(ItemType::SERVICE);
    expect($item->tracksInventory())->toBeFalse();
    expect($item->allowsDecimalQuantity())->toBeTrue();

    $this->get(route('services.index'))->assertOk()->assertSee('SVC-PRINT-VINYL');
});

test('service decimal flag is off when stuff setting is disabled', function () {
    $this->post(route('services.store'), [
        'name' => 'Training',
        'code' => 'SVC-TRAIN',
        'price' => 100000,
        'allow_decimal_quantity' => '1',
    ])->assertRedirect();

    $item = Item::query()->where('code', 'SVC-TRAIN')->first();
    expect($item->allowsDecimalQuantity())->toBeFalse();
});

test('catalog items always track inventory', function () {
    $item = Item::factory()->create(['type' => ItemType::ITEM, 'track_inventory' => false]);

    expect($item->tracksInventory())->toBeTrue();
});

test('item quantity validator enforces integer and decimal rules', function () {
    $integerItem = Item::factory()->create([
        'allow_decimal_quantity' => false,
        'track_inventory' => true,
    ]);
    $decimalItem = Item::factory()->create([
        'allow_decimal_quantity' => true,
        'track_inventory' => false,
        'type' => ItemType::SERVICE,
    ]);

    Setting::updateOrCreate(
        ['slug' => ItemInventorySettings::SETTING_DECIMAL_SERVICES],
        ['group' => 'Stuff', 'name' => 'Decimal Quantity — Services', 'value' => '1']
    );
    $decimalItem->refresh();

    expect(ItemQuantityValidator::validateQuantity($integerItem, 10))->toBeNull();
    expect(ItemQuantityValidator::validateQuantity($integerItem, 10.5))->not->toBeNull();
    expect(ItemQuantityValidator::validateQuantity($decimalItem, 25.9))->toBeNull();
    expect(ItemQuantityValidator::validateQuantity($decimalItem, 25.999))->not->toBeNull();
    expect(ItemQuantityValidator::validateQuantity($integerItem, 0.5))->not->toBeNull();
});

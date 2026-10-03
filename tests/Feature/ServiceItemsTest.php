<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\User;
use App\Support\ItemQuantityValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = User::query()->find(1) ?? User::factory()->create(['id' => 1]);
    $this->actingAs($user);
});

test('service create defaults to tanpa stok and appears under services index', function () {
    $response = $this->post(route('services.store'), [
        'name' => 'Print Vinyl',
        'code' => 'SVC-PRINT-VINYL',
        'price' => 50000,
        'tanpa_stok' => '1',
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

test('item quantity validator enforces integer and decimal rules', function () {
    $integerItem = Item::factory()->create([
        'allow_decimal_quantity' => false,
        'track_inventory' => true,
    ]);
    $decimalItem = Item::factory()->create([
        'allow_decimal_quantity' => true,
        'track_inventory' => false,
    ]);

    expect(ItemQuantityValidator::validateQuantity($integerItem, 10))->toBeNull();
    expect(ItemQuantityValidator::validateQuantity($integerItem, 10.5))->not->toBeNull();
    expect(ItemQuantityValidator::validateQuantity($decimalItem, 25.9))->toBeNull();
    expect(ItemQuantityValidator::validateQuantity($decimalItem, 25.999))->not->toBeNull();
    expect(ItemQuantityValidator::validateQuantity($integerItem, 0.5))->not->toBeNull();
});

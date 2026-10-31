<?php

use App\Models\Item;
use App\Models\ItemGroup;

it('copies colorway pricing onto skus that still store zero', function () {
    $group = ItemGroup::factory()->create([
        'price' => 125000,
        'reseller_price' => 99000,
        'cost' => 45000,
    ]);

    $zeroSku = Item::factory()->create([
        'group_id' => $group->id,
        'price' => 0,
        'reseller_price' => 0,
        'cost' => 0,
    ]);

    $pricedSku = Item::factory()->create([
        'group_id' => $group->id,
        'price' => 88000,
        'reseller_price' => 0,
        'cost' => 0,
    ]);

    $this->artisan('app:backfill-sku-pricing-from-colorway')
        ->assertSuccessful();

    $zeroSku->refresh();
    $pricedSku->refresh();

    expect((float) $zeroSku->price)->toBe(125000.0)
        ->and((float) $zeroSku->reseller_price)->toBe(99000.0)
        ->and((float) $zeroSku->cost)->toBe(45000.0)
        ->and((float) $pricedSku->price)->toBe(88000.0)
        ->and((float) $group->fresh()->price)->toBe(125000.0);
});

it('dry run does not persist sku pricing', function () {
    $group = ItemGroup::factory()->create(['price' => 50000]);
    $item = Item::factory()->create(['group_id' => $group->id, 'price' => 0]);

    $this->artisan('app:backfill-sku-pricing-from-colorway', ['--dry-run' => true])
        ->assertSuccessful();

    expect((float) $item->fresh()->price)->toBe(0.0);
});

it('limits backfill to one colorway group when group option is set', function () {
    $groupA = ItemGroup::factory()->create(['price' => 100000]);
    $groupB = ItemGroup::factory()->create(['price' => 200000]);

    $itemA = Item::factory()->create(['group_id' => $groupA->id, 'price' => 0]);
    $itemB = Item::factory()->create(['group_id' => $groupB->id, 'price' => 0]);

    $this->artisan('app:backfill-sku-pricing-from-colorway', ['--group' => (string) $groupA->id])
        ->assertSuccessful();

    expect((float) $itemA->fresh()->price)->toBe(100000.0)
        ->and((float) $itemB->fresh()->price)->toBe(0.0);
});

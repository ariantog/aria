<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemParent;
use App\Models\Tag;
use App\Support\ItemPricing;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->typeTag = Tag::factory()->create(['type' => Tag::TYPE_TYPE, 'code' => 'AJD', 'name' => 'Jacket']);
    $this->warnaTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLACK', 'name' => 'BLACK']);

    $this->sizeTags = collect(['LIGHT', 'MEDIUM', 'HEAVY', 'XHEAVY'])->mapWithKeys(function (string $code) {
        $tag = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => $code, 'name' => $code]);

        return [$code => $tag];
    });
});

function createParentSkuMatrix($test): ItemParent
{
    $parent = ItemParent::query()->create(['price' => 69000]);
    $group = ItemGroup::factory()->create([
        'master' => 'CX99001-01',
        'variant' => '01',
        'name' => 'WEIGHT TEST',
        'parent_id' => $parent->id,
    ]);

    foreach ($test->sizeTags as $code => $sizeTag) {
        $item = Item::factory()->create([
            'group_id' => $group->id,
            'type' => ItemType::ITEM,
            'pcode' => 'CX99001-01',
            'code' => 'AJD-CX99001-01-'.$code,
            'price' => 0,
        ]);
        $item->tags()->attach([$test->typeTag->id, $test->warnaTag->id, $sizeTag->id]);
    }

    return $parent;
}

test('apply parent size prices repairs map and pins unmapped heavy from parent default', function () {
    $parent = createParentSkuMatrix($this);

    $exit = Artisan::call('app:apply-parent-size-prices', [
        'parent' => $parent->id,
        '--map' => 'LIGHT:49000,MEDIUM:59000,XHEAVY:79000',
    ]);

    expect($exit)->toBe(0);

    $bySize = fn (string $code) => Item::query()
        ->where('code', 'AJD-CX99001-01-'.$code)
        ->firstOrFail()
        ->fresh(['group.parent', 'tags']);

    expect($bySize('LIGHT')->effectivePrice())->toBe(49000.0)
        ->and($bySize('MEDIUM')->effectivePrice())->toBe(59000.0)
        ->and($bySize('XHEAVY')->effectivePrice())->toBe(79000.0)
        ->and($bySize('HEAVY')->effectivePrice())->toBe(69000.0);

    $parent->refresh();
    expect((float) $parent->price)->toBe(0.0);
});

test('apply parent size prices dry run does not write', function () {
    $parent = createParentSkuMatrix($this);

    Artisan::call('app:apply-parent-size-prices', [
        'parent' => $parent->id,
        '--map' => 'LIGHT:49000,MEDIUM:59000,XHEAVY:79000',
        '--dry-run' => true,
    ]);

    $item = Item::query()->where('code', 'AJD-CX99001-01-LIGHT')->firstOrFail()->fresh(['group.parent', 'tags']);

    expect($item->effectivePrice())->toBe(69000.0);
    $parent->refresh();
    expect((float) $parent->price)->toBe(69000.0);
});

test('apply parent size prices requires map option', function () {
    $parent = createParentSkuMatrix($this);

    $exit = Artisan::call('app:apply-parent-size-prices', [
        'parent' => $parent->id,
    ]);

    expect($exit)->toBe(1);
    expect(Artisan::output())->toContain('--map');
});

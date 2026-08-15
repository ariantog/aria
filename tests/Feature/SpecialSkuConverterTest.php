<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use App\Models\User;
use App\Services\Items\ItemIdentityBuilder;
use App\Services\Items\LegacyItemConverterService;
use App\Services\Items\SpecialSkuConverterRules;
use App\Services\Items\SpecialSkuConverterService;
use App\Services\Items\SpecialSkuIdentityParser;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->rules = new SpecialSkuConverterRules;
    $this->parser = new SpecialSkuIdentityParser($this->rules, new ItemIdentityBuilder);
    $this->service = new SpecialSkuConverterService(
        $this->rules,
        $this->parser,
        new LegacyItemConverterService(new ItemIdentityBuilder),
    );

    Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'LIGHT', 'name' => 'Light']);
    Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'MEDIUM', 'name' => 'Medium']);
    Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'HEAVY', 'name' => 'Heavy']);
});

it('parses fabricband legacy sku into canonical identity', function () {
    $item = Item::factory()->make([
        'type' => ItemType::ASSET_LANCAR->value,
        'code' => 'FABRICBAND-03-LIGHT-BABYBLUE',
        'name' => 'FABRIC BAND - BABYBLUE - LIGHT',
    ]);

    $result = $this->parser->parse($item);

    expect($result->success)->toBeTrue()
        ->and($result->pcode)->toBe('FABRICBAND-03')
        ->and($result->warnaCode)->toBe('BABYBLUE')
        ->and($result->sizeCode)->toBe('LIGHT')
        ->and($result->canonicalCode)->toBe('FABRICBAND-03-BABYBLUE-LIGHT')
        ->and($result->legacyCode)->toBe('FABRICBAND-03-LIGHT-BABYBLUE');
});

it('resolves size tags by name when production code differs', function () {
    Tag::query()->where('type', Tag::TYPE_SIZE)->whereRaw('UPPER(name) = ?', ['HEAVY'])->delete();

    Tag::factory()->create([
        'type' => Tag::TYPE_SIZE,
        'code' => 'HV',
        'name' => 'HEAVY',
        'item_type' => 0,
    ]);

    $parser = new SpecialSkuIdentityParser($this->rules, new ItemIdentityBuilder);
    $item = Item::factory()->make([
        'type' => ItemType::ASSET_LANCAR->value,
        'code' => 'FABRICBAND-03-HEAVY-BABYBLUE',
    ]);

    $result = $parser->parse($item);

    expect($result->success)->toBeTrue()
        ->and($result->sizeCode)->toBe('HV')
        ->and($result->canonicalCode)->toBe('FABRICBAND-03-BABYBLUE-HV');
});

it('converts fabricband sku preserving legacy_code for jubelio lookup', function () {
    $item = Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR->value,
        'group_id' => null,
        'code' => 'FABRICBAND-03-LIGHT-BABYBLUE',
        'legacy_code' => null,
        'pcode' => 'FABRICBAND-03',
        'name' => 'FABRIC BAND - BABYBLUE - LIGHT',
        'jubelio_item_id' => 12345,
    ]);

    $run = $this->service->runItems(collect([$item]), $this->user);
    $item->refresh();

    expect($run->success_count)->toBe(1)
        ->and($item->code)->toBe('FABRICBAND-03-BABYBLUE-LIGHT')
        ->and($item->legacy_code)->toBe('FABRICBAND-03-LIGHT-BABYBLUE')
        ->and($item->jubelio_item_id)->toBe(12345)
        ->and($item->group_id)->not->toBeNull()
        ->and(Item::findBySku('FABRICBAND-03-LIGHT-BABYBLUE')?->id)->toBe($item->id);
});

it('renders special converter page for superadmin', function () {
    Item::factory()->create([
        'type' => ItemType::ASSET_LANCAR->value,
        'group_id' => null,
        'code' => 'FABRICBAND-03-LIGHT-BABYBLUE',
        'pcode' => 'FABRICBAND-03',
        'name' => 'FABRIC BAND - BABYBLUE - LIGHT',
    ]);

    $this->actingAs($this->user)
        ->get(route('items.special-converter'))
        ->assertOk()
        ->assertSee('Special SKU Converter', false)
        ->assertSee('FABRICBAND-03-LIGHT-BABYBLUE', false)
        ->assertSee('FABRICBAND-03-BABYBLUE-LIGHT', false);
});

it('forbids special converter without permission', function () {
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)
        ->get(route('items.special-converter'))
        ->assertForbidden();
});

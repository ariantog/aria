<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemIdentityConversionResult;
use App\Models\Tag;
use App\Models\User;
use App\Services\Items\ItemIdentityBuilder;
use App\Services\Items\LegacyItemConverterService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->service = new LegacyItemConverterService(new ItemIdentityBuilder);

    Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'S', 'name' => 'S']);
    Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'XL', 'name' => 'XL']);
    Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => '14OZ', 'name' => '14OZ']);
    Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => '3M', 'name' => '3M']);
    Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'AS', 'name' => 'All Size']);

    Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLACK', 'name' => 'BLACK']);
    Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'POWDERWHITE', 'name' => 'POWDERWHITE']);
    Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'MARBLEPINK', 'name' => 'MARBLEPINK']);

    $this->typeTag = Tag::factory()->create(['type' => Tag::TYPE_TYPE, 'code' => 'AJJ', 'name' => 'Jacket']);
    $this->jahitTag = Tag::factory()->create(['type' => Tag::TYPE_JAHIT, 'code' => 'J1', 'name' => 'J1']);
    $this->warnaTag = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'NAVY', 'name' => 'NAVY']);
});

it('converts asset fixture batch with groups and tags', function () {
    $fixtures = [
        ['code' => 'YOGAMAT-20-POWDERWHITE', 'name' => 'YOGA MAT - POWDERWHITE'],
        ['code' => 'LUMBARSUPPORT-01-BLACK-S', 'name' => 'LUMBAR SUPPORT - BLACK - S'],
        ['code' => 'BOXINGGLOVE-02-BLACK-14OZ', 'name' => 'BOXING GLOVE - BLACK - 14OZ'],
        ['code' => 'BOXINGWRAP-02-MARBLEPINK-3M', 'name' => 'BOXING WRAP - MARBLEPINK - 3M'],
    ];

    foreach ($fixtures as $fixture) {
        Item::factory()->create([
            'type' => ItemType::ASSET_LANCAR,
            'group_id' => null,
            'code' => $fixture['code'],
            'pcode' => implode('-', array_slice(explode('-', $fixture['code']), 0, 2)),
            'name' => $fixture['name'],
        ]);
    }

    $run = $this->service->runBatch(ItemType::ASSET_LANCAR, $this->user);

    expect($run->success_count)->toBe(4)
        ->and($run->failed_count)->toBe(0);

    foreach ($fixtures as $fixture) {
        $item = Item::query()->where('code', $fixture['code'])->first();
        expect($item)->not->toBeNull()
            ->and($item->group_id)->not->toBeNull()
            ->and($item->tags->contains(fn (Tag $tag) => $tag->type === Tag::TYPE_WARNA))->toBeTrue();
    }
});

it('converts AJJPL2512906XL preserving legacy_code', function () {
    $item = Item::factory()->create([
        'type' => ItemType::ITEM,
        'group_id' => null,
        'code' => 'AJJPL2512906XL',
        'legacy_code' => null,
        'pcode' => 'PL25129-06',
        'name' => 'JACKET',
    ]);
    $item->tags()->sync([
        $this->typeTag->id,
        $this->warnaTag->id,
        $this->jahitTag->id,
        Tag::where('code', 'XL')->first()->id,
    ]);

    $run = $this->service->runBatch(ItemType::ITEM, $this->user);

    $item->refresh();

    expect($run->success_count)->toBe(1)
        ->and($item->code)->toBe('AJJ-PL25129-06-XL')
        ->and($item->legacy_code)->toBe('AJJPL2512906XL')
        ->and($item->group_id)->not->toBeNull();
});

it('records failure_code for unparseable manufactured items', function () {
    Item::factory()->create([
        'type' => ItemType::ITEM,
        'group_id' => null,
        'code' => 'NOT-A-VALID-SKU',
        'pcode' => 'INVALID',
    ]);

    $run = $this->service->runBatch(ItemType::ITEM, $this->user);

    expect($run->failed_count)->toBe(1);

    $result = ItemIdentityConversionResult::query()->where('run_id', $run->id)->first();

    expect($result->status)->toBe(ItemIdentityConversionResult::STATUS_FAILED)
        ->and($result->failure_code)->not->toBeNull();
});

it('renders legacy converter page for superadmin', function () {
    $this->actingAs($this->user)
        ->get(route('items.legacy-converter'))
        ->assertOk()
        ->assertSee('Legacy Item Identity Converter', false)
        ->assertSee('Failed', false);
});

it('forbids legacy converter for non-superadmin without permission', function () {
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)
        ->get(route('items.legacy-converter'))
        ->assertForbidden();
});

it('allows reviewing failed tab while pending items remain', function () {
    Item::factory()->create([
        'type' => ItemType::ITEM,
        'group_id' => null,
        'code' => 'BADCODE',
        'pcode' => 'INVALID',
    ]);

    Item::factory()->create([
        'type' => ItemType::ITEM,
        'group_id' => null,
        'code' => 'ALSO-BAD',
        'pcode' => 'INVALID',
    ]);

    $run = $this->service->runBatch(ItemType::ITEM, $this->user, limit: 1);

    expect($run->failed_count)->toBe(1)
        ->and($this->service->eligibleQuery(ItemType::ITEM)->count())->toBe(2);

    $this->actingAs($this->user)
        ->get(route('items.legacy-converter', ['tab' => 'failed', 'type' => ItemType::ITEM->value]))
        ->assertOk()
        ->assertSee('SKU_UNPARSEABLE', false);
});

<?php

use App\Enums\ItemType;
use App\Models\RestockSheet;
use App\Models\Tag;
use App\Models\User;
use App\Services\ItemService;
use App\Services\Restock\RestockSheetService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    foreach (['restock-list', 'restock-create', 'restock-edit'] as $perm) {
        Permission::firstOrCreate(['name' => $perm]);
    }
    $this->user->givePermissionTo(['restock-list', 'restock-create', 'restock-edit']);

    $this->typeTag = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'code' => 'ELBOW',
        'name' => 'Elbow',
        'item_type' => 0,
    ]);
    $this->manufacturedTypeTag = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'code' => 'AJD',
        'name' => 'Jacket',
        'item_type' => 2,
    ]);
    $this->warnaBlue = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLUE', 'name' => 'BLUE']);
    $this->warnaRed = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'RED', 'name' => 'RED']);
    $this->sizeS = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'S', 'name' => 'S']);
    $this->sizeM = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'M', 'name' => 'M']);
});

function createAssetLancarSkus(object $context, string $pcode = 'ELBOW-03', string $productName = 'Soft Edition'): void
{
    $itemService = app(ItemService::class);

    $input = (object) [
        'pcode' => $pcode,
        'type' => ItemType::ASSET_LANCAR->value,
        'product_name' => $productName,
        'price' => 100000,
        'cost' => 50000,
    ];

    $tags = [
        'types' => [$context->typeTag->id],
        'sizes' => [$context->sizeS->id, $context->sizeM->id],
        'warna' => [$context->warnaBlue->id, $context->warnaRed->id],
        'jahit' => [],
    ];

    $itemService->create($input, $tags);
}

test('type tabs exclude manufactured tags with item_type 2', function () {
    $tags = app(RestockSheetService::class)->typeTags();

    expect($tags->pluck('id'))->toContain($this->typeTag->id);
    expect($tags->pluck('id'))->not->toContain($this->manufacturedTypeTag->id);
});

test('restock index redirects to first asset lancar type tab', function () {
    createAssetLancarSkus($this);

    $this->actingAs($this->user)
        ->get('/restock')
        ->assertRedirect(route('restock.type.show', $this->typeTag));
});

test('type landing shows one sheet row per type', function () {
    createAssetLancarSkus($this);
    createAssetLancarSkus($this, 'ELBOW-07', 'Elbow Support v2');

    $this->actingAs($this->user)
        ->get(route('restock.type.show', $this->typeTag))
        ->assertOk()
        ->assertSee('Elbow')
        ->assertSee('Start tracking')
        ->assertSee('2', false); // parent pcode count
});

test('creating a sheet seeds cells for every sku under the type', function () {
    createAssetLancarSkus($this);
    createAssetLancarSkus($this, 'ELBOW-07', 'Elbow Support v2');

    $response = $this->actingAs($this->user)
        ->post(route('restock.sheets.store', $this->typeTag));

    $sheet = RestockSheet::where('type_tag_id', $this->typeTag->id)->first();

    expect($sheet)->not->toBeNull();
    expect($sheet->name)->toBe('Elbow');
    expect($sheet->cells)->toHaveCount(8);

    $response->assertRedirect(route('restock.sheets.show', $sheet));
});

test('sync skus adds cells when new variants are created', function () {
    createAssetLancarSkus($this);

    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    expect($sheet->cells)->toHaveCount(4);

    $sizeL = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'L', 'name' => 'L']);

    app(ItemService::class)->create((object) [
        'pcode' => 'ELBOW-03',
        'type' => ItemType::ASSET_LANCAR->value,
        'product_name' => 'Soft Edition',
        'price' => 100000,
        'cost' => 50000,
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$sizeL->id],
        'warna' => [$this->warnaBlue->id],
        'jahit' => [],
    ]);

    $this->actingAs($this->user)
        ->post(route('restock.sheets.sync', $sheet))
        ->assertRedirect();

    expect($sheet->fresh()->cells)->toHaveCount(5);
});

test('sheet show page groups cells by parent pcode', function () {
    createAssetLancarSkus($this);
    createAssetLancarSkus($this, 'ELBOW-07', 'Elbow Support v2');

    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);

    $this->actingAs($this->user)
        ->get(route('restock.sheets.show', $sheet))
        ->assertOk()
        ->assertSee('ELBOW-03')
        ->assertSee('ELBOW-07')
        ->assertSee('ELBOW-03-BLUE-S')
        ->assertSee('ELBOW-07-RED-M');
});

test('cannot create duplicate sheet for same type', function () {
    createAssetLancarSkus($this);
    app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);

    $this->actingAs($this->user)
        ->from(route('restock.type.show', $this->typeTag))
        ->post(route('restock.sheets.store', $this->typeTag))
        ->assertRedirect()
        ->assertSessionHas('error');
});

test('manufactured type tag route returns 404', function () {
    $this->actingAs($this->user)
        ->get(route('restock.type.show', $this->manufacturedTypeTag))
        ->assertNotFound();
});

test('asset lancar create requires type tag', function () {
    Permission::firstOrCreate(['name' => 'assetLancar-create']);
    $this->user->givePermissionTo('assetLancar-create');

    $this->actingAs($this->user)
        ->post(route('assetlancar.store'), [
            'pcode' => 'ELBOW-04',
            'type' => ItemType::ASSET_LANCAR->value,
            'product_name' => 'Test Product',
            'cost' => 1000,
            'price' => 2000,
            'tags' => [
                'warna' => [$this->warnaBlue->id],
                'sizes' => [$this->sizeS->id],
            ],
        ])
        ->assertSessionHasErrors('tags.types');
});

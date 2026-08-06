<?php

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\RestockCellHistory;
use App\Models\RestockSheet;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\ItemService;
use App\Services\Restock\RestockGridBuilder;
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
        'item_type' => ItemType::ASSET_LANCAR->value,
    ]);
    $this->manufacturedTypeTag = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'code' => 'AJD',
        'name' => 'Jacket',
        'item_type' => ItemType::ITEM->value,
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

function seedRestockReceiveSettings(Addrbook $supplier, Addrbook $warehouse): void
{
    Setting::updateOrCreate(['slug' => 'restock.default_supplier_id'], [
        'group' => 'Restock',
        'name' => 'Default Supplier',
        'value' => $supplier->id,
    ]);
    Setting::updateOrCreate(['slug' => 'restock.default_receiver_id'], [
        'group' => 'Restock',
        'name' => 'Default Receiver (Warehouse)',
        'value' => $warehouse->id,
    ]);
}

test('type tabs only include asset lancar tags with item_type 2', function () {
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

test('type landing lists parent pcodes under the type', function () {
    createAssetLancarSkus($this);
    createAssetLancarSkus($this, 'ELBOW-07', 'Elbow Support v2');

    $this->actingAs($this->user)
        ->get(route('restock.type.show', $this->typeTag))
        ->assertOk()
        ->assertSee('ELBOW-03')
        ->assertSee('ELBOW-07')
        ->assertSee('Start tracking Elbow');
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

test('sheet save writes cell histories', function () {
    createAssetLancarSkus($this);
    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    $cell = $sheet->cells()->first();

    $this->actingAs($this->user)
        ->putJson(route('restock.sheets.update', $sheet), [
            'cells' => [
                ['id' => $cell->id, 'qty_restock' => 25, 'qty_production' => 0, 'qty_shipped' => 0],
            ],
        ])
        ->assertSuccessful();

    expect($cell->fresh()->qty_restock)->toBe(25);
    expect(RestockCellHistory::where('restock_cell_id', $cell->id)->count())->toBe(1);
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

test('grid groups legacy full-sku pcodes into parent color rows and size columns', function () {
    createAssetLancarSkus($this);

    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    $grid = app(RestockGridBuilder::class)->build($sheet);

    expect($grid['parents'])->toHaveCount(1);
    expect($grid['parents'][0]['pcode'])->toBe('ELBOW-03');
    expect($grid['parents'][0]['sizes'])->toBe(['S', 'M']);
    expect($grid['parents'][0]['rows'])->toHaveCount(2);

    $colors = collect($grid['parents'][0]['rows'])->pluck('color_name')->sort()->values()->all();
    expect($colors)->toBe(['BLUE', 'RED']);

    $blueRow = collect($grid['parents'][0]['rows'])->firstWhere('color_name', 'BLUE');
    expect($blueRow)->toHaveKeys(['s_restock', 'm_restock', 's_production', 'm_production', 'restock_total']);
    expect($blueRow['restock_total'])->toBe(0);
});

test('grid resolves parent from code when pcode stores the full sku', function () {
    $group = \App\Models\ItemGroup::factory()->create(['name' => 'Lifting Belt v17']);

    foreach (['GREEN' => 'XL', 'BLACK' => 'S'] as $color => $size) {
        $item = Item::factory()->create([
            'group_id' => $group->id,
            'type' => ItemType::ASSET_LANCAR,
            'pcode' => "LIFTINGBELT-17-{$color}-{$size}",
            'code' => "LIFTINGBELT-17-{$color}-{$size}",
            'name' => "LIFTING BELT - {$color} - {$size}",
        ]);
        $item->tags()->sync([
            $this->typeTag->id,
            Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => $color, 'name' => $color])->id,
            Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => $size, 'name' => $size])->id,
        ]);
    }

    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    $grid = app(RestockGridBuilder::class)->build($sheet);

    expect($grid['parents'])->toHaveCount(1);
    expect($grid['parents'][0]['pcode'])->toBe('LIFTINGBELT-17');
    expect($grid['parents'][0]['sizes'])->toBe(['S', 'XL']);
    expect(collect($grid['parents'][0]['rows'])->pluck('color_name')->sort()->values()->all())
        ->toBe(['BLACK', 'GREEN']);
});

test('move transfers restock qty to production for selected cells', function () {
    createAssetLancarSkus($this);
    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);

    $cell = $sheet->cells()->first();
    $cell->update(['qty_restock' => 40, 'qty_production' => 5]);

    $this->actingAs($this->user)
        ->postJson(route('restock.sheets.move', $sheet), [
            'direction' => 'to_production',
            'cells' => [['id' => $cell->id]],
        ])
        ->assertSuccessful()
        ->assertJsonPath('moved', 40);

    $cell->refresh();
    expect($cell->qty_restock)->toBe(0);
    expect($cell->qty_production)->toBe(45);

    expect(RestockCellHistory::where('restock_cell_id', $cell->id)->where('action', 'move')->count())->toBe(2);
});

test('move transfers production qty to shipped', function () {
    createAssetLancarSkus($this);
    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);

    $cell = $sheet->cells()->first();
    $cell->update(['qty_production' => 30, 'qty_shipped' => 10]);

    $this->actingAs($this->user)
        ->postJson(route('restock.sheets.move', $sheet), [
            'direction' => 'to_shipped',
            'cells' => [['id' => $cell->id]],
        ])
        ->assertSuccessful()
        ->assertJsonPath('moved', 30);

    $cell->refresh();
    expect($cell->qty_production)->toBe(0);
    expect($cell->qty_shipped)->toBe(40);
});

test('receive partial qty records shortfall as missing', function () {
    createAssetLancarSkus($this);

    $supplier = Addrbook::factory()->supplier()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    seedRestockReceiveSettings($supplier, $warehouse);

    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    $cell = $sheet->cells()->with('item')->first();
    $cell->update(['qty_shipped' => 100]);

    $this->actingAs($this->user)
        ->postJson(route('restock.sheets.receive', $sheet), [
            'date' => now()->toDateString(),
            'cells' => [['id' => $cell->id, 'qty' => 98]],
        ])
        ->assertSuccessful();

    $cell->refresh();
    expect($cell->qty_shipped)->toBe(0);
    expect($cell->qty_missing)->toBe(2);
    expect($cell->missing_at)->not->toBeNull();

    $transaction = Transaction::latest('id')->first();
    expect((float) $transaction->details->first()->quantity)->toBe(98.0);

    expect(RestockCellHistory::where('restock_cell_id', $cell->id)->where('action', 'missing')->count())->toBe(1);
});

test('missing page lists shortfall skus and mark found clears qty', function () {
    createAssetLancarSkus($this);
    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    $cell = $sheet->cells()->with('item')->first();
    $cell->update([
        'qty_missing' => 3,
        'missing_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('restock.type.missing', $this->typeTag))
        ->assertOk()
        ->assertSee($cell->item->code)
        ->assertSee('Mark found');

    $this->actingAs($this->user)
        ->post(route('restock.missing.found', $cell))
        ->assertRedirect()
        ->assertSessionHas('success');

    $cell->refresh();
    expect($cell->qty_missing)->toBe(0);
    expect($cell->missing_at)->toBeNull();
    expect(RestockCellHistory::where('restock_cell_id', $cell->id)->where('action', 'found')->count())->toBe(1);
});

test('restock settings page shows saved supplier and receiver names', function () {
    $supplier = Addrbook::factory()->supplier()->create(['name' => 'Supplier Tersimpan']);
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Tersimpan']);

    Setting::updateOrCreate(['slug' => 'restock.default_supplier_id'], [
        'group' => 'Restock',
        'name' => 'Default Supplier',
        'value' => $supplier->id,
    ]);
    Setting::updateOrCreate(['slug' => 'restock.default_receiver_id'], [
        'group' => 'Restock',
        'name' => 'Default Receiver (Warehouse)',
        'value' => $warehouse->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('restock.settings.edit'))
        ->assertOk()
        ->assertSee('Supplier Tersimpan', false)
        ->assertSee('Gudang Tersimpan', false);
});

test('receive creates buy transaction and decrements shipped qty', function () {
    createAssetLancarSkus($this);

    $supplier = Addrbook::factory()->supplier()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    seedRestockReceiveSettings($supplier, $warehouse);

    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    $cell = $sheet->cells()->with('item')->first();
    $cell->update(['qty_shipped' => 12]);

    $this->actingAs($this->user)
        ->postJson(route('restock.sheets.receive', $sheet), [
            'date' => now()->toDateString(),
            'cells' => [['id' => $cell->id]],
        ])
        ->assertSuccessful()
        ->assertJsonStructure(['transaction_id', 'transaction_url', 'grid']);

    $cell->refresh();
    expect($cell->qty_shipped)->toBe(0);

    $transaction = Transaction::latest('id')->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->type->value)->toBe(1);
    expect($transaction->sender_id)->toBe($supplier->id);
    expect($transaction->receiver_id)->toBe($warehouse->id);
    expect($transaction->details)->toHaveCount(1);
    expect((float) $transaction->details->first()->quantity)->toBe(12.0);

    expect((float) WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $cell->item_id)->value('quantity'))
        ->toBe(12.0);

    expect(RestockCellHistory::where('restock_cell_id', $cell->id)->where('action', 'receive')->count())->toBe(1);
});

test('receive fails when restock settings are not configured', function () {
    createAssetLancarSkus($this);
    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    $cell = $sheet->cells()->first();
    $cell->update(['qty_shipped' => 5]);

    $this->actingAs($this->user)
        ->postJson(route('restock.sheets.receive', $sheet), [
            'date' => now()->toDateString(),
            'cells' => [['id' => $cell->id]],
        ])
        ->assertStatus(422);
});

test('sheet show page includes tabulator grid payload', function () {
    createAssetLancarSkus($this);
    createAssetLancarSkus($this, 'ELBOW-07', 'Elbow Support v2');

    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);

    $this->actingAs($this->user)
        ->get(route('restock.sheets.show', $sheet))
        ->assertOk()
        ->assertSee('parent-ELBOW-03', false)
        ->assertSee('parent-ELBOW-07', false)
        ->assertSee('tabulator-tables', false)
        ->assertSee('Save sheet', false);
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

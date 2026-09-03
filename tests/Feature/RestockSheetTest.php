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
    expect($blueRow)->toHaveKeys(['s_restock', 'm_restock', 's_production', 'm_production', 'restock_total', 'production_total', 'shipped_total', 'stock_total', 's_stock']);
    expect($blueRow['restock_total'])->toBe(0);
});

test('grid blocks combine sized parents into one matrix with union sizes', function () {
    createAssetLancarSkus($this);
    createAssetLancarSkus($this, 'ELBOW-07', 'Elbow Support v2');

    $sizeL = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'L', 'name' => 'L']);
    app(ItemService::class)->create((object) [
        'pcode' => 'ELBOW-07',
        'type' => ItemType::ASSET_LANCAR->value,
        'product_name' => 'Elbow Support v2',
        'price' => 100000,
        'cost' => 50000,
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$sizeL->id],
        'warna' => [$this->warnaBlue->id],
        'jahit' => [],
    ]);

    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    $grid = app(RestockGridBuilder::class)->build($sheet);

    expect($grid['blocks'])->toHaveCount(1);
    expect($grid['blocks'][0]['kind'])->toBe('matrix');
    expect($grid['blocks'][0]['sizes'])->toBe(['S', 'M', 'L']);

    $dataRows = collect($grid['blocks'][0]['rows'])->where('_type', 'data')->values();
    expect($dataRows)->not->toBeEmpty();

    $elbow03Row = $dataRows->first(fn (array $row) => $row['pcode'] === 'ELBOW-03');
    expect($elbow03Row['parent_sizes'])->toBe(['S', 'M']);
    expect($elbow03Row)->not->toHaveKey('l_restock');

    $sectionPcodes = collect($grid['blocks'][0]['rows'])
        ->where('_type', 'section')
        ->pluck('pcode')
        ->all();
    expect($sectionPcodes)->toBe(['ELBOW-03', 'ELBOW-07']);
});

test('grid blocks separate no-size parents into a flat table', function () {
    $sizeAs = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'AS', 'name' => 'All Size']);

    createAssetLancarSkus($this);

    app(ItemService::class)->create((object) [
        'pcode' => 'ELBOW-99',
        'type' => ItemType::ASSET_LANCAR->value,
        'product_name' => 'Elbow Sleeve',
        'price' => 50000,
        'cost' => 25000,
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$sizeAs->id],
        'warna' => [$this->warnaBlue->id],
        'jahit' => [],
    ]);

    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    $grid = app(RestockGridBuilder::class)->build($sheet);

    expect($grid['blocks'])->toHaveCount(2);

    $matrixBlock = collect($grid['blocks'])->firstWhere('kind', 'matrix');
    $flatBlock = collect($grid['blocks'])->firstWhere('kind', 'flat');

    expect($matrixBlock)->not->toBeNull();
    expect($flatBlock)->not->toBeNull();
    expect($flatBlock['title'])->toBe('No size');

    $flatDataRow = collect($flatBlock['rows'])->first(fn (array $row) => $row['_type'] === 'data');
    expect($flatDataRow['pcode'])->toBe('ELBOW-99');
    expect($flatDataRow)->toHaveKeys(['restock', 'production', 'shipped', 'stock']);
});

test('sheet show page includes unified tabulator block grids', function () {
    createAssetLancarSkus($this);
    createAssetLancarSkus($this, 'ELBOW-07', 'Elbow Support v2');

    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);

    $this->actingAs($this->user)
        ->get(route('restock.sheets.show', $sheet))
        ->assertOk()
        ->assertSee('data-block-grid="matrix-alpha"', false)
        ->assertSee('ELBOW-03', false)
        ->assertSee('ELBOW-07', false)
        ->assertDontSee('data-parent-grid=', false)
        ->assertSee('vendor/tabulator/tabulator.min.js', false)
        ->assertSee('Save sheet', false)
        ->assertSee('Export Excel', false)
        ->assertSee('Stock', false)
        ->assertSee('data-testid="restock-sku-lookup"', false);
});

test('grid includes warehouse stock from configured warehouses', function () {
    createAssetLancarSkus($this);

    $warehouse = Addrbook::factory()->warehouse()->create();
    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    $cell = $sheet->cells()->with('item')->first();

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => $warehouse->type,
        'item_id' => $cell->item_id,
        'quantity' => 17,
    ]);

    Setting::updateOrCreate(['slug' => 'restock.default_warehouse_ids'], [
        'group' => 'Restock',
        'name' => 'Stock Display Warehouses',
        'value' => [$warehouse->id],
    ]);

    $grid = app(RestockGridBuilder::class)->build($sheet->fresh());
    $matched = false;

    foreach ($grid['parents'][0]['rows'] as $row) {
        foreach ($row['_meta'] as $prefix => $meta) {
            if (($meta['cell_id'] ?? null) === $cell->id) {
                expect($row[$prefix.'stock'])->toBe(17);
                $matched = true;
            }
        }
    }

    expect($matched)->toBeTrue();
});

test('sheet export returns xlsx download with all parent sections', function () {
    createAssetLancarSkus($this);
    createAssetLancarSkus($this, 'ELBOW-07', 'Elbow Support v2');
    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);

    $response = $this->actingAs($this->user)
        ->get(route('restock.sheets.export', $sheet));

    $response->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $tempPath = tempnam(sys_get_temp_dir(), 'restock-export-').'.xlsx';
    file_put_contents($tempPath, $response->streamedContent());

    $worksheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempPath)->getActiveSheet();
    $values = [];
    foreach ($worksheet->getRowIterator() as $row) {
        foreach ($row->getCellIterator() as $cell) {
            $value = $cell->getValue();
            if ($value !== null && $value !== '') {
                $values[] = (string) $value;
            }
        }
    }

    expect($values)->toContain('ELBOW-03');
    expect($values)->toContain('ELBOW-07');

    @unlink($tempPath);
});

test('grid includes parent image url', function () {
    createAssetLancarSkus($this);
    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);

    $grid = app(RestockGridBuilder::class)->build($sheet);

    expect($grid['parents'][0]['image_url'])->toBeString()->not->toBeEmpty();
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

test('move json response includes non-empty grid for table refresh', function () {
    createAssetLancarSkus($this);
    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    $cell = $sheet->cells()->first();
    $cell->update(['qty_restock' => 12]);

    $response = $this->actingAs($this->user)
        ->postJson(route('restock.sheets.move', $sheet), [
            'direction' => 'to_production',
            'cells' => [['id' => $cell->id]],
        ])
        ->assertSuccessful();

    $grid = $response->json('grid');
    expect($grid['blocks'])->not->toBeEmpty();
    expect(count($grid['blocks'][0]['rows'] ?? []))->toBeGreaterThan(0);
    expect(collect($grid['blocks'][0]['rows'])->contains(fn (array $row) => $row['_type'] === 'data'))->toBeTrue();
    expect(collect($grid['blocks'][0]['rows'])->contains(fn (array $row) => isset($row['_rowKey'])))->toBeTrue();
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

test('receive multiple cells without invoice and mixed quantities', function () {
    createAssetLancarSkus($this);

    $supplier = Addrbook::factory()->supplier()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    seedRestockReceiveSettings($supplier, $warehouse);

    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    $cells = $sheet->cells()->with('item')->limit(2)->get();
    $cells[0]->update(['qty_shipped' => 100]);
    $cells[1]->update(['qty_shipped' => 100]);

    $response = $this->actingAs($this->user)
        ->postJson(route('restock.sheets.receive', $sheet), [
            'date' => now()->toDateString(),
            'invoice' => '',
            'cells' => [
                ['id' => $cells[0]->id, 'qty' => 99],
                ['id' => $cells[1]->id, 'qty' => 100],
            ],
        ])
        ->assertSuccessful();

    $cells[0]->refresh();
    $cells[1]->refresh();
    expect($cells[0]->qty_shipped)->toBe(0);
    expect($cells[0]->qty_missing)->toBe(1);
    expect($cells[1]->qty_shipped)->toBe(0);
    expect($cells[1]->qty_missing)->toBe(0);

    $transaction = Transaction::find($response->json('transaction_id'));
    expect($transaction)->not->toBeNull();
    expect($transaction->details)->toHaveCount(2);
    expect((float) $transaction->details->firstWhere('item_id', $cells[0]->item_id)->quantity)->toBe(99.0);
    expect((float) $transaction->details->firstWhere('item_id', $cells[1]->item_id)->quantity)->toBe(100.0);

    $grid = $response->json('grid');
    expect($grid['blocks'])->not->toBeEmpty();
    expect(count($grid['blocks'][0]['rows'] ?? []))->toBeGreaterThan(0);
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
    expect($transaction->type)->toBe(1);
    expect($transaction->sender_id)->toBe($supplier->id);
    expect($transaction->receiver_id)->toBe($warehouse->id);
    expect($transaction->details)->toHaveCount(1);
    expect((float) $transaction->details->first()->quantity)->toBe(12.0);

    expect((float) WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $cell->item_id)->value('quantity'))
        ->toBe(12.0);

    expect((float) $transaction->total)->toBe(
        (float) Transaction::signedAmount(Transaction::TYPE_BUY, 12 * (float) $cell->item->cost)
    );

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

test('sheet lookup resolves a cell by canonical code and legacy_code', function () {
    createAssetLancarSkus($this);
    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    $cell = $sheet->cells()->with('item')->first();
    $cell->item->update(['legacy_code' => 'OLD-ELBOW-BARCODE']);

    $this->actingAs($this->user)
        ->getJson(route('restock.sheets.lookup', $sheet).'?code='.urlencode($cell->item->code))
        ->assertSuccessful()
        ->assertJsonPath('item.id', $cell->item_id)
        ->assertJsonPath('cell.id', $cell->id);

    $this->actingAs($this->user)
        ->getJson(route('restock.sheets.lookup', $sheet).'?code=old-elbow-barcode')
        ->assertSuccessful()
        ->assertJsonPath('item.id', $cell->item_id)
        ->assertJsonPath('item.legacy_code', 'OLD-ELBOW-BARCODE')
        ->assertJsonPath('cell.id', $cell->id);

    $this->actingAs($this->user)
        ->getJson(route('restock.sheets.lookup', $sheet).'?code=UNKNOWN-SKU')
        ->assertSuccessful()
        ->assertJsonPath('item', null)
        ->assertJsonPath('cell', null);
});

test('sync skus can add a variant resolved by legacy_code', function () {
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

    $newItem = Item::query()
        ->where('type', ItemType::ASSET_LANCAR)
        ->whereHas('tags', fn ($q) => $q->where('tags.id', $sizeL->id))
        ->first();
    $newItem->update(['legacy_code' => 'OLD-ELBOW-L']);

    $this->actingAs($this->user)
        ->from(route('restock.sheets.show', $sheet))
        ->post(route('restock.sheets.sync', $sheet), ['skus' => ['OLD-ELBOW-L']])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($sheet->fresh()->cells)->toHaveCount(5);
    expect($sheet->fresh()->cells()->where('item_id', $newItem->id)->exists())->toBeTrue();
});

test('receive by legacy_code uses configured parties and signed buy total', function () {
    createAssetLancarSkus($this);

    $supplier = Addrbook::factory()->supplier()->create(['name' => 'Pabrik Konfigurasi']);
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Konfigurasi']);
    seedRestockReceiveSettings($supplier, $warehouse);

    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    $cell = $sheet->cells()->with('item')->first();
    $cell->item->update(['legacy_code' => 'OLD-ELBOW-RECV']);
    $cell->update(['qty_shipped' => 7]);

    $this->actingAs($this->user)
        ->postJson(route('restock.sheets.receive', $sheet), [
            'date' => now()->toDateString(),
            'cells' => [['sku' => 'OLD-ELBOW-RECV', 'qty' => 7]],
        ])
        ->assertSuccessful()
        ->assertJsonStructure(['transaction_id', 'transaction_url', 'grid']);

    $cell->refresh();
    expect($cell->qty_shipped)->toBe(0);

    $transaction = Transaction::latest('id')->first();
    expect($transaction->sender_id)->toBe($supplier->id);
    expect($transaction->receiver_id)->toBe($warehouse->id);
    expect($transaction->type)->toBe(Transaction::TYPE_BUY);
    expect((float) $transaction->details->first()->quantity)->toBe(7.0);
    expect((float) $transaction->total)->toBe(
        (float) Transaction::signedAmount(Transaction::TYPE_BUY, 7 * (float) $cell->item->cost)
    );
    expect((float) WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $cell->item_id)->value('quantity'))
        ->toBe(7.0);
});

test('receive rejects qty above shipped with a clear message', function () {
    createAssetLancarSkus($this);

    $supplier = Addrbook::factory()->supplier()->create();
    $warehouse = Addrbook::factory()->warehouse()->create();
    seedRestockReceiveSettings($supplier, $warehouse);

    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    $cell = $sheet->cells()->with('item')->first();
    $cell->update(['qty_shipped' => 10]);

    $response = $this->actingAs($this->user)
        ->postJson(route('restock.sheets.receive', $sheet), [
            'date' => now()->toDateString(),
            'cells' => [['id' => $cell->id, 'qty' => 12]],
        ])
        ->assertStatus(422);

    expect($response->json('message'))->toContain('Insufficient shipped qty');
    expect($cell->fresh()->qty_shipped)->toBe(10);
    expect(Transaction::count())->toBe(0);
});

test('sheet show lists configured receive parties', function () {
    createAssetLancarSkus($this);
    $supplier = Addrbook::factory()->supplier()->create(['name' => 'Pabrik Utama']);
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang Terima']);
    seedRestockReceiveSettings($supplier, $warehouse);

    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);

    $this->actingAs($this->user)
        ->get(route('restock.sheets.show', $sheet))
        ->assertOk()
        ->assertSee('Pabrik Utama', false)
        ->assertSee('Gudang Terima', false)
        ->assertSee('data-testid="restock-sku-lookup"', false);
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

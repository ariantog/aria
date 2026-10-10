<?php

use App\Enums\ItemType;
use App\Models\Tag;
use App\Models\User;
use App\Services\ItemService;
use App\Services\Restock\RestockFlatExportService;
use App\Services\Restock\RestockFlatImportParser;
use App\Services\Restock\RestockFlatImportService;
use App\Services\Restock\RestockSheetService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    foreach (['restock-list', 'restock-create', 'restock-edit', 'restock-export'] as $perm) {
        Permission::firstOrCreate(['name' => $perm]);
    }
    $this->user->givePermissionTo(['restock-list', 'restock-create', 'restock-edit', 'restock-export']);

    $this->typeTag = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'code' => 'ELBOW',
        'name' => 'Elbow',
        'item_type' => ItemType::ASSET_LANCAR->value,
    ]);
    $this->warnaBlue = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLUE', 'name' => 'BLUE']);
    $this->sizeS = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'S', 'name' => 'S']);

    app(ItemService::class)->create((object) [
        'pcode' => 'ELBOW-03',
        'type' => ItemType::ASSET_LANCAR->value,
        'product_name' => 'Soft Edition',
        'price' => 100000,
        'cost' => 50000,
    ], [
        'types' => [$this->typeTag->id],
        'sizes' => [$this->sizeS->id],
        'warna' => [$this->warnaBlue->id],
        'jahit' => [],
    ]);
});

test('flat export lists skus with restock quantity across all sheets', function () {
    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    $cell = $sheet->cells()->first();
    $cell->update(['qty_restock' => 12]);
    $item = $cell->item;

    $this->actingAs($this->user)
        ->get(route('restock.export-flat', ['source' => 'restock']))
        ->assertSuccessful()
        ->assertHeader('content-disposition');

    $rows = app(RestockFlatExportService::class)->collectRows('qty_restock');
    expect($rows)->toHaveCount(1);
    expect($rows[0]['sku'])->toBe($item->code);
    expect($rows[0]['quantity'])->toBe(12);
});

test('flat import preview and apply moves restock to production', function () {
    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    $cell = $sheet->cells()->first();
    $cell->update(['qty_restock' => 20, 'qty_production' => 0]);
    $sku = $cell->item->code;

    $csv = "sku,quantity\n{$sku},15\n";
    $path = sys_get_temp_dir().'/restock-flat-'.uniqid().'.csv';
    file_put_contents($path, $csv);

    $parsed = app(RestockFlatImportParser::class)->parse($path);
    $preview = app(RestockFlatImportService::class)->preview('to_production', $parsed);

    expect($preview['can_apply'])->toBeTrue();
    expect($preview['summary']['moving'])->toBe(15);

    $result = app(RestockFlatImportService::class)->apply('to_production', $parsed, $this->user);
    expect($result['moved'])->toBe(15);

    $cell->refresh();
    expect($cell->qty_restock)->toBe(5);
    expect($cell->qty_production)->toBe(15);

    @unlink($path);
});

test('flat import rejects quantity above available restock', function () {
    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    $cell = $sheet->cells()->first();
    $cell->update(['qty_restock' => 5]);
    $sku = $cell->item->code;

    $csv = "sku,quantity\n{$sku},10\n";
    $path = sys_get_temp_dir().'/restock-flat-'.uniqid().'.csv';
    file_put_contents($path, $csv);

    $parsed = app(RestockFlatImportParser::class)->parse($path);
    $preview = app(RestockFlatImportService::class)->preview('to_production', $parsed);

    expect($preview['can_apply'])->toBeFalse();
    expect($preview['summary']['errors'])->toBe(1);

    @unlink($path);
});

test('flat import apply moves production to shipped', function () {
    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    $cell = $sheet->cells()->first();
    $cell->update(['qty_restock' => 0, 'qty_production' => 8, 'qty_shipped' => 0]);
    $sku = $cell->item->code;

    $csv = "sku,quantity\n{$sku},8\n";
    $path = sys_get_temp_dir().'/restock-flat-'.uniqid().'.csv';
    file_put_contents($path, $csv);

    $parsed = app(RestockFlatImportParser::class)->parse($path);
    app(RestockFlatImportService::class)->apply('to_shipped', $parsed, $this->user);

    $cell->refresh();
    expect($cell->qty_production)->toBe(0);
    expect($cell->qty_shipped)->toBe(8);

    @unlink($path);
});

test('flat import preview endpoint accepts uploaded xlsx', function () {
    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    $cell = $sheet->cells()->first();
    $cell->update(['qty_restock' => 10]);
    $sku = $cell->item->code;

    $exportPath = sys_get_temp_dir().'/restock-flat-export-'.uniqid().'.xlsx';
    $response = app(RestockFlatExportService::class)->downloadAll('restock');
    ob_start();
    $response->sendContent();
    file_put_contents($exportPath, ob_get_clean());

    $worksheet = IOFactory::load($exportPath)->getActiveSheet();
    expect($worksheet->getCell('A2')->getValue())->toBe($sku);

    $this->actingAs($this->user)
        ->post(route('restock.import-flat.preview'), [
            'direction' => 'to_production',
            'file' => new Illuminate\Http\UploadedFile($exportPath, 'flat.xlsx', null, null, true),
        ])
        ->assertSuccessful()
        ->assertJsonPath('can_apply', true)
        ->assertJsonPath('summary.moving', 10);

    @unlink($exportPath);
});

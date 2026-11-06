<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemParentPrice;
use App\Models\Tag;

beforeEach(function () {
    $this->typeTag = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'code' => 'AJD',
        'name' => 'Jacket',
    ]);
    $this->sizeTag = Tag::factory()->create([
        'type' => Tag::TYPE_SIZE,
        'code' => 'M',
        'name' => 'Medium',
    ]);
    $this->warnaTag = Tag::factory()->create([
        'type' => Tag::TYPE_WARNA,
        'code' => 'BLACK',
        'name' => 'BLACK',
    ]);

    $this->group = ItemGroup::factory()->create([
        'name' => 'CX90032-01',
        'master' => 'CX90032-01',
        'variant' => '01',
    ]);

    $this->parentKey = '1:AJD:CX90032';

    ItemParentPrice::query()->create([
        'parent_key' => $this->parentKey,
        'product_name' => 'RUNNING SHIRT',
    ]);
});

it('sets alias from parent when item has no warna tag', function () {
    $item = Item::factory()->create([
        'group_id' => $this->group->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX90032-01',
        'code' => 'AJD-CX90032-01-M',
        'alias' => '',
        'name' => 'OLD',
    ]);
    $item->tags()->sync([$this->typeTag->id, $this->sizeTag->id]);

    $path = base_path('tests/Fixtures/backfill-alias-ids.xlsx');
    $this->assertDirectoryExists(dirname($path));
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'id');
    $sheet->setCellValue('A2', (string) $item->id);
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($path);

    $this->artisan('items:backfill-alias-from-parent-excel', ['file' => $path])
        ->assertSuccessful();

    $item->refresh();
    expect(strtoupper(trim($item->alias)))->toBe('RUNNING SHIRT')
        ->and(strtoupper($item->name))->toContain('RUNNING SHIRT')
        ->and(strtoupper($item->name))->toContain('M');

    @unlink($path);
});

it('sets alias to parent title when warna is tagged and rebuilds name with color', function () {
    $item = Item::factory()->create([
        'group_id' => $this->group->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX90032-01',
        'code' => 'AJD-CX90032-01-M',
        'alias' => 'OLD ALIAS',
        'name' => 'OLD',
    ]);
    $item->tags()->sync([$this->typeTag->id, $this->sizeTag->id, $this->warnaTag->id]);

    $path = base_path('tests/Fixtures/backfill-alias-ids-warna.xlsx');
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', $item->id);
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($path);

    $this->artisan('items:backfill-alias-from-parent-excel', ['file' => $path])
        ->assertSuccessful();

    $item->refresh();
    expect(strtoupper(trim((string) $item->alias)))->toBe('RUNNING SHIRT')
        ->and(strtoupper($item->name))->toContain('RUNNING SHIRT')
        ->and(strtoupper($item->name))->toContain('BLACK')
        ->and(strtoupper($item->name))->toContain('M');

    @unlink($path);
});

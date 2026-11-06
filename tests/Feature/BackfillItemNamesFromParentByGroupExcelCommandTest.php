<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemParentPrice;
use App\Models\Tag;
use Illuminate\Support\Facades\Schema;

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

it('updates all SKUs in listed groups from parent product_name', function () {
    $itemA = Item::factory()->create([
        'group_id' => $this->group->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX90032-01',
        'code' => 'AJD-CX90032-01-M',
        'name' => 'OLD A',
    ]);
    $itemA->tags()->sync([$this->typeTag->id, $this->sizeTag->id]);

    $path = base_path('tests/Fixtures/backfill-by-group.xlsx');
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', (string) $this->group->id);
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($path);

    $this->artisan('items:backfill-names-from-parent-by-group-excel', ['file' => $path])
        ->assertSuccessful();

    $itemA->refresh();
    expect(strtoupper($itemA->name))->toContain('RUNNING SHIRT')
        ->and(strtoupper($itemA->name))->toContain('M');

    if (Schema::hasColumn('items', 'alias')) {
        expect(strtoupper(trim((string) $itemA->alias)))->toBe('RUNNING SHIRT');
    }

    @unlink($path);
});

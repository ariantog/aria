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

it('updates SKUs in sibling colorway groups under the same parent when only one group id is listed', function () {
    $group02 = ItemGroup::factory()->create([
        'name' => 'CX90032-02',
        'master' => 'CX90032-02',
        'variant' => '02',
    ]);
    $itemA = Item::factory()->create([
        'group_id' => $this->group->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX90032-01',
        'code' => 'AJD-CX90032-01-M',
        'name' => 'OLD A',
    ]);
    $itemA->tags()->sync([$this->typeTag->id, $this->sizeTag->id]);
    $itemB = Item::factory()->create([
        'group_id' => $group02->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX90032-02',
        'code' => 'AJD-CX90032-02-M',
        'name' => 'OLD B',
    ]);
    $itemB->tags()->sync([$this->typeTag->id, $this->sizeTag->id]);

    $path = base_path('tests/Fixtures/backfill-by-group-sibling.xlsx');
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', (string) $this->group->id);
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($path);

    $this->artisan('items:backfill-names-from-parent-by-group-excel', ['file' => $path])
        ->assertSuccessful();

    $itemA->refresh();
    $itemB->refresh();
    expect(strtoupper($itemA->name))->toContain('RUNNING SHIRT')
        ->and(strtoupper($itemB->name))->toContain('RUNNING SHIRT');

    @unlink($path);
});

it('syncs parent product_name from item_group.name when parent row is empty and group name is not pcode like', function () {
    ItemParentPrice::query()->where('parent_key', $this->parentKey)->update(['product_name' => '']);

    $this->group->update(['name' => 'Legacy Product Title']);

    $itemA = Item::factory()->create([
        'group_id' => $this->group->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX90032-01',
        'code' => 'AJD-CX90032-01-M',
        'name' => 'OLD A',
    ]);
    $itemA->tags()->sync([$this->typeTag->id, $this->sizeTag->id]);

    $path = base_path('tests/Fixtures/backfill-by-group-fallback.xlsx');
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', (string) $this->group->id);
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($path);

    $this->artisan('items:backfill-names-from-parent-by-group-excel', ['file' => $path])
        ->assertSuccessful();

    $this->group->refresh();
    $itemA->refresh();

    expect($this->group->name)->toBe('Legacy Product Title');

    $row = ItemParentPrice::query()->where('parent_key', $this->parentKey)->first();
    expect(strtoupper(trim((string) $row->product_name)))->toBe('LEGACY PRODUCT TITLE');
    expect(strtoupper($itemA->name))->toContain('LEGACY PRODUCT TITLE');

    @unlink($path);
});

it('skips when product_name is empty and item_group.name contains hyphen or slash', function () {
    ItemParentPrice::query()->where('parent_key', $this->parentKey)->update(['product_name' => '']);

    $this->group->update(['name' => 'CX90032-01']);

    $itemA = Item::factory()->create([
        'group_id' => $this->group->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX90032-01',
        'code' => 'AJD-CX90032-01-M',
        'name' => 'OLD A',
    ]);
    $itemA->tags()->sync([$this->typeTag->id, $this->sizeTag->id]);

    $path = base_path('tests/Fixtures/backfill-by-group-skip-code.xlsx');
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', (string) $this->group->id);
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($path);

    $this->artisan('items:backfill-names-from-parent-by-group-excel', ['file' => $path])
        ->assertSuccessful();

    $itemA->refresh();
    expect($itemA->name)->toBe('OLD A');

    @unlink($path);
});

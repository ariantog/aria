<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemParentPrice;
use App\Models\Tag;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (Schema::hasColumn('items', 'alias')) {
        Schema::table('items', function ($table): void {
            $table->dropColumn('alias');
        });
    }

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

it('rebuilds name from parent title when items.alias column does not exist and there is no warna tag', function () {
    $item = Item::factory()->create([
        'group_id' => $this->group->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX90032-01',
        'code' => 'AJD-CX90032-01-M',
        'name' => 'CX90032-01 - M',
    ]);
    $item->tags()->sync([$this->typeTag->id, $this->sizeTag->id]);

    $path = base_path('tests/Fixtures/backfill-alias-no-col.xlsx');
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', (string) $item->id);
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($path);

    $this->artisan('items:backfill-alias-from-parent-excel', ['file' => $path])
        ->assertSuccessful();

    $item->refresh();
    expect(strtoupper($item->name))->toContain('RUNNING SHIRT')
        ->and(strtoupper($item->name))->toContain('M');

    @unlink($path);
});

it('rebuilds name with parent title and warna when items.alias column does not exist', function () {
    $item = Item::factory()->create([
        'group_id' => $this->group->id,
        'type' => ItemType::ITEM,
        'pcode' => 'CX90032-01',
        'code' => 'AJD-CX90032-01-M',
        'name' => 'CX90032-01 - M',
    ]);
    $item->tags()->sync([$this->typeTag->id, $this->sizeTag->id, $this->warnaTag->id]);

    $path = base_path('tests/Fixtures/backfill-alias-no-col-warna.xlsx');
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', (string) $item->id);
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($path);

    $this->artisan('items:backfill-alias-from-parent-excel', ['file' => $path])
        ->assertSuccessful();

    $item->refresh();
    expect(strtoupper($item->name))->toContain('RUNNING SHIRT')
        ->and(strtoupper($item->name))->toContain('BLACK')
        ->and(strtoupper($item->name))->toContain('M');

    @unlink($path);
});

<?php

use App\Enums\ItemType;
use App\Models\RestockSheetParentTotal;
use App\Models\Tag;
use App\Models\User;
use App\Services\ItemService;
use App\Services\Restock\RestockSheetService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    User::query()->find(User::SUPERADMIN_ID) ?? User::factory()->create(['id' => User::SUPERADMIN_ID]);

    $this->user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'restock-list']);
    Permission::firstOrCreate(['name' => 'restock-edit']);
    $this->user->givePermissionTo(['restock-list', 'restock-edit']);

    $this->typeTag = Tag::factory()->create([
        'type' => Tag::TYPE_TYPE,
        'code' => 'ELBOW',
        'name' => 'Elbow',
        'item_type' => ItemType::ASSET_LANCAR->value,
    ]);
    $this->warnaBlue = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'BLUE', 'name' => 'BLUE']);
    $this->warnaRed = Tag::factory()->create(['type' => Tag::TYPE_WARNA, 'code' => 'RED', 'name' => 'RED']);
    $this->sizeS = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'S', 'name' => 'S']);
    $this->sizeM = Tag::factory()->create(['type' => Tag::TYPE_SIZE, 'code' => 'M', 'name' => 'M']);
});

test('sheet save updates cached parent pipeline totals', function () {
    restockParentTotalsSeedSkus($this);
    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    $cell = $sheet->cells()->first();

    $this->actingAs($this->user)
        ->putJson(route('restock.sheets.update', $sheet), [
            'cells' => [
                ['id' => $cell->id, 'qty_restock' => 42, 'qty_production' => 3, 'qty_shipped' => 1],
            ],
        ])
        ->assertSuccessful();

    $row = RestockSheetParentTotal::query()
        ->where('restock_sheet_id', $sheet->id)
        ->where('parent_pcode', 'ELBOW-03')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->qty_restock)->toBe(42)
        ->and($row->qty_production)->toBe(3)
        ->and($row->qty_shipped)->toBe(1);

    $summary = app(RestockSheetService::class)->sheetSummaries()->firstWhere('id', $sheet->id);
    expect($summary['qty_restock'])->toBe(42);
});

test('recommendation save-restock refreshes cached parent totals', function () {
    restockParentTotalsSeedSkus($this);
    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    $cell = $sheet->cells()->first();

    $this->actingAs($this->user)
        ->post(route('restock.recommendations.save-restock'), [
            'item_id' => $cell->item_id,
            'qty_restock' => 77,
            'tab' => 'fast',
        ])
        ->assertRedirect(route('restock.recommendations', ['tab' => 'fast']));

    $row = RestockSheetParentTotal::query()
        ->where('restock_sheet_id', $sheet->id)
        ->where('parent_pcode', 'ELBOW-03')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->qty_restock)->toBe(77);

    $summary = app(RestockSheetService::class)->sheetSummaries()->firstWhere('id', $sheet->id);
    expect($summary['qty_restock'])->toBe(77);
});

function restockParentTotalsSeedSkus(object $context, string $pcode = 'ELBOW-03', string $productName = 'Elbow Support'): void
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

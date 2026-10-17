<?php

use App\Enums\ItemType;
use App\Models\RestockCellHistory;
use App\Models\Tag;
use App\Models\User;
use App\Services\ItemService;
use App\Services\Restock\RestockCellHistoryRecorder;
use App\Services\Restock\RestockSheetService;
use App\Support\RestockCellHistoryLabels;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    foreach (['restock-list', 'restock-create', 'restock-edit', 'restock-history'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $this->user->givePermissionTo(['restock-list', 'restock-create', 'restock-edit', 'restock-history']);

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

test('restock history page lists user sheet saves with source metadata', function () {
    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    $cell = $sheet->cells()->first();

    $this->actingAs($this->user)
        ->putJson(route('restock.sheets.update', $sheet), [
            'cells' => [
                ['id' => $cell->id, 'qty_restock' => 9],
            ],
        ])
        ->assertSuccessful();

    $history = RestockCellHistory::query()->where('restock_cell_id', $cell->id)->first();
    expect($history)->not->toBeNull()
        ->and($history->source)->toBe(RestockCellHistoryLabels::SOURCE_SHEET_SAVE)
        ->and($history->actor_type)->toBe(RestockCellHistoryLabels::ACTOR_USER)
        ->and($history->user_id)->toBe($this->user->id);

    $this->actingAs($this->user)
        ->get(route('restock.sheets.history', $sheet))
        ->assertOk()
        ->assertSee('data-testid="restock-history-table"', false)
        ->assertSee('Manual edit', false)
        ->assertSee('Restock sheet save', false)
        ->assertSee($this->user->username, false);
});

test('purge command deletes restock history older than retention window', function () {
    $sheet = app(RestockSheetService::class)->createSheet($this->typeTag, $this->user);
    $cell = $sheet->cells()->first();

    $recorder = app(RestockCellHistoryRecorder::class);
    $old = $recorder->record($cell, 'restock', 1, 2, 'edit', $this->user, RestockCellHistoryLabels::SOURCE_SHEET_SAVE);
    $old->forceFill(['created_at' => now()->subMonths(7)])->save();

    $recent = $recorder->record($cell, 'restock', 2, 3, 'edit', $this->user, RestockCellHistoryLabels::SOURCE_SHEET_SAVE);

    Artisan::call('app:purge-restock-cell-histories', ['--months' => 6]);

    expect(RestockCellHistory::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(RestockCellHistory::query()->whereKey($recent->id)->exists())->toBeTrue();
});

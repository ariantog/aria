<?php

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\User;
use App\Models\WarehouseItem;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    User::factory()->create();
    $this->user = User::factory()->create();
});

it('parses a csv and returns warehouse stock for the selected warehouse', function () {
    $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
    $item = Item::factory()->create([
        'code' => 'ACCHJ0002206L',
        'name' => 'Batch Item',
        'price' => 50_000,
    ]);
    WarehouseItem::create([
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => Addrbook::class,
        'quantity' => 12,
    ]);

    $csv = UploadedFile::fake()->createWithContent('batch.csv', "ACCHJ0002206L,2,0\n");

    $response = $this->actingAs($this->user)
        ->postJson(route('transactions.batch-parse'), [
            'csv_file' => $csv,
            'warehouse_id' => $warehouse->id,
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.0.code', 'ACCHJ0002206L')
        ->assertJsonPath('data.0.quantity', 2)
        ->assertJsonPath('data.0.price', 0)
        ->assertJsonPath('data.0.warehouse_stock', 12)
        ->assertJsonPath('data.0.warehouse_item.0.warehouse_id', (string) $warehouse->id);

    expect((float) $response->json('data.0.warehouse_item.0.quantity'))->toBe(12.0);
});

it('resolves csv codes via legacy sku and skips a header row', function () {
    $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
    $item = Item::factory()->create([
        'code' => 'NEW-SKU-01',
        'legacy_code' => 'LEGACY-SKU-01',
        'name' => 'Legacy Item',
    ]);
    WarehouseItem::create([
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => Addrbook::class,
        'quantity' => 5,
    ]);

    $csv = UploadedFile::fake()->createWithContent('batch.csv', "code,qty,price\nLEGACY-SKU-01,3,0\n");

    $response = $this->actingAs($this->user)
        ->postJson(route('transactions.batch-parse'), [
            'csv_file' => $csv,
            'warehouse_id' => $warehouse->id,
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.0.code', 'NEW-SKU-01')
        ->assertJsonPath('data.0.quantity', 3)
        ->assertJsonPath('data.0.warehouse_stock', 5);
});

it('rejects an empty csv', function () {
    $csv = UploadedFile::fake()->createWithContent('batch.csv', "code,qty,price\n");

    $this->actingAs($this->user)
        ->postJson(route('transactions.batch-parse'), ['csv_file' => $csv])
        ->assertUnprocessable()
        ->assertJsonPath('error', 'Failed to parse CSV.');
});

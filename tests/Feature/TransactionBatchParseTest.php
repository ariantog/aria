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
            'type' => 'move',
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.0.code', 'ACCHJ0002206L')
        ->assertJsonPath('data.0.quantity', 2)
        ->assertJsonPath('data.0.price', 0)
        ->assertJsonPath('data.0.warehouse_stock', 12)
        ->assertJsonPath('data.0.warehouse_item.0.warehouse_id', (string) $warehouse->id);

    expect((float) $response->json('data.0.warehouse_item.0.quantity'))->toBe(12.0);
});

it('uses csv price and sender warehouse stock for sell batch uploads', function () {
    $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
    $item = Item::factory()->create([
        'code' => 'SELL-SKU-01',
        'name' => 'Sell Item',
        'price' => 50_000,
        'cost' => 30_000,
    ]);
    WarehouseItem::create([
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => Addrbook::class,
        'quantity' => 8,
    ]);

    $csv = UploadedFile::fake()->createWithContent('batch.csv', "SELL-SKU-01,2,75000\n");

    $response = $this->actingAs($this->user)
        ->postJson(route('transactions.batch-parse'), [
            'csv_file' => $csv,
            'warehouse_id' => $warehouse->id,
            'type' => 'sell',
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.0.quantity', 2)
        ->assertJsonPath('data.0.price', 75_000)
        ->assertJsonPath('data.0.csv_price', 75_000)
        ->assertJsonPath('data.0.item_price', 50_000)
        ->assertJsonPath('data.0.warehouse_stock', 8)
        ->assertJsonPath('data.0.subtotal', 150_000);
});

it('loads item cost from the database for buy batch uploads', function () {
    $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
    $item = Item::factory()->create([
        'code' => 'BUY-SKU-01',
        'name' => 'Buy Item',
        'price' => 99_000,
        'cost' => 42_500,
    ]);
    WarehouseItem::create([
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => Addrbook::class,
        'quantity' => 3,
    ]);

    $csv = UploadedFile::fake()->createWithContent('batch.csv', "BUY-SKU-01,4,0\n");

    $response = $this->actingAs($this->user)
        ->postJson(route('transactions.batch-parse'), [
            'csv_file' => $csv,
            'warehouse_id' => $warehouse->id,
            'type' => 'buy',
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.0.quantity', 4)
        ->assertJsonPath('data.0.price', 42_500)
        ->assertJsonPath('data.0.cost', 42_500)
        ->assertJsonPath('data.0.csv_price', 0)
        ->assertJsonPath('data.0.warehouse_stock', 3)
        ->assertJsonPath('data.0.subtotal', 170_000);
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

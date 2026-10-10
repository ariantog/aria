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
        ->assertJsonPath('data.0.price', 50_000)
        ->assertJsonPath('data.0.csv_price', 0)
        ->assertJsonPath('data.0.jubelio_item_id', (int) ($item->jubelio_item_id ?? 0))
        ->assertJsonPath('data.0.warehouse_stock', 12)
        ->assertJsonPath('data.0.warehouse_item.0.warehouse_id', (string) $warehouse->id)
        ->assertJsonPath('data.0.subtotal', 100_000);

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

it('uses csv price for buy batch uploads', function () {
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

    $csv = UploadedFile::fake()->createWithContent('batch.csv', "BUY-SKU-01,4,38000\n");

    $response = $this->actingAs($this->user)
        ->postJson(route('transactions.batch-parse'), [
            'csv_file' => $csv,
            'warehouse_id' => $warehouse->id,
            'type' => 'buy',
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.0.quantity', 4)
        ->assertJsonPath('data.0.price', 38_000)
        ->assertJsonPath('data.0.csv_price', 38_000)
        ->assertJsonPath('data.0.cost', 42_500)
        ->assertJsonPath('data.0.warehouse_stock', 3)
        ->assertJsonPath('data.0.subtotal', 152_000);
});

it('prefers legacy sku over another item with the same code on sell batch upload', function () {
    $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
    Item::factory()->create(['code' => 'ACCHJ0002206L', 'legacy_code' => '']);
    $converted = Item::factory()->create([
        'code' => 'ACC-HJ00022-06-L',
        'legacy_code' => 'ACCHJ0002206L',
        'name' => 'Converted Item',
        'price' => 159_900,
    ]);
    WarehouseItem::create([
        'item_id' => $converted->id,
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => Addrbook::class,
        'quantity' => 4,
    ]);

    $csv = UploadedFile::fake()->createWithContent('batch.csv', "ACCHJ0002206L,2,159900\n");

    $this->actingAs($this->user)
        ->postJson(route('transactions.batch-parse'), [
            'csv_file' => $csv,
            'warehouse_id' => $warehouse->id,
            'type' => 'sell',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.0.code', 'ACC-HJ00022-06-L')
        ->assertJsonPath('data.0.quantity', 2)
        ->assertJsonPath('data.0.price', 159_900);
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

it('returns unmatched csv lines when sku is not in aria', function () {
    $warehouse = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);
    $item = Item::factory()->create(['code' => 'FOUND-SKU', 'legacy_code' => '']);
    WarehouseItem::create([
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'warehouse_type' => Addrbook::class,
        'quantity' => 1,
    ]);

    $csv = UploadedFile::fake()->createWithContent(
        'batch.csv',
        "FOUND-SKU,1,1000\nMISSING-A,2,2000\nMISSING-B,1,3000\n",
    );

    $response = $this->actingAs($this->user)
        ->postJson(route('transactions.batch-parse'), [
            'csv_file' => $csv,
            'warehouse_id' => $warehouse->id,
            'type' => 'sell',
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('summary.parsed_lines', 3)
        ->assertJsonPath('summary.matched_lines', 1)
        ->assertJsonPath('summary.unmatched_lines', 2)
        ->assertJsonCount(1, 'data')
        ->assertJsonCount(2, 'unmatched')
        ->assertJsonPath('unmatched.0.code', 'MISSING-A')
        ->assertJsonPath('unmatched.0.line', 2)
        ->assertJsonPath('unmatched.0.reason', 'sku_not_found')
        ->assertJsonPath('unmatched.1.code', 'MISSING-B');
});

it('rejects an empty csv', function () {
    $csv = UploadedFile::fake()->createWithContent('batch.csv', "code,qty,price\n");

    $this->actingAs($this->user)
        ->postJson(route('transactions.batch-parse'), ['csv_file' => $csv])
        ->assertUnprocessable()
        ->assertJsonPath('error', 'Failed to parse CSV.');
});

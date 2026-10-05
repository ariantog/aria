<?php

use App\Models\Item;
use App\Models\User;
use App\Services\PermissionGenerator;
use App\Services\Shopee\ShopeeItemBulkLinkParser;
use App\Services\Shopee\ShopeeItemBulkLinkService;
use App\Services\Shopee\ShopeeStockApiService;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    app(PermissionGenerator::class)->generateForModule('ShopeeStock');
    User::factory()->create();
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('shopee-stock-sync');
});

it('parses shopee product export headers from sample xlsx', function () {
    $path = base_path('old/DATA LENGKAP PRODUK SHOPEE.xlsx');
    if (! is_file($path)) {
        $this->markTestSkipped('Sample Shopee export not in repo.');
    }

    $rows = app(ShopeeItemBulkLinkParser::class)->parse($path);

    expect($rows)->not->toBeEmpty()
        ->and($rows[0]['shopee_item_id'])->toBe(58000473010)
        ->and($rows[0]['shopee_model_id'])->toBe(395043520573)
        ->and($rows[0]['code'])->toBe('ELBOWSUPPORT-05-BLUE');
});

it('parses csv rows with flexible headers', function () {
    $csv = "sku,item_id,model_id\nBULK-SKU-1,9001001,8001\n";
    $path = tempnam(sys_get_temp_dir(), 'shopee-bulk');
    file_put_contents($path, $csv);

    $rows = app(ShopeeItemBulkLinkParser::class)->parse($path);
    unlink($path);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['code'])->toBe('BULK-SKU-1')
        ->and($rows[0]['shopee_item_id'])->toBe(9001001)
        ->and($rows[0]['shopee_model_id'])->toBe(8001);
});

it('matches legacy sku before current code on bulk preview', function () {
    $legacyItem = Item::factory()->create([
        'code' => 'NEW-SKU-FORMAT',
        'legacy_code' => 'OLD-SHOPEE-SKU',
        'shopee_item_id' => null,
    ]);
    Item::factory()->create([
        'code' => 'OLD-SHOPEE-SKU',
        'legacy_code' => null,
        'shopee_item_id' => null,
    ]);

    $csv = "Kode Produk,Kode Variasi,SKU\n9001,8001,OLD-SHOPEE-SKU\n";
    $upload = UploadedFile::fake()->createWithContent('shopee.csv', $csv);

    $preview = app(ShopeeItemBulkLinkService::class)->preview($upload);

    expect($preview['summary']['ready'])->toBe(1)
        ->and($preview['rows'][0]['status'])->toBe('ready')
        ->and($preview['rows'][0]['message'])->toContain('legacy_code')
        ->and($preview['rows'][0]['item']['id'])->toBe($legacyItem->id);
});

it('previews and applies bulk shopee links', function () {
    $item = Item::factory()->create([
        'code' => 'BULK-LINK-SKU',
        'shopee_item_id' => null,
    ]);

    $this->mock(ShopeeStockApiService::class, function ($mock) {
        $mock->shouldReceive('isReady')->andReturn(true);
        $mock->shouldReceive('modelsByItemIds')
            ->once()
            ->with([9002002])
            ->andReturn([
                9002002 => [
                    [
                        'model_id' => 8002,
                        'model_sku' => 'BULK-LINK-SKU',
                        'stock_info_v2' => ['seller_stock' => []],
                    ],
                ],
            ]);
    });

    $csv = "code,shopee_item_id,shopee_model_id\nBULK-LINK-SKU,9002002,8002\n";
    $upload = UploadedFile::fake()->createWithContent('links.csv', $csv);

    $preview = app(ShopeeItemBulkLinkService::class)->preview($upload);

    expect($preview['summary']['ready'])->toBe(1)
        ->and($preview['rows'][0]['status'])->toBe('ready');

    $applied = app(ShopeeItemBulkLinkService::class)->apply($preview['token']);

    expect($applied['summary']['linked'])->toBe(1)
        ->and($item->fresh()->shopee_item_id)->toBe(9002002)
        ->and($item->fresh()->shopee_model_id)->toBe(8002);
});

it('renders bulk link page for sync permission', function () {
    $this->actingAs($this->user)
        ->get(route('shopee.bulk-link.index'))
        ->assertOk()
        ->assertSee('Shopee Bulk Link', false)
        ->assertSee('data-testid="shopee-bulk-link-preview"', false);
});

it('forbids bulk link without sync permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('shopee-stock-view');

    $this->actingAs($user)
        ->get(route('shopee.bulk-link.index'))
        ->assertForbidden();
});

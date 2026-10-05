<?php

use App\Models\Item;
use App\Models\User;
use App\Services\PermissionGenerator;
use App\Services\Shopee\ShopeeItemBulkLinkParser;
use App\Services\Shopee\ShopeeItemBulkLinkService;
use App\Services\Shopee\ShopeeStockApiService;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    app(PermissionGenerator::class)->generateForModule('ShopeeStock');
    User::factory()->create();
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('shopee-stock-sync');
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

    $csv = "code,shopee_item_id\nBULK-LINK-SKU,9002002\n";
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

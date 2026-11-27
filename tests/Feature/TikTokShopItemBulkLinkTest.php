<?php

use App\Models\Item;
use App\Models\TikTokBulkLinkRun;
use App\Models\User;
use App\Services\PermissionGenerator;
use App\Services\TikTokShop\TikTokShopItemBulkLinkParser;
use App\Services\TikTokShop\TikTokShopItemBulkLinkService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_24_120000_add_tiktok_shop_item_link_columns_to_items.php']);
    $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_27_120000_install_tiktok_bulk_link_runs.php']);

    app(PermissionGenerator::class)->generateForModule('TikTokShop');

    User::factory()->create();
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('tiktok-shop-item-link');
});

it('parses csv rows with flexible headers', function () {
    $csv = "seller_sku,product_id,sku_id\nBULK-TTS-1,7490000000000000101,7491000000000000101\n";
    $path = tempnam(sys_get_temp_dir(), 'tiktok-bulk');
    file_put_contents($path, $csv);

    $rows = app(TikTokShopItemBulkLinkParser::class)->parse($path);
    unlink($path);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['code'])->toBe('BULK-TTS-1')
        ->and($rows[0]['tiktok_product_id'])->toBe('7490000000000000101')
        ->and($rows[0]['tiktok_sku_id'])->toBe('7491000000000000101');
});

it('parses tiktok export style headers', function () {
    $csv = "product_id,sku_id,seller_sku\n7490000000000000202,7491000000000000202,EXPORT-SKU\n";
    $path = tempnam(sys_get_temp_dir(), 'tiktok-export');
    file_put_contents($path, $csv);

    $rows = app(TikTokShopItemBulkLinkParser::class)->parse($path);
    unlink($path);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['code'])->toBe('EXPORT-SKU')
        ->and($rows[0]['tiktok_product_id'])->toBe('7490000000000000202')
        ->and($rows[0]['tiktok_sku_id'])->toBe('7491000000000000202');
});

it('matches legacy sku before current code on bulk preview', function () {
    $legacyItem = Item::factory()->create([
        'code' => 'NEW-TTS-FORMAT',
        'legacy_code' => 'OLD-TTS-SKU',
        'tiktok_shop_product_id' => null,
    ]);
    Item::factory()->create([
        'code' => 'OLD-TTS-SKU',
        'legacy_code' => null,
        'tiktok_shop_product_id' => null,
    ]);

    $csv = "product_id,sku_id,seller_sku\n749001,749101,OLD-TTS-SKU\n";
    $upload = UploadedFile::fake()->createWithContent('tiktok.csv', $csv);

    $preview = app(TikTokShopItemBulkLinkService::class)->preview($upload);

    expect($preview['summary']['ready'])->toBe(1)
        ->and($preview['rows'][0]['status'])->toBe('ready')
        ->and($preview['rows'][0]['message'])->toContain('legacy_code')
        ->and($preview['rows'][0]['item']['id'])->toBe($legacyItem->id);
});

it('starts apply in batches and completes small files in one batch', function () {
    $item = Item::factory()->create([
        'code' => 'BULK-TTS-LINK',
        'name' => 'LINK ONLY NAME',
        'price' => 12345,
        'tiktok_shop_product_id' => null,
        'tiktok_shop_sku_id' => null,
    ]);

    $csv = "code,product_id,sku_id\nBULK-TTS-LINK,7490000000000000999,7491000000000000999\n";
    $upload = UploadedFile::fake()->createWithContent('links.csv', $csv);

    $service = app(TikTokShopItemBulkLinkService::class);
    $preview = $service->preview($upload);
    $run = $service->startApply($preview['token']);

    $fresh = $item->fresh();

    expect($run->status)->toBe(TikTokBulkLinkRun::STATUS_COMPLETED)
        ->and($run->processed_rows)->toBe(1)
        ->and($run->linked_count)->toBe(1)
        ->and($fresh->tiktok_shop_product_id)->toBe('7490000000000000999')
        ->and($fresh->tiktok_shop_sku_id)->toBe('7491000000000000999')
        ->and($fresh->code)->toBe('BULK-TTS-LINK')
        ->and($fresh->name)->toBe('LINK ONLY NAME')
        ->and((float) $fresh->price)->toBe(12345.0);
});

it('rate limits bulk link to one batch per minute', function () {
    Item::factory()->create(['code' => 'BATCH-TTS-SKU', 'tiktok_shop_product_id' => null]);

    $rows = [];
    for ($i = 0; $i < 1001; $i++) {
        $rows[] = [
            'line' => $i + 2,
            'code' => 'BATCH-TTS-SKU',
            'aria_item_id' => null,
            'tiktok_product_id' => '749000000'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'tiktok_sku_id' => '749100000'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
        ];
    }

    $run = TikTokBulkLinkRun::query()->create([
        'filename' => 'test.csv',
        'overwrite_existing' => true,
        'status' => TikTokBulkLinkRun::STATUS_RUNNING,
        'total_rows' => count($rows),
        'processed_rows' => 0,
        'payload' => $rows,
        'recent_results' => [],
        'failed_results' => [],
    ]);

    $service = app(TikTokShopItemBulkLinkService::class);
    Carbon::setTestNow('2026-11-27 12:00:00');

    expect($service->processRun($run->fresh(), ignoreInterval: true))->toBeTrue();
    $run->refresh();
    expect($run->processed_rows)->toBe(1000);

    expect($service->processRun($run->fresh()))->toBeFalse();

    Carbon::setTestNow('2026-11-27 12:01:01');
    expect($service->processRun($run->fresh()))->toBeTrue();
    $run->refresh();
    expect($run->processed_rows)->toBe(1001)
        ->and($run->status)->toBe(TikTokBulkLinkRun::STATUS_COMPLETED);

    Carbon::setTestNow();
});

it('renders bulk link page for item link permission', function () {
    $this->actingAs($this->user)
        ->get(route('tiktok-shop.bulk-link.index'))
        ->assertOk()
        ->assertSee('TikTok Shop Bulk Link', false)
        ->assertSee('data-testid="tiktok-bulk-link-preview"', false);
});

it('forbids bulk link without item link permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('tiktok-shop-reply');

    $this->actingAs($user)
        ->get(route('tiktok-shop.bulk-link.index'))
        ->assertForbidden();
});
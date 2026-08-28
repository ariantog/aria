<?php

namespace Tests\Unit;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Services\ImageService;
use App\Support\ItemImageResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImagePathLogicTest extends TestCase
{
    private ?string $defaultImageBackup = null;

    private ItemImageResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('core-nation.item_image_path', storage_path('framework/testing/images/'));
        Config::set('core-nation.item_image_url', 'http://localhost/asset/');

        $this->resolver = app(ItemImageResolver::class);

        if (! File::exists(public_path('images'))) {
            File::makeDirectory(public_path('images'), 0755, true);
        }
        $this->defaultImageBackup = File::exists(public_path('images/default-item.png'))
            ? File::get(public_path('images/default-item.png'))
            : null;
        if ($this->defaultImageBackup === null) {
            File::put(public_path('images/default-item.png'), 'png');
        }

        if (! File::exists(config('core-nation.item_image_path'))) {
            File::makeDirectory(config('core-nation.item_image_path'), 0755, true);
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(config('core-nation.item_image_path'));
        if ($this->defaultImageBackup !== null) {
            File::put(public_path('images/default-item.png'), $this->defaultImageBackup);
        } else {
            File::delete(public_path('images/default-item.png'));
        }
        parent::tearDown();
    }

    public function test_image_path_generation_for_small_ids(): void
    {
        $service = app(ImageService::class);
        $group = new ItemGroup(['id' => 3]);
        $group->id = 3;

        $file = UploadedFile::fake()->image('test.jpg');
        $service->saveImage($group, $file);

        $expectedPath = config('core-nation.item_image_path').'03/3.jpg';

        $this->assertFileExists($expectedPath, 'Image for ID 3 should be at 03/3.jpg');
    }

    public function test_image_path_generation_for_large_ids(): void
    {
        $service = app(ImageService::class);
        $group = new ItemGroup;
        $group->id = 125;

        $file = UploadedFile::fake()->image('test.jpg');
        $service->saveImage($group, $file);

        $expectedPath = config('core-nation.item_image_path').'25/125.jpg';

        $this->assertFileExists($expectedPath, 'Image for ID 125 should be at 25/125.jpg');
    }

    public function test_group_items_share_same_image_file(): void
    {
        $service = app(ImageService::class);
        $group = new ItemGroup;
        $group->id = 50;

        $itemA = new Item;
        $itemA->id = 101;
        $itemA->group_id = 50;

        $itemB = new Item;
        $itemB->id = 102;
        $itemB->group_id = 50;

        $file = UploadedFile::fake()->image('test.jpg');
        $service->saveItemImage($itemA, $file);

        $expectedPath = config('core-nation.item_image_path').'50/50.jpg';

        $this->assertFileExists($expectedPath, 'Image should be saved using Group ID filename');

        $this->assertEquals(
            config('core-nation.item_image_url').'50/50.jpg',
            $itemA->image_url,
            'Item A URL should point to Group ID image'
        );

        $this->assertEquals(
            config('core-nation.item_image_url').'50/50.jpg',
            $itemB->image_url,
            'Item B URL should point to SAME Group ID image'
        );
    }

    public function test_item_returns_default_image_when_file_missing(): void
    {
        $item = new Item;
        $item->id = 999;
        $item->group_id = 0;

        $this->assertStringContainsString('default-item.svg', $item->image_url);

        $folder = '99';
        $filename = '999.jpg';
        $path = config('core-nation.item_image_path').$folder;
        if (! File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }
        File::put($path.'/'.$filename, 'dummy content');

        $this->assertEquals(
            config('core-nation.item_image_url').'99/999.jpg',
            $item->image_url
        );
    }

    public function test_asset_lancar_falls_back_to_legacy_item_id_image(): void
    {
        $item = new Item;
        $item->id = 501;
        $item->group_id = 900;
        $item->type = ItemType::ASSET_LANCAR;

        $this->assertStringContainsString('default-item.svg', $item->image_url);

        $folder = '01';
        $path = config('core-nation.item_image_path').$folder;
        File::makeDirectory($path, 0755, true);
        File::put($path.'/501.jpg', 'legacy asset image');

        $this->assertEquals(
            config('core-nation.item_image_url').'01/501.jpg',
            $item->image_url,
            'Legacy per-SKU asset lancar uploads should resolve via item id'
        );
    }

    public function test_group_prefers_group_image_then_falls_back_to_child_item(): void
    {
        $group = new ItemGroup;
        $group->id = 60;

        $item = new Item;
        $item->id = 601;
        $item->group_id = 60;

        $group->setRelation('items', collect([$item]));

        $folder = '01';
        $path = config('core-nation.item_image_path').$folder;
        File::makeDirectory($path, 0755, true);
        File::put($path.'/601.jpg', 'child sku image');

        $this->assertEquals(
            config('core-nation.item_image_url').'01/601.jpg',
            $this->resolver->resolveUrlForGroup($group),
        );
    }

    public function test_parent_group_resolves_first_available_child_image(): void
    {
        $groupA = new ItemGroup;
        $groupA->id = 70;

        $groupB = new ItemGroup;
        $groupB->id = 71;

        $item = new Item;
        $item->id = 701;
        $item->group_id = 71;

        $groupB->setRelation('items', collect([$item]));

        $folder = '01';
        $path = config('core-nation.item_image_path').$folder;
        File::makeDirectory($path, 0755, true);
        File::put($path.'/701.jpg', 'child image for parent fallback');

        $url = $this->resolver->resolveUrlForGroups(collect([$groupA, $groupB]));

        $this->assertEquals(config('core-nation.item_image_url').'01/701.jpg', $url);
    }
}

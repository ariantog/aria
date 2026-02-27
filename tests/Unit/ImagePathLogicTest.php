<?php

namespace Tests\Unit;

use App\Models\Item;
use App\Models\ItemGroup;
use App\Services\ImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImagePathLogicTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Use a temporary directory for testing
        Config::set('core-nation.item_image_path', storage_path('framework/testing/images/'));
        Config::set('core-nation.item_image_url', 'http://localhost/asset/');

        // Ensure default image exists for fallback test
        if (! File::exists(public_path('images'))) {
            File::makeDirectory(public_path('images'), 0755, true);
        }
        File::put(public_path('images/default-item.svg'), '<svg></svg>');

        if (! File::exists(config('core-nation.item_image_path'))) {
            File::makeDirectory(config('core-nation.item_image_path'), 0755, true);
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(config('core-nation.item_image_path'));
        // Clean up default image if we created it just for test (optional, strictly speaking we should modify public_path mock but here we just touch the file)
        // File::delete(public_path('images/default-item.svg'));
        parent::tearDown();
    }

    public function test_image_path_generation_for_small_ids()
    {
        $service = new ImageService;
        $group = new ItemGroup(['id' => 3]);
        // Mock ID by force setting it (since it's not saved)
        $group->id = 3;

        $file = UploadedFile::fake()->image('test.jpg');
        $service->saveImage($group, $file);

        // Expected: 03/3.jpg
        $expectedPath = config('core-nation.item_image_path').'03/3.jpg';

        $this->assertFileExists($expectedPath, 'Image for ID 3 should be at 03/3.jpg');
    }

    public function test_image_path_generation_for_large_ids()
    {
        $service = new ImageService;
        $group = new ItemGroup;
        $group->id = 125;

        $file = UploadedFile::fake()->image('test.jpg');
        $service->saveImage($group, $file);

        // Expected according to requirement: 25/125.jpg
        $expectedPath = config('core-nation.item_image_path').'25/125.jpg';

        $this->assertFileExists($expectedPath, 'Image for ID 125 should be at 25/125.jpg');
    }

    public function test_group_items_share_same_image_file()
    {
        $service = new ImageService;
        $group = new ItemGroup;
        $group->id = 50;

        $itemA = new Item;
        $itemA->id = 101;
        $itemA->group_id = 50;

        $itemB = new Item;
        $itemB->id = 102;
        $itemB->group_id = 50;

        $file = UploadedFile::fake()->image('test.jpg');

        // Save image for Item A (which belongs to Group 50)
        $service->saveItemImage($itemA, $file);

        // Expected Path: .../50/50.jpg (Group ID as filename)
        // Folder: 50 (last 2 digits of Group ID 50 is 50, padded to 50)
        // Filename: 50.jpg
        $expectedPath = config('core-nation.item_image_path').'50/50.jpg';

        $this->assertFileExists($expectedPath, 'Image should be saved using Group ID filename');

        // Verify Item Accessors return URL because file exists
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

    public function test_item_returns_default_image_when_file_missing()
    {
        $item = new Item;
        $item->id = 999;
        $item->group_id = 0;

        // Ensure no file exists at expected path (.../99/999.jpg)

        // Route::fallback/asset() usually returns http://localhost/images/default-item.svg in test environment
        // We check if it contains default-item.svg

        $this->assertStringContainsString('default-item.svg', $item->image_url);

        // Now create file and check it returns real URL
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
}

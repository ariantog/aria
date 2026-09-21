<?php

uses(Tests\TestCase::class);

use App\Services\Restock\RestockSheetExportService;
use App\Support\ItemImageResolver;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

test('ItemImageResolver maps production cdn img/items URL to local disk', function () {
    $base = storage_path('framework/testing/restock-img-items/');
    if (! is_dir($base.'22')) {
        mkdir($base.'22', 0777, true);
    }

    $diskPath = $base.'22/95822.jpg';
    $image = imagecreatetruecolor(20, 20);
    imagejpeg($image, $diskPath, 90);
    imagedestroy($image);

    Config::set('core-nation.item_image_path', $base);
    Config::set('core-nation.cdn_path', $base);
    Config::set('core-nation.cdn_url', 'https://cdn.corenationactive.com/img/items/');

    $resolver = app(ItemImageResolver::class);
    $resolved = $resolver->resolveExistingDiskPathFromImageUrl(
        'https://cdn.corenationactive.com/img/items/22/95822.jpg',
    );

    expect($resolved)->toBe($diskPath);

    @unlink($diskPath);
});

test('export image resolver fetches remote JPEG when local file is missing', function () {
    $jpeg = imagecreatetruecolor(40, 40);
    ob_start();
    imagejpeg($jpeg, null, 90);
    $bytes = ob_get_clean();
    imagedestroy($jpeg);

    $remoteUrl = 'https://cdn.example.test/asset/99/12345.jpg';

    Http::fake([
        $remoteUrl => Http::response($bytes, 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $service = app(RestockSheetExportService::class);
    $method = new ReflectionMethod($service, 'resolveExportImagePath');
    $method->setAccessible(true);

    $path = $method->invoke($service, $remoteUrl, null);

    expect($path)->not->toBeNull();
    expect(is_file($path))->toBeTrue();
    Http::assertSent(fn ($request) => $request->url() === $remoteUrl);

    if (is_string($path) && str_starts_with($path, sys_get_temp_dir())) {
        @unlink($path);
    }
});

test('export image resolver finds files under cdn_path', function () {
    $base = storage_path('framework/testing/restock-export-cdn/');
    if (! is_dir($base)) {
        mkdir($base, 0777, true);
    }

    $diskPath = $base.'03/77.jpg';
    if (! is_dir(dirname($diskPath))) {
        mkdir(dirname($diskPath), 0777, true);
    }

    $image = imagecreatetruecolor(24, 24);
    imagejpeg($image, $diskPath, 90);
    imagedestroy($image);

    Config::set('core-nation.cdn_path', $base);
    Config::set('core-nation.cdn_url', 'https://cdn.example.test/asset/');

    $service = app(RestockSheetExportService::class);
    $method = new ReflectionMethod($service, 'resolveExportImagePath');
    $method->setAccessible(true);

    $path = $method->invoke($service, 'https://cdn.example.test/asset/03/77.jpg', null);

    expect($path)->toBe($diskPath);

    @unlink($diskPath);
});

test('export fetches img/items URL via configured CDN origin when path is relative', function () {
    $jpeg = imagecreatetruecolor(32, 32);
    ob_start();
    imagejpeg($jpeg, null, 90);
    $bytes = ob_get_clean();
    imagedestroy($jpeg);

    Config::set('core-nation.cdn_url', 'https://cdn.corenationactive.com/img/items/');
    Config::set('core-nation.item_image_path', storage_path('framework/testing/missing-local/'));

    $absolute = 'https://cdn.corenationactive.com/img/items/22/95822.jpg';
    Http::fake([
        $absolute => Http::response($bytes, 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $service = app(RestockSheetExportService::class);
    $method = new ReflectionMethod($service, 'resolveExportImagePath');
    $method->setAccessible(true);

    $path = $method->invoke($service, '/img/items/22/95822.jpg', null);

    expect($path)->not->toBeNull();
    Http::assertSent(fn ($request) => $request->url() === $absolute);

    if (is_string($path) && str_starts_with($path, sys_get_temp_dir())) {
        @unlink($path);
    }
});

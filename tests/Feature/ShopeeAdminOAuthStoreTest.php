<?php

use App\Support\ShopeeAdminOAuthStore;

it('merges multiple shop tokens without dropping the primary flat row', function () {
    $raw = ShopeeAdminOAuthStore::mergeShopAuthorization(['shop_id' => 100], 100, [
        'access_token' => 'tok-a',
        'refresh_token' => 'ref-a',
        'expires_at' => now()->addHour()->toIso8601String(),
    ]);

    $raw = ShopeeAdminOAuthStore::mergeShopAuthorization($raw, 999, [
        'access_token' => 'tok-my',
        'refresh_token' => 'ref-my',
        'expires_at' => now()->addHour()->toIso8601String(),
    ]);

    expect($raw['shop_id'])->toBe(100)
        ->and($raw['access_token'])->toBe('tok-a')
        ->and(ShopeeAdminOAuthStore::credentialsForShop($raw, 999)['access_token'])->toBe('tok-my')
        ->and(ShopeeAdminOAuthStore::authorizedShopIds($raw))->toBe([100, 999]);
});
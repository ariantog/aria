<?php

use App\Models\Setting;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.shopee_admin.partner_id' => '2053195',
        'services.shopee_admin.partner_key' => 'test-admin-partner-key',
        'services.shopee_admin.base_url' => 'https://partner.shopeemobile.com',
        'services.shopee_admin.primary_shop_id' => 76610113,
    ]);
});

it('exchanges main account oauth and stores all shop_id_list', function () {
    Http::fake([
        'partner.shopeemobile.com/*' => Http::response([
            'error' => '',
            'access_token' => 'main-batch-access',
            'refresh_token' => 'main-batch-refresh',
            'expire_in' => 14_400,
            'shop_id_list' => [76610113, 88001234],
            'merchant_id_list' => [],
        ]),
    ]);

    $this->get(route('shopee-admin.oauth.callback', [
        'code' => 'main-code',
        'main_account_id' => 555001,
    ]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $stored = Setting::getValue(ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG, []);

    expect($stored['main_account_id'])->toBe(555001)
        ->and($stored['shop_id_list'])->toBe([76610113, 88001234])
        ->and($stored['shops']['76610113']['access_token'])->toBe('main-batch-access')
        ->and($stored['shops']['88001234']['access_token'])->toBe('main-batch-access')
        ->and($stored['last_oauth_callback_query']['main_account_id'])->toBe('555001');
});
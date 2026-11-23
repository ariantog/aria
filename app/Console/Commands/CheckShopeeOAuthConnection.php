<?php

namespace App\Console\Commands;

use App\Services\Shopee\ShopeeAdminOpenApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckShopeeOAuthConnection extends Command
{
    protected $signature = 'shopee:refresh-oauth';

    protected $description = 'Refresh Shopee shop access token using the stored refresh token (CORENATION ADMIN)';

    public function handle(ShopeeAdminOpenApiService $api): int
    {
        if (! $api->isConfigured()) {
            $this->comment('Shopee admin app not configured (SHOPEE_ADMIN_PARTNER_ID / KEY).');

            return self::SUCCESS;
        }

        Log::info('shopee:refresh-oauth run at: '.now()->toIso8601String());

        $result = $api->checkConnection();

        if ($result['ok']) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}
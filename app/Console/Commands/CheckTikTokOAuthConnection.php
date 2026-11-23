<?php

namespace App\Console\Commands;

use App\Services\TikTokShop\TikTokShopOAuthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckTikTokOAuthConnection extends Command
{
    protected $signature = 'tiktok:refresh-oauth';

    protected $description = 'Refresh TikTok Shop seller access token using the stored refresh token';

    public function handle(TikTokShopOAuthService $oauth): int
    {
        if (! $oauth->isConfigured()) {
            $this->comment('TikTok Shop app not configured (TIKTOK_SHOP_APP_KEY / TIKTOK_SHOP_APP_SECRET).');

            return self::SUCCESS;
        }

        Log::info('tiktok:refresh-oauth run at: '.now()->toIso8601String());

        $result = $oauth->checkConnection();

        if ($result['ok']) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}
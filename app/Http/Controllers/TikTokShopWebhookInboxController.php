<?php

namespace App\Http\Controllers;

use App\Models\TikTokShop;
use App\Models\TikTokShopWebhookInbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TikTokShopWebhookInboxController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize(TikTokShop::getPermissions()['view']);

        $tableExists = Schema::hasTable('tiktok_shop_webhook_inbox');

        $rows = null;

        if ($tableExists) {
            $perPage = min(max((int) $request->query('per_page', 25), 10), 100);

            $rows = TikTokShopWebhookInbox::query()
                ->orderByDesc('id')
                ->paginate($perPage)
                ->withQueryString();
        }

        return view('tiktok-shop.webhooks', [
            'tableExists' => $tableExists,
            'rows' => $rows,
        ]);
    }
}
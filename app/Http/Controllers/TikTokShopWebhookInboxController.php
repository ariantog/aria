<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceWebhook;
use App\Models\Omnichannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TikTokShopWebhookInboxController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        abort_unless(
            MarketplaceWebhook::userCanView($request->user()) || Omnichannel::userCanOrders($request->user()),
            403,
        );

        $query = $request->query();
        unset($query['view']);

        return redirect()->route('omnichannel.orders.index', $query);
    }
}
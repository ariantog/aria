<?php

namespace App\Http\Controllers;

use App\Models\Omnichannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TikTokShopWebhookInboxController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        abort_unless(Omnichannel::userCanOrders($request->user()), 403);

        return redirect()->route('omnichannel.orders.index', $request->query());
    }
}
<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TikTokShopWebhookInboxController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('marketplace.webhooks.index', $request->query());
    }
}
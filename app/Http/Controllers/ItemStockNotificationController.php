<?php

namespace App\Http\Controllers;

use App\Models\ItemStockNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ItemStockNotificationController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize(ItemStockNotification::getPermissions()['view']);

        $showDismissed = $request->boolean('dismissed');

        $notifications = ItemStockNotification::query()
            ->with(['item:id,code,name,type', 'soldOutWarehouse:id,name,type', 'sourceWarehouse:id,name,type'])
            ->when(
                $showDismissed,
                fn ($query) => $query->whereNotNull('dismissed_at'),
                fn ($query) => $query->active(),
            )
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('stock-notifications.index', [
            'notifications' => $notifications,
            'showDismissed' => $showDismissed,
            'unreadCount' => ItemStockNotification::query()->unread()->count(),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function unreadCount(): JsonResponse
    {
        Gate::authorize(ItemStockNotification::getPermissions()['view']);

        return response()->json([
            'count' => ItemStockNotification::query()->unread()->count(),
        ]);
    }

    public function markRead(ItemStockNotification $notification): RedirectResponse
    {
        Gate::authorize(ItemStockNotification::getPermissions()['view']);

        if ($notification->dismissed_at === null && $notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    public function dismiss(ItemStockNotification $notification): RedirectResponse
    {
        Gate::authorize(ItemStockNotification::getPermissions()['dismiss']);

        $notification->update([
            'dismissed_at' => now(),
            'read_at' => $notification->read_at ?? now(),
        ]);

        return back()->with('success', 'Notification dismissed.');
    }

    public function markAllRead(): RedirectResponse
    {
        Gate::authorize(ItemStockNotification::getPermissions()['view']);

        ItemStockNotification::query()
            ->unread()
            ->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}

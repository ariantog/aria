<?php

namespace App\Http\Controllers;

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\ItemStockNotification;
use Illuminate\Database\Eloquent\Builder;
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
        $filters = $this->filtersFromRequest($request);
        $baseQuery = $this->scopedNotificationsQuery($showDismissed);

        $notifications = (clone $baseQuery)
            ->with(['item:id,code,name,type', 'soldOutWarehouse:id,name,type', 'sourceWarehouse:id,name,type'])
            ->when(
                filled($filters['item_id']) && ctype_digit((string) $filters['item_id']),
                fn (Builder $query) => $query->where('item_id', (int) $filters['item_id']),
            )
            ->when(
                filled($filters['sold_out_warehouse_id']) && ctype_digit((string) $filters['sold_out_warehouse_id']),
                fn (Builder $query) => $query->where('sold_out_warehouse_id', (int) $filters['sold_out_warehouse_id']),
            )
            ->when(
                filled($filters['source_warehouse_id']) && ctype_digit((string) $filters['source_warehouse_id']),
                fn (Builder $query) => $query->where('source_warehouse_id', (int) $filters['source_warehouse_id']),
            )
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('stock-notifications.index', [
            'notifications' => $notifications,
            'showDismissed' => $showDismissed,
            'filters' => $filters,
            'filterOptions' => $this->filterOptions($baseQuery),
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

    /**
     * @return array{item_id: string, sold_out_warehouse_id: string, source_warehouse_id: string}
     */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'item_id' => (string) $request->query('item_id', ''),
            'sold_out_warehouse_id' => (string) $request->query('sold_out_warehouse_id', ''),
            'source_warehouse_id' => (string) $request->query('source_warehouse_id', ''),
        ];
    }

    private function scopedNotificationsQuery(bool $showDismissed): Builder
    {
        return ItemStockNotification::query()
            ->when(
                $showDismissed,
                fn (Builder $query) => $query->whereNotNull('dismissed_at'),
                fn (Builder $query) => $query->active(),
            );
    }

    /**
     * @return array{
     *     items: \Illuminate\Support\Collection<int, Item>,
     *     soldOutWarehouses: \Illuminate\Support\Collection<int, Addrbook>,
     *     sourceWarehouses: \Illuminate\Support\Collection<int, Addrbook>
     * }
     */
    private function filterOptions(Builder $baseQuery): array
    {
        $itemIds = (clone $baseQuery)->distinct()->pluck('item_id');
        $soldOutWarehouseIds = (clone $baseQuery)->distinct()->pluck('sold_out_warehouse_id');
        $sourceWarehouseIds = (clone $baseQuery)->distinct()->pluck('source_warehouse_id');

        return [
            'items' => Item::query()
                ->whereIn('id', $itemIds)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'soldOutWarehouses' => Addrbook::query()
                ->whereIn('id', $soldOutWarehouseIds)
                ->orderBy('name')
                ->get(['id', 'name']),
            'sourceWarehouses' => Addrbook::query()
                ->whereIn('id', $sourceWarehouseIds)
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }
}

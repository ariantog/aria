@extends('layouts.app')

@section('title', 'Stock Alerts')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'Stock Alerts', 'href' => route('stock-notifications.index')],
];
$fmtNum = fn ($v) => format_amount($v, 0);
$filterQuery = array_filter($filters ?? []);
$hasFilters = ! empty($filterQuery);
$sourceStatusOptions = \App\Enums\ItemStockSourceStatus::cases();
@endphp

{{-- Do not use h-full + overflow-x-auto here: that pins height to the viewport and clips the table. --}}
<div class="flex flex-col gap-4 p-3 sm:p-4" data-testid="stock-notifications-page">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Stock Alerts</h1>
            <p class="text-gray-500">
                Items sold out at arrangement-enabled shops while stock remains at another warehouse — available, slow moving, or dead stock.
                Only warehouses with <span class="font-medium">Warehouse Arrangement</span> enabled on the addrbook form trigger alerts.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($unreadCount > 0)
            <form method="POST" action="{{ route('stock-notifications.mark-all-read') }}">
                @csrf
                <button type="submit" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Mark all read
                </button>
            </form>
            @endif
            <a href="{{ route('reports.warehouse-arrangement') }}"
               class="rounded-lg bg-blue-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-800">
                Warehouse Arrangement
            </a>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 text-sm">
        <a href="{{ route('stock-notifications.index', $filterQuery) }}"
           class="rounded-md px-3 py-1.5 {{ ! $showDismissed ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
            Active
        </a>
        <a href="{{ route('stock-notifications.index', array_merge($filterQuery, ['dismissed' => 1])) }}"
           class="rounded-md px-3 py-1.5 {{ $showDismissed ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
            Dismissed
        </a>
        @if($unreadCount > 0)
        <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-medium text-rose-700">
            {{ $unreadCount }} unread
        </span>
        @endif
    </div>

    <form method="GET"
          action="{{ route('stock-notifications.index') }}"
          class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-3 sm:p-4"
          data-testid="stock-notifications-filters">
        @if($showDismissed)
        <input type="hidden" name="dismissed" value="1">
        @endif
        <label class="flex min-w-[12rem] flex-1 flex-col gap-1 sm:flex-none">
            <span class="text-xs font-medium uppercase text-gray-500">Item</span>
            <select name="item_id"
                    class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    data-testid="stock-notifications-filter-item">
                <option value="">All items</option>
                @foreach($filterOptions['items'] as $item)
                <option value="{{ $item->id }}" @selected((string) ($filters['item_id'] ?? '') === (string) $item->id)>
                    {{ $item->code }} — {{ $item->name }}
                </option>
                @endforeach
            </select>
        </label>
        <label class="flex min-w-[12rem] flex-1 flex-col gap-1 sm:flex-none">
            <span class="text-xs font-medium uppercase text-gray-500">Sold out at</span>
            <select name="sold_out_warehouse_id"
                    class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    data-testid="stock-notifications-filter-sold-out">
                <option value="">All shops</option>
                @foreach($filterOptions['soldOutWarehouses'] as $warehouse)
                <option value="{{ $warehouse->id }}" @selected((string) ($filters['sold_out_warehouse_id'] ?? '') === (string) $warehouse->id)>
                    {{ $warehouse->name }}
                </option>
                @endforeach
            </select>
        </label>
        <label class="flex min-w-[12rem] flex-1 flex-col gap-1 sm:flex-none">
            <span class="text-xs font-medium uppercase text-gray-500">Stock at</span>
            <select name="source_warehouse_id"
                    class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    data-testid="stock-notifications-filter-stock-at">
                <option value="">All warehouses</option>
                @foreach($filterOptions['sourceWarehouses'] as $warehouse)
                <option value="{{ $warehouse->id }}" @selected((string) ($filters['source_warehouse_id'] ?? '') === (string) $warehouse->id)>
                    {{ $warehouse->name }}
                </option>
                @endforeach
            </select>
        </label>
        <label class="flex min-w-[10rem] flex-1 flex-col gap-1 sm:flex-none">
            <span class="text-xs font-medium uppercase text-gray-500">Stock type</span>
            <select name="source_status"
                    class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    data-testid="stock-notifications-filter-status">
                <option value="">All types</option>
                @foreach($sourceStatusOptions as $statusOption)
                <option value="{{ $statusOption->value }}" @selected(($filters['source_status'] ?? '') === $statusOption->value)>
                    {{ $statusOption->label() }}
                </option>
                @endforeach
            </select>
        </label>
        <button type="submit"
                class="rounded-lg bg-blue-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-800"
                data-testid="stock-notifications-filter-apply">
            Apply
        </button>
        @if($hasFilters)
        <a href="{{ route('stock-notifications.index', $showDismissed ? ['dismissed' => 1] : []) }}"
           class="inline-flex items-center rounded-lg px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100"
           data-testid="stock-notifications-filter-clear">
            Clear
        </a>
        @endif
    </form>

    @php
    $selectionRows = $notifications->map(fn ($notification) => [
        'id' => $notification->id,
        'item_id' => $notification->item_id,
        'quantity' => max(1, (int) floor((float) $notification->source_stock)),
    ])->values();
    @endphp

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
         x-data="stockAlertsList(@js([
             'canDraftMove' => $canDraftMove ?? false,
             'warehouseFiltersLocked' => $warehouseFiltersLocked ?? false,
             'fromWarehouseId' => filled($filters['source_warehouse_id'] ?? '') ? (int) $filters['source_warehouse_id'] : null,
             'toWarehouseId' => filled($filters['sold_out_warehouse_id'] ?? '') ? (int) $filters['sold_out_warehouse_id'] : null,
             'rows' => $selectionRows,
         ]))">
        @if($notifications->isEmpty())
        <div class="p-8 text-center text-sm text-gray-500">
            No {{ $showDismissed ? 'dismissed' : 'active' }} stock alerts.
        </div>
        @else
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 bg-gray-50 px-4 py-3">
            <div class="flex flex-wrap items-center gap-3">
                @if($canDraftMove ?? false)
                <button type="button"
                        @click="submitDraft()"
                        :disabled="selectedCount() === 0"
                        class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                        data-testid="stock-notifications-move">
                    Move to sold-out shop
                    <span x-show="selectedCount() > 0" x-cloak>(<span x-text="selectedCount()"></span>)</span>
                </button>
                @endif
                <button type="button"
                        x-show="selectedCount() > 0"
                        x-cloak
                        @click="clearSelection()"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                    Clear selection
                </button>
            </div>
            @if(! ($warehouseFiltersLocked ?? false))
            <p class="text-xs text-gray-500" data-testid="stock-notifications-move-hint">
                Filter by <span class="font-medium">Sold out at</span> and <span class="font-medium">Stock at</span> to enable move.
            </p>
            @endif
            @if(($canDraftMove ?? false) === false && ($warehouseFiltersLocked ?? false))
            <p class="text-xs text-gray-500">Move requires permission to create move transactions.</p>
            @endif
        </div>

        <form id="stock-alerts-draft-form" method="POST" action="{{ route('stock-notifications.draft-move') }}" class="hidden">
            @csrf
            <input type="hidden" name="from_warehouse_id" :value="fromWarehouseId">
            <input type="hidden" name="to_warehouse_id" :value="toWarehouseId">
            <template x-for="(draft, idx) in draftPayload()" :key="`${draft.item_id}-${idx}`">
                <div>
                    <input type="hidden" :name="`items[${idx}][item_id]`" :value="draft.item_id">
                    <input type="hidden" :name="`items[${idx}][quantity]`" :value="draft.quantity">
                </div>
            </template>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="w-10 px-4 py-3">
                            @if($canDraftMove ?? false)
                            <input type="checkbox"
                                   class="rounded border-gray-300"
                                   :checked="allSelected()"
                                   @change="toggleAll($event.target.checked)"
                                   data-testid="stock-notifications-select-all"
                                   aria-label="Select all on this page">
                            @endif
                        </th>
                        <th class="px-4 py-3">Item</th>
                        <th class="px-4 py-3">Sold out at</th>
                        <th class="px-4 py-3">Stock at</th>
                        <th class="px-4 py-3">Qty</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">When</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($notifications as $notification)
                    <tr class="{{ $notification->isUnread() ? 'bg-blue-50/40' : '' }}" data-testid="stock-notification-row">
                        <td class="px-4 py-3 align-top">
                            @if($canDraftMove ?? false)
                            <input type="checkbox"
                                   class="rounded border-gray-300"
                                   x-model="selected[{{ $notification->id }}]"
                                   data-testid="stock-notification-select">
                            @endif
                        </td>
                        @include('stock-notifications.partials.item-cell', ['notification' => $notification])
                        @include('stock-notifications.partials.warehouse-cell', ['warehouse' => $notification->soldOutWarehouse])
                        @include('stock-notifications.partials.warehouse-cell', ['warehouse' => $notification->sourceWarehouse])
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $fmtNum($notification->source_stock) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $notification->source_status->colorClass() }}">
                                {{ $notification->source_status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500">
                            {{ $notification->created_at?->format('d M Y H:i') }}
                            @if($notification->read_at)
                            <div class="text-xs">Read {{ $notification->read_at->format('d M H:i') }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('reports.warehouse-arrangement', ['warehouse_id' => $notification->sold_out_warehouse_id]) }}"
                                   class="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                    Arrange
                                </a>
                                @if(! $showDismissed)
                                @if($notification->isUnread())
                                <form method="POST" action="{{ route('stock-notifications.read', $notification) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                        Mark read
                                    </button>
                                </form>
                                @endif
                                <form method="POST" action="{{ route('stock-notifications.dismiss', $notification) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md border border-rose-200 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50">
                                        Dismiss
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($notifications->hasPages())
        <div class="border-t border-gray-100 px-4 py-3">
            {{ $notifications->links() }}
        </div>
        @endif
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
function stockAlertsList(config) {
    const selected = {};
    for (const row of config.rows || []) {
        selected[row.id] = false;
    }

    return {
        canDraftMove: !!config.canDraftMove,
        warehouseFiltersLocked: !!config.warehouseFiltersLocked,
        fromWarehouseId: config.fromWarehouseId,
        toWarehouseId: config.toWarehouseId,
        rows: config.rows || [],
        selected,

        selectedCount() {
            return Object.values(this.selected).filter(Boolean).length;
        },

        allSelected() {
            if (this.rows.length === 0) {
                return false;
            }
            return this.rows.every((row) => this.selected[row.id]);
        },

        toggleAll(checked) {
            for (const row of this.rows) {
                this.selected[row.id] = checked;
            }
        },

        clearSelection() {
            this.toggleAll(false);
        },

        draftPayload() {
            return this.rows
                .filter((row) => this.selected[row.id])
                .map((row) => ({
                    item_id: row.item_id,
                    quantity: row.quantity,
                }));
        },

        submitDraft() {
            if (!this.canDraftMove || this.selectedCount() === 0) {
                return;
            }
            document.getElementById('stock-alerts-draft-form')?.requestSubmit();
        },
    };
}
</script>
@endpush

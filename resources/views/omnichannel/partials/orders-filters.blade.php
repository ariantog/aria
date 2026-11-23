@php
    $defaultOpen = $defaultOpen ?? true;
    $resetQuery = $resetQuery ?? ['view' => $filters['view'] ?? 'events'];
    $perPage = (int) ($filters['per_page'] ?? request()->query('per_page', 25));
@endphp

<div class="rounded-xl border border-gray-200 bg-white shadow-sm" data-testid="omnichannel-orders-filters" x-data="{ showFilters: {{ $defaultOpen ? 'true' : 'false' }} }">
    <div class="flex items-center justify-between gap-2 border-b border-gray-100 px-3 py-2.5">
        <span class="text-sm font-medium text-gray-700">Filters</span>
        <button type="button"
                @click="showFilters = !showFilters"
                class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-gray-600 hover:bg-gray-50"
                data-testid="toggle-omnichannel-orders-filters">
            <span x-text="showFilters ? 'Hide' : 'Show'"></span>
            <svg class="h-4 w-4 transition-transform" :class="showFilters ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
    </div>
    <div x-show="showFilters" x-cloak class="p-3">
        <form method="get" action="{{ route('omnichannel.orders.index') }}" class="flex flex-wrap items-end gap-2">
            <div class="flex flex-col gap-1">
                <label for="channel" class="text-xs font-medium uppercase text-gray-500">Marketplace</label>
                <select name="channel" id="channel" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="all" @selected($filters['channel'] === 'all')>Semua</option>
                    <option value="shopee" @selected($filters['channel'] === 'shopee')>Shopee</option>
                    <option value="tiktok" @selected($filters['channel'] === 'tiktok')>TikTok Shop</option>
                    <option value="tokopedia" @selected($filters['channel'] === 'tokopedia')>Tokopedia (TikTok Partner)</option>
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label for="view" class="text-xs font-medium uppercase text-gray-500">Tampilan</label>
                <select name="view" id="view" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" data-testid="omnichannel-view-select">
                    <option value="events" @selected(($filters['view'] ?? 'events') === 'events')>Events (history)</option>
                    <option value="orders" @selected(($filters['view'] ?? 'events') === 'orders')>Current orders</option>
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label for="date_from" class="text-xs font-medium uppercase text-gray-500">From</label>
                <input type="date" name="date_from" id="date_from" value="{{ $filters['date_from']->format('Y-m-d') }}"
                       class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <div class="flex flex-col gap-1">
                <label for="date_to" class="text-xs font-medium uppercase text-gray-500">To</label>
                <input type="date" name="date_to" id="date_to" value="{{ $filters['date_to']->format('Y-m-d') }}"
                       class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <div class="flex flex-col gap-1">
                <label for="order_ref" class="text-xs font-medium uppercase text-gray-500">Order ref</label>
                <input type="text" name="order_ref" id="order_ref" value="{{ $filters['order_ref'] ?? '' }}" placeholder="Cari nomor order…" autocomplete="off"
                       class="min-w-[10rem] rounded-md border border-gray-300 px-2.5 py-1.5 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <div class="flex flex-col gap-1">
                <label for="order_status" class="text-xs font-medium uppercase text-gray-500">Status</label>
                <input type="text" name="order_status" id="order_status" list="order-status-options" value="{{ $filters['order_status'] ?? '' }}" placeholder="Partial match…"
                       class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <datalist id="order-status-options">
                    @foreach($statusOptions as $status)
                    <option value="{{ $status }}"></option>
                    @endforeach
                </datalist>
            </div>
            <div class="flex flex-col gap-1">
                <label for="shop_ref" class="text-xs font-medium uppercase text-gray-500">Shop</label>
                <select name="shop_ref" id="shop_ref" class="max-w-[14rem] rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="">Semua</option>
                    @foreach($shopOptions as $shop)
                    <option value="{{ $shop }}" @selected($filters['shop_ref'] === $shop)>{{ $shop }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label for="warehouse_id" class="text-xs font-medium uppercase text-gray-500">Gudang</label>
                <select name="warehouse_id" id="warehouse_id" class="max-w-[14rem] rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="">Semua</option>
                    @foreach($warehouseOptions as $wid => $wname)
                    <option value="{{ $wid }}" @selected((int) $filters['warehouse_id'] === (int) $wid)>{{ $wname }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label for="per_page" class="text-xs font-medium uppercase text-gray-500">Rows / page</label>
                <select name="per_page" id="per_page" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    @foreach([10, 25, 50, 100] as $size)
                    <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
                <a href="{{ route('omnichannel.orders.index', $resetQuery) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</a>
            </div>
        </form>
        <p class="mt-2 text-[10px] text-gray-500">Order ref: partial match; mengisi ini mengabaikan filter tanggal.</p>
    </div>
</div>
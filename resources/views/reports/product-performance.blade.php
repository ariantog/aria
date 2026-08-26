@extends('layouts.app')

@section('title', 'Product Performance')

@section('content')
@php
use App\Services\ProductPerformanceService;

$breadcrumbs = [
    ['title' => 'Reports', 'href' => '#'],
    ['title' => 'Product Performance', 'href' => route('reports.product-performance')],
];
$queryParams = fn (array $extra = []) => array_filter(array_merge([
    'tab' => $tab,
    'period' => $periodDays,
    'warehouse_id' => $warehouseId,
    'item_type' => $itemType !== 'all' ? $itemType : null,
    'grain' => $grain,
], $extra));
$metricLabel = $tab === ProductPerformanceService::TAB_DEMAND ? 'Qty' : 'Value';
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Product Performance</h2>
        <p class="mt-0.5 text-sm text-gray-500">Sales contribution and warehouse demand (net of returns, after invoice discount).</p>
    </div>

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('reports.product-performance', $queryParams(['tab' => ProductPerformanceService::TAB_SALES])) }}"
           class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $tab === ProductPerformanceService::TAB_SALES ? 'bg-blue-600 text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">
            Sales contribution
        </a>
        <a href="{{ route('reports.product-performance', $queryParams(['tab' => ProductPerformanceService::TAB_DEMAND])) }}"
           class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $tab === ProductPerformanceService::TAB_DEMAND ? 'bg-blue-600 text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">
            Warehouse demand
        </a>
        <a href="{{ route('reports.product-performance', $queryParams(['tab' => ProductPerformanceService::TAB_ATTRIBUTES])) }}"
           class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $tab === ProductPerformanceService::TAB_ATTRIBUTES ? 'bg-blue-600 text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">
            Attributes
        </a>
    </div>

    <form method="GET" action="{{ route('reports.product-performance') }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-3">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Period</label>
            <select name="period" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                @foreach([30, 90, 180, 365] as $days)
                    <option value="{{ $days }}" @selected($periodDays === $days)>Last {{ $days }} days</option>
                @endforeach
            </select>
        </div>
        @if($tab === ProductPerformanceService::TAB_DEMAND)
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Warehouse</label>
            <select name="warehouse_id" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                <option value="">Select warehouse</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" @selected((int) $warehouseId === (int) $wh->id)>{{ $wh->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Product kind</label>
            <select name="item_type" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                <option value="all" @selected($itemType === 'all')>All</option>
                <option value="1" @selected($itemType === '1')>Regular items</option>
                <option value="2" @selected($itemType === '2')>Asset lancar</option>
            </select>
        </div>
        @if($tab === ProductPerformanceService::TAB_ATTRIBUTES)
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Group by</label>
            <select name="grain" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                @foreach($grainOptions as $key => $label)
                    <option value="{{ $key }}" @selected($grain === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Apply</button>
        <a href="{{ route('reports.product-performance', ['tab' => $tab]) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</a>
    </form>

    <div class="text-xs text-gray-500">
        @if($syncedAt)
            Last synced {{ $syncedAt->diffForHumans() }}.
            @if($stale)
                <span class="text-amber-700">May be stale — run <code class="text-xs">app:sync-product-performance</code> or wait for the daily cron.</span>
            @endif
        @elseif(! $hasData)
            <span class="text-amber-700">No cached data yet. Run <code class="text-xs">php artisan app:recalculate-warehouse-item-stats</code> then <code class="text-xs">php artisan app:sync-product-performance</code>.</span>
        @endif
    </div>

    @if($tab === ProductPerformanceService::TAB_ATTRIBUTES)
    <p class="text-sm text-gray-600">Default <strong>Type + Size</strong> is the most common replenishment view in apparel retail. Use <strong>Type + Color</strong> for merchandising buys, or <strong>Type + Color + Size</strong> for full assortment planning.</p>
    @endif

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 bg-gray-50 px-5 py-3 text-sm font-bold text-gray-900">
            @if($tab === ProductPerformanceService::TAB_SALES)
                Top contributors by revenue
            @elseif($tab === ProductPerformanceService::TAB_DEMAND)
                Top demand by quantity
            @else
                Top attribute combinations
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">#</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">Label</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">Qty (net)</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">Value (net)</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">% of {{ strtolower($metricLabel) }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-500">{{ $row->rank }}</td>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $row->label }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ format_amount((float) $row->net_qty, 0) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ format_amount((float) $row->net_value, 0) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-600">{{ format_amount((float) $row->pct_of_total, 1) }}%</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            @if($tab === ProductPerformanceService::TAB_DEMAND && ! $warehouseId)
                                Select a warehouse to view demand rankings.
                            @else
                                No results for this filter.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

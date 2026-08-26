@extends('layouts.app')

@section('title', 'Inventory Health')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'Reports', 'href' => '#'],
    ['title' => 'Inventory Health', 'href' => route('reports.inventory-health')],
];

$getStatus = function ($item) {
    $sold30 = abs((float) ($item->sold_30 ?? 0));
    $sold90 = abs((float) ($item->sold_90 ?? 0));
    $stock = (float) ($item->current_stock ?? 0);
    $days = $item->last_sold_at ? now()->diffInDays(\Illuminate\Support\Carbon::parse($item->last_sold_at)) : 999;

    if ($sold30 > 10 && $stock < $sold30 * 0.2) {
        return ['label' => 'Fast Moving / Low Stock', 'color' => 'bg-amber-500', 'rec' => 'Restock immediately'];
    }
    if ($sold30 > 0) {
        return ['label' => 'Healthy', 'color' => 'bg-emerald-500', 'rec' => 'Maintain levels'];
    }
    if ($sold90 === 0.0 && $stock > 0) {
        return ['label' => 'Dead Stock', 'color' => 'bg-rose-500', 'rec' => 'Move to active warehouse or clearance'];
    }
    if ($days > 30 && $stock > 0) {
        return ['label' => 'Slow Moving', 'color' => 'bg-zinc-500', 'rec' => 'Monitor & reduce stock'];
    }
    return ['label' => 'Inactive', 'color' => 'bg-zinc-300', 'rec' => 'N/A'];
};
$fmtNum = fn($v) => format_amount($v, 0);
@endphp

<div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Inventory Health & Intelligence</h1>
        <p class="text-zinc-500">Identify Fast Moving items and Dead Stock to optimize your inventory.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-l-4 border-l-emerald-500 bg-white p-4">
            <p class="text-sm font-medium text-emerald-600">Healthy / Fast</p>
            <p class="mt-2 text-xs text-zinc-500">Items sold in the last 30 days. High turnover.</p>
        </div>
        <div class="rounded-xl border border-l-4 border-l-amber-500 bg-white p-4">
            <p class="text-sm font-medium text-amber-600">Slow Moving</p>
            <p class="mt-2 text-xs text-zinc-500">No sales in 30 days. Consider re-evaluating stock levels.</p>
        </div>
        <div class="rounded-xl border border-l-4 border-l-rose-500 bg-white p-4">
            <p class="text-sm font-medium text-rose-600">Dead Stock</p>
            <p class="mt-2 text-xs text-zinc-500">No sales in 90 days. Recommended for rebalancing.</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="p-4">
            <form method="GET" action="{{ route('reports.inventory-health') }}" class="flex flex-wrap items-end gap-4">
                <div class="grid w-[200px] gap-1.5">
                    <label class="text-sm font-medium" for="warehouse_id">Warehouse</label>
                    <select id="warehouse_id" name="warehouse_id" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                        <option value="">All Warehouses</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" @selected((string)($filters['warehouse_id'] ?? '') === (string)$wh->id)>{{ $wh->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid min-w-[200px] flex-1 gap-1.5">
                    <label class="text-sm font-medium" for="search">Search Product</label>
                    <input id="search" name="search" placeholder="Name or SKU..." value="{{ $filters['search'] ?? '' }}"
                           class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
                    <a href="{{ route('reports.inventory-health') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Clear</a>
                </div>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-zinc-50 text-left">
                        <th class="px-3 py-2 font-medium text-gray-600">Product</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600">Sold (30d)</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600">Sold (90d)</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600">Current Stock</th>
                        <th class="px-3 py-2 font-medium text-gray-600">Last Sold</th>
                        <th class="px-3 py-2 font-medium text-gray-600">Status</th>
                        <th class="px-3 py-2 font-medium text-gray-600">Recommendation</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                    @php $status = $getStatus($item); @endphp
                    <tr class="border-b transition-colors hover:bg-zinc-50/50">
                        <td class="px-3 py-2">
                            <div class="flex flex-col">
                                <a href="/items/{{ $item->id }}" class="font-bold text-blue-600 hover:underline">{{ $item->name }}</a>
                                <span class="font-mono text-xs text-zinc-500">{{ $item->code }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-2 text-right font-medium">{{ $fmtNum($item->sold_30 ?? 0) }}</td>
                        <td class="px-3 py-2 text-right font-medium">{{ $fmtNum($item->sold_90 ?? 0) }}</td>
                        <td class="px-3 py-2 text-right font-bold tabular-nums">{{ $fmtNum($item->current_stock ?? 0) }}</td>
                        <td class="px-3 py-2 text-sm">{{ $item->last_sold_at ? \Illuminate\Support\Carbon::parse($item->last_sold_at)->format('d M Y') : '-' }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex w-fit items-center gap-1 rounded-full px-2 py-0.5 text-xs text-white {{ $status['color'] }}">{{ $status['label'] }}</span>
                        </td>
                        <td class="px-3 py-2">
                            <span class="text-xs font-medium text-zinc-700">{{ $status['rec'] }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="h-32 text-center text-zinc-500">No items found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $items, 'label' => 'items'])
    </div>
</div>
@endsection

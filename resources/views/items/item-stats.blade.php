@extends('layouts.app')

@section('title', 'Stats: ' . $item->name)

@section('content')
@php
$base = $isAsset ? '/assetlancar' : '/items';
$breadcrumbs = [
    ['title' => $isAsset ? 'Assets' : 'Items', 'href' => $base],
    ['title' => $item->name, 'href' => $base.'/'.$item->id],
    ['title' => 'Stats', 'href' => '#'],
];
$fmt = fn ($n) => number_format((float) $n, 0, ',', '.');
@endphp

<div class="p-4 sm:p-6">
    <div class="mb-4 flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
            <div class="mb-2 flex items-center gap-2">
                <a href="{{ $base }}/{{ $item->id }}" class="text-gray-500 hover:text-gray-900">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <span class="font-mono text-sm text-gray-400">#{{ $item->code }}</span>
            </div>
            <h1 class="mb-1 text-2xl font-bold text-gray-900">Item Statistics</h1>
            <p class="text-sm text-gray-500">Sell / return demand for <span class="text-blue-600">{{ $item->name }}</span> (cached product-performance data, net of returns, after invoice discount).</p>
        </div>

        <form method="GET" action="{{ $base }}/{{ $item->id }}/stats" class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4">
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium uppercase text-gray-500">Period</label>
                <select name="period" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                    @foreach($periodOptions as $days => $label)
                        <option value="{{ $days }}" @selected((int) $filters['period'] === (int) $days)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium uppercase text-gray-500">Warehouse</label>
                <select name="warehouse_id" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                    <option value="">All warehouses</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" @selected((int) ($filters['warehouse_id'] ?? 0) === (int) $wh->id)>{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
        </form>
    </div>

    <div class="mb-4 text-xs text-gray-500">
        @if($syncedAt)
            Stats last updated {{ $syncedAt->diffForHumans() }}.
            @if($stale)
                <span class="text-amber-700">May be stale — run <code class="text-xs">app:recalculate-warehouse-item-stats</code>.</span>
            @endif
        @elseif(! $hasData)
            <span class="text-amber-700">No cached stats yet. Run <code class="text-xs">php artisan app:recalculate-warehouse-item-stats</code>.</span>
        @endif
    </div>

    @include('items.partials.item-tabs', ['active' => 'Stats'])

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 text-[10px] uppercase tracking-widest text-gray-500">
                        <th class="px-6 py-3 font-bold">Month</th>
                        <th class="px-6 py-3 text-right font-bold">Sold qty</th>
                        <th class="px-6 py-3 text-right font-bold">Return qty</th>
                        <th class="px-6 py-3 text-right font-bold">Net qty</th>
                        <th class="px-6 py-3 text-right font-bold">Sold value</th>
                        <th class="px-6 py-3 text-right font-bold">Return value</th>
                        <th class="px-6 py-3 text-right font-bold">Net value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($months as $row)
                    <tr class="hover:bg-gray-50/50">
                        <td class="whitespace-nowrap px-6 py-3 font-bold text-gray-800">{{ $row['label'] }}</td>
                        <td class="px-6 py-3 text-right font-mono text-blue-600">{{ $row['sold_qty'] > 0 ? $fmt($row['sold_qty']) : '-' }}</td>
                        <td class="px-6 py-3 text-right font-mono text-purple-600">{{ $row['returned_qty'] > 0 ? $fmt($row['returned_qty']) : '-' }}</td>
                        <td class="px-6 py-3 text-right font-mono font-semibold text-gray-900">{{ $row['net_qty'] > 0 ? $fmt($row['net_qty']) : '-' }}</td>
                        <td class="px-6 py-3 text-right font-mono text-blue-600">{{ $row['sold_value'] > 0 ? $fmt($row['sold_value']) : '-' }}</td>
                        <td class="px-6 py-3 text-right font-mono text-purple-600">{{ $row['returned_value'] > 0 ? $fmt($row['returned_value']) : '-' }}</td>
                        <td class="px-6 py-3 text-right font-mono font-semibold text-gray-900">{{ $row['net_value'] > 0 ? $fmt($row['net_value']) : '-' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-6 py-12 text-center italic text-gray-500">No statistical data available for this period.</td></tr>
                    @endforelse
                </tbody>
                @if(count($months) > 0)
                <tfoot class="border-t border-gray-200 bg-gray-50 font-bold">
                    <tr>
                        <td class="px-6 py-3 text-sm uppercase tracking-wider text-gray-700">Total</td>
                        <td class="px-6 py-3 text-right font-mono text-blue-600">{{ $fmt($totals['sold_qty']) }}</td>
                        <td class="px-6 py-3 text-right font-mono text-purple-600">{{ $fmt($totals['returned_qty']) }}</td>
                        <td class="px-6 py-3 text-right font-mono text-gray-900">{{ $fmt($totals['net_qty']) }}</td>
                        <td class="px-6 py-3 text-right font-mono text-blue-600">{{ $fmt($totals['sold_value']) }}</td>
                        <td class="px-6 py-3 text-right font-mono text-purple-600">{{ $fmt($totals['returned_value']) }}</td>
                        <td class="px-6 py-3 text-right font-mono text-gray-900">{{ $fmt($totals['net_value']) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection

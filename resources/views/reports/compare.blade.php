@extends('layouts.app')

@section('title', 'Compare Warehouse')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'Reports', 'href' => '#'],
    ['title' => 'Compare Warehouse', 'href' => route('reports.compare')],
];
$sort = $filters['sort'] ?? 'name';
$direction = $filters['direction'] ?? 'asc';
$meta = $items['meta'];
$availableWarehouses = collect($allWarehouses)->reject(function ($wh) use ($selectedWarehouses) {
    return $selectedWarehouses->contains('warehouse_id', $wh->id);
})->values();

$sortLink = function ($column) use ($sort, $direction, $filters) {
    $newDir = ($sort === $column && $direction === 'asc') ? 'desc' : 'asc';
    return route('reports.compare', ['search' => $filters['search'] ?? '', 'sort' => $column, 'direction' => $newDir]);
};
@endphp

<div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4" x-data="{ addOpen: {{ $errors->any() ? 'true' : 'false' }} }">
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Compare Warehouse</h1>
            <p class="text-zinc-500">Compare item quantities across multiple warehouses.</p>
        </div>
        <button @click="addOpen = true" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Warehouse
        </button>
    </div>

    {{-- Add Warehouse Modal --}}
    <div x-show="addOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="addOpen = false">
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <h3 class="text-lg font-bold">Add Warehouse to Compare</h3>
            <form method="POST" action="{{ route('reports.compare.store') }}" class="mt-4 space-y-4">
                @csrf
                <div class="space-y-2">
                    <label class="text-sm font-medium" for="warehouse_id">Select Warehouse</label>
                    <select id="warehouse_id" name="warehouse_id" class="w-full rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                        <option value="">Choose a warehouse...</option>
                        @foreach($availableWarehouses as $wh)
                            <option value="{{ $wh->id }}" @selected(old('warehouse_id') == $wh->id)>{{ $wh->name }}</option>
                        @endforeach
                    </select>
                    @error('warehouse_id')<p class="text-sm text-rose-500">{{ $message }}</p>@enderror
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="addOpen = false" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Add to Comparison</button>
                </div>
            </form>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="p-4">
            <form method="GET" action="{{ route('reports.compare') }}" class="flex flex-wrap items-end gap-4">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">
                <div class="grid min-w-[200px] flex-1 gap-1.5">
                    <label class="text-sm font-medium" for="search">Search Product</label>
                    <input id="search" name="search" placeholder="Search by name or SKU..." value="{{ $filters['search'] ?? '' }}"
                           class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
                    <a href="{{ route('reports.compare') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Clear</a>
                </div>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-zinc-50 text-left">
                        <th class="min-w-[250px] px-3 py-2 font-medium text-gray-600">
                            <a href="{{ $sortLink('name') }}" class="flex items-center gap-1 hover:text-gray-900">Product <span class="text-xs">{{ $sort === 'name' ? ($direction === 'asc' ? '▲' : '▼') : '↕' }}</span></a>
                        </th>
                        <th class="px-3 py-2 font-medium text-gray-600">
                            <a href="{{ $sortLink('code') }}" class="flex items-center gap-1 hover:text-gray-900">SKU <span class="text-xs">{{ $sort === 'code' ? ($direction === 'asc' ? '▲' : '▼') : '↕' }}</span></a>
                        </th>
                        @forelse($selectedWarehouses as $sw)
                        <th class="min-w-[150px] px-3 py-2 text-right font-medium text-gray-600">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ $sortLink('wh_'.$sw->warehouse_id) }}" class="max-w-[120px] truncate hover:text-gray-900" title="{{ $sw->warehouse->name }}">{{ $sw->warehouse->name }}</a>
                                <span class="text-xs">{{ $sort === 'wh_'.$sw->warehouse_id ? ($direction === 'asc' ? '▲' : '▼') : '↕' }}</span>
                                <form method="POST" action="{{ route('reports.compare.destroy', $sw->id) }}" onsubmit="return confirm('Are you sure you want to remove this warehouse from comparison?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-rose-500 hover:text-rose-700" title="Remove">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </th>
                        @empty
                        <th class="px-3 py-2 font-medium italic text-gray-400">Add warehouses to compare quantities</th>
                        @endforelse
                    </tr>
                </thead>
                <tbody>
                    @forelse($items['data'] as $item)
                    <tr class="border-b transition-colors hover:bg-zinc-50/50">
                        <td class="px-3 py-2 font-medium"><a href="/items/{{ $item['id'] }}" class="text-blue-600 hover:underline">{{ $item['name'] }}</a></td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $item['code'] }}</td>
                        @foreach($selectedWarehouses as $sw)
                        @php $qty = (float) ($item['wh_'.$sw->warehouse_id] ?? 0); @endphp
                        <td class="px-3 py-2 text-right tabular-nums">
                            <span class="{{ $qty > 0 ? 'font-bold text-zinc-900' : 'text-zinc-400' }}">{{ format_amount($qty, 0) }}</span>
                        </td>
                        @endforeach
                        @if($selectedWarehouses->count() === 0)<td></td>@endif
                    </tr>
                    @empty
                    <tr><td colspan="{{ $selectedWarehouses->count() + 2 }}" class="h-48 text-center text-gray-500">No products found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($meta['total'] > 0)
        <div class="flex items-center justify-between border-t border-gray-200 bg-gray-50/40 px-4 py-3 text-sm">
            <div class="text-gray-500">
                Showing <span class="font-medium">{{ $meta['from'] }}</span>
                to <span class="font-medium">{{ $meta['to'] }}</span>
                of <span class="font-medium">{{ $meta['total'] }}</span> products
            </div>
            <div class="flex items-center gap-1">
                @foreach($items['links'] as $link)
                    <a href="{{ $link['url'] ?: '#' }}"
                       class="rounded-md border px-2.5 py-1 text-xs {!! $link['active'] ? 'border-blue-700 bg-blue-700 text-white' : 'border-gray-200 bg-white hover:bg-gray-50' !!} {{ $link['url'] ? '' : 'pointer-events-none opacity-40' }}">{!! $link['label'] !!}</a>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

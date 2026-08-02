@extends('layouts.app')

@section('title', 'Item Sales: ' . $addrbook->name)

@section('content')
@php
$baseUrl = '/' . $addrbook->type_slug . '/' . $addrbook->id . '/item-sales';
$breadcrumbs = [
    ['title' => 'Address Book', 'href' => route('addrbook.index')],
    ['title' => $addrbook->name, 'href' => '/' . $addrbook->type_slug . '/' . $addrbook->id],
    ['title' => 'Item Sales', 'href' => $baseUrl],
];
$months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$typeColors = [2 => 'text-blue-700 bg-blue-50', 15 => 'text-purple-700 bg-purple-50'];
$typeNames = collect($transactionTypes)->keyBy('id');
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    {{-- Header --}}
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
            <div class="mb-1 flex items-center gap-2">
                <a href="/{{ $addrbook->type_slug }}/{{ $addrbook->id }}" class="text-gray-400 hover:text-gray-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <span class="font-mono text-sm text-gray-400">#{{ $addrbook->id }}</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Monthly Item Sales</h1>
            <p class="text-sm text-gray-500">Sales performance for <span class="text-blue-600">{{ $addrbook->name }}</span></p>
        </div>
    </div>

    @include('addrbook.partials.tabs', ['active' => 'item-sales'])

    {{-- Filters --}}
    <form method="GET" action="{{ $baseUrl }}" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-white p-4">
        <div class="flex flex-1 min-w-[220px] flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Search Item Group</label>
            <div class="relative">
                <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name or Description…" class="w-full rounded-md border border-gray-300 py-1.5 pl-9 pr-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Month</label>
            <select name="bulan" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <option value="">All Months</option>
                @foreach($months as $i => $m)
                    <option value="{{ $i + 1 }}" @selected(($filters['bulan'] ?? null) === $i + 1)>{{ $m }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Year</label>
            <select name="tahun" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                @foreach($years as $y)
                    <option value="{{ $y }}" @selected(($filters['tahun'] ?? null) === $y)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Type</label>
            <select name="type" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <option value="">All Types</option>
                <option value="2" @selected(($filters['type'] ?? null) === 2)>Sell</option>
                <option value="15" @selected(($filters['type'] ?? null) === 15)>Return</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Search</button>
            <a href="{{ $baseUrl }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</a>
        </div>
    </form>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-[10px] uppercase tracking-widest text-gray-500">
                    <tr>
                        <th class="px-6 py-4 font-bold">Period</th>
                        <th class="px-6 py-4 font-bold">Type</th>
                        <th class="px-6 py-4 font-bold">Item Group</th>
                        <th class="px-6 py-4 text-right font-bold">Qty</th>
                        <th class="px-6 py-4 text-right font-bold">Total Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($sales as $sale)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-medium text-gray-800">{{ $months[$sale->bulan - 1] ?? $sale->bulan }} {{ $sale->tahun }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $typeColors[$sale->type] ?? 'text-gray-700 bg-gray-50' }}">
                                    {{ $typeNames[$sale->type]['name'] ?? 'Other' }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium text-gray-800">{{ $sale->group->description ?? $sale->group->name ?? 'Unknown Group' }}</span>
                                    <span class="font-mono text-[10px] text-gray-400">{{ $sale->group->name ?? '' }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <span class="font-mono text-sm font-bold {{ $sale->type == 2 ? 'text-emerald-600' : 'text-rose-600' }}">{{ number_format((float) $sale->sum_qty, 0, ',', '.') }}</span>
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <span class="text-sm font-semibold text-gray-700">IDR {{ number_format((float) $sale->sum_total, 0, ',', '.') }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-12 text-center italic text-gray-500">No sales data found for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-200 px-6 py-4">
            @include('partials.pagination', ['paginator' => $sales, 'label' => 'records'])
        </div>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Transactions')

@php
    $typeMap = [
        1  => ['Buy',           'text-emerald-700 bg-emerald-50', 'bg-emerald-500'],
        2  => ['Sell',          'text-blue-700 bg-blue-50',       'bg-blue-500'],
        3  => ['Move',          'text-amber-700 bg-amber-50',     'bg-amber-500'],
        6  => ['Transfer',      'text-cyan-700 bg-cyan-50',       'bg-cyan-500'],
        7  => ['Cash Out',      'text-rose-700 bg-rose-50',       'bg-rose-500'],
        8  => ['Use',           'text-yellow-700 bg-yellow-50',   'bg-yellow-500'],
        9  => ['Cash In',       'text-purple-700 bg-purple-50',   'bg-purple-500'],
        12 => ['Adjust',        'text-indigo-700 bg-indigo-50',   'bg-indigo-500'],
        15 => ['Return',        'text-rose-700 bg-rose-50',       'bg-rose-500'],
        16 => ['Production',    'text-slate-700 bg-slate-50',     'bg-slate-500'],
        17 => ['Ret. Supplier', 'text-orange-700 bg-orange-50',   'bg-orange-500'],
        18 => ['Depreciation',  'text-zinc-700 bg-zinc-50',       'bg-zinc-500'],
    ];

    // Build a sort link that preserves current filters
    $sortLink = function (string $column) use ($filters, $sort, $direction) {
        $nextDir = ($sort === $column && $direction === 'asc') ? 'desc' : 'asc';
        return route('transactions.index', array_merge($filters, ['sort' => $column, 'direction' => $nextDir]));
    };
@endphp

@php
    $perPage = $perPage ?? (int) request()->query('per_page', 100);
    $exportQuery = array_merge(request()->query(), ['per_page' => $perPage, 'page' => $rows->currentPage()]);
@endphp

@section('content')
<div class="flex flex-col gap-3 p-3 sm:p-4">

    {{-- Header --}}
    <div class="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Transactions</h2>
            <p class="mt-0.5 text-sm text-gray-500">
                {{ number_format($rows->total()) }} record{{ $rows->total() === 1 ? '' : 's' }} found.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($can['type_buy'])
                <a href="{{ route('transactions.create', 'buy') }}" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">+ Buy</a>
            @endif
            @if($can['type_sell'])
                <a href="{{ route('transactions.create', 'sell') }}" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">+ Sell</a>
            @endif
            @if($can['delete_transaction'])
                <a href="{{ route('transactions.deleted.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-white px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Deleted
                </a>
            @endif
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('transactions.index') }}"
          class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-white p-3">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">From</label>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"
                   class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">To</label>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"
                   class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Type</label>
            <select name="type" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <option value="">All Types</option>
                @foreach($typeMap as $id => $meta)
                    <option value="{{ $id }}" @selected(($filters['type'] ?? '') == $id)>{{ $meta[0] }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Invoice</label>
            <input type="text" name="invoice" value="{{ $filters['invoice'] ?? '' }}" placeholder="Search invoice…"
                   class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Min Total</label>
            <input type="number" name="min_total" value="{{ $filters['min_total'] ?? '' }}" placeholder="0"
                   class="w-28 rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Max Total</label>
            <input type="number" name="max_total" value="{{ $filters['max_total'] ?? '' }}" placeholder="∞"
                   class="w-28 rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        </div>
        <input type="hidden" name="sort" value="{{ $sort }}">
        <input type="hidden" name="direction" value="{{ $direction }}">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Rows / page</label>
            <select name="per_page" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                @foreach([100, 200, 300] as $size)
                    <option value="{{ $size }}" @selected($perPage == $size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
            <a href="{{ route('transactions.index') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</a>
        </div>
    </form>

    <div class="flex items-center justify-end">
        <a href="{{ route('transactions.export', $exportQuery) }}"
           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Export Excel
        </a>
    </div>

    {{-- Table (shared with a contact's transactions page) --}}
    @include('transactions.partials.list-table', [
        'rows' => $rows,
        'can' => $can,
        'sortLink' => $sortLink,
        'sort' => $sort,
        'direction' => $direction,
    ])
</div>
@endsection

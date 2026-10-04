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
    $hasActiveFilters = collect($filters)
        ->filter(fn ($value) => $value !== null && $value !== '')
        ->isNotEmpty();
@endphp

@section('content')
<div class="ui-page">
    @php
        ob_start();
    @endphp
            @if($can['type_buy'])
                <a href="{{ route('transactions.create', 'buy') }}" class="ui-btn ui-btn-sm bg-emerald-600 text-white shadow-sm hover:bg-emerald-700">+ Buy</a>
            @endif
            @if($can['type_sell'])
                <a href="{{ route('transactions.create', 'sell') }}" class="ui-btn ui-btn-primary ui-btn-sm">+ Sell</a>
            @endif
            @if($can['delete_transaction'])
                <a href="{{ route('transactions.deleted.index') }}"
                   class="ui-btn ui-btn-secondary ui-btn-sm text-rose-600 !shadow-[inset_0_0_0_1px_rgb(254_205_211)] hover:bg-rose-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Deleted
                </a>
            @endif
    @php
        $txHeaderActions = ob_get_clean();
    @endphp
    @include('partials.ui.page-header', [
        'title' => 'Transactions',
        'lead' => number_format($rows->total()).' record'.($rows->total() === 1 ? '' : 's').' found.',
        'actionsHtml' => $txHeaderActions,
    ])

    @include('transactions.partials.list-filters', [
        'filters' => $filters,
        'typeMap' => $typeMap,
        'sort' => $sort,
        'direction' => $direction,
        'perPage' => $perPage,
        'defaultOpen' => $hasActiveFilters,
    ])

    {{-- Table (shared with a contact's transactions page) --}}
    @include('transactions.partials.list-table', [
        'rows' => $rows,
        'can' => $can,
        'sortLink' => $sortLink,
        'sort' => $sort,
        'direction' => $direction,
        'exportExcelUrl' => route('transactions.export', $exportQuery),
    ])
</div>
@endsection

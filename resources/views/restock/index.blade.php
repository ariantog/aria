@extends('layouts.app')

@section('title', 'Restock')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Stuff', 'href' => '#'],
    ['title' => 'Restock', 'href' => route('restock.index')],
];
$qtyRestockTotal = $sheets->sum('qty_restock');
$qtyProductionTotal = $sheets->sum('qty_production');
$qtyShippedTotal = $sheets->sum('qty_shipped');
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Restock</h1>
            <p class="text-sm text-gray-500">Pipeline totals by restock sheet.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('restock.settings.edit') }}"
               class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Settings
            </a>
            <a href="{{ route('restock.missing.index') }}"
               class="inline-flex items-center justify-center rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-800 hover:bg-red-100">
                Missing SKUs
                @if(($missingCount ?? 0) > 0)
                    <span class="ml-2 inline-flex rounded-full bg-red-200 px-2 py-0.5 text-xs font-semibold text-red-900">{{ $missingCount }}</span>
                @endif
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    @if($typeTags->isEmpty())
        <div class="rounded-xl border border-gray-200 bg-white p-8 text-center text-gray-500">
            No asset lancar TYPE tags found. Create tags with type = Type and item type = Asset Lancar under Stuff → Tags.
        </div>
    @else
        @include('restock.partials.type-tabs', [
            'typeTags' => $typeTags,
            'activeTypeTag' => null,
            'activeOverview' => true,
        ])

        @if($sheets->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-gray-500"
                 data-testid="restock-sheets-empty">
                No restock sheets yet. Choose a product type to start tracking.
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm" data-testid="restock-sheets-table">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">Sheet</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">Restock</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">Production</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">Shipping</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($sheets as $sheet)
                            <tr class="hover:bg-gray-50" data-testid="restock-sheet-row-{{ $sheet['id'] }}">
                                <td class="px-4 py-3">
                                    <a href="{{ route('restock.sheets.show', $sheet['id']) }}"
                                       class="font-medium text-blue-600 hover:text-blue-800">
                                        {{ $sheet['name'] }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-900"
                                    data-qty-restock="{{ $sheet['qty_restock'] }}">
                                    {{ number_format($sheet['qty_restock']) }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-900"
                                    data-qty-production="{{ $sheet['qty_production'] }}">
                                    {{ number_format($sheet['qty_production']) }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-900"
                                    data-qty-shipping="{{ $sheet['qty_shipped'] }}">
                                    {{ number_format($sheet['qty_shipped']) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Total</th>
                            <th class="px-4 py-3 text-right tabular-nums font-medium text-gray-700"
                                data-qty-restock="{{ $qtyRestockTotal }}">
                                {{ number_format($qtyRestockTotal) }}
                            </th>
                            <th class="px-4 py-3 text-right tabular-nums font-medium text-gray-700"
                                data-qty-production="{{ $qtyProductionTotal }}">
                                {{ number_format($qtyProductionTotal) }}
                            </th>
                            <th class="px-4 py-3 text-right tabular-nums font-medium text-gray-700"
                                data-qty-shipping="{{ $qtyShippedTotal }}">
                                {{ number_format($qtyShippedTotal) }}
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    @endif
</div>
@endsection

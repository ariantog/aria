@extends('layouts.app')

@section('title', 'Restock recommendations')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Stuff', 'href' => '#'],
    ['title' => 'Restock', 'href' => route('restock.index')],
    ['title' => 'Recommendations', 'href' => route('restock.recommendations')],
];
$fmtCover = function (?float $days): string {
    if ($days === null) {
        return '—';
    }
    if ($days <= 0) {
        return '0';
    }
    if ($days < 0.1) {
        return '<0.1';
    }
    if ($days < 10) {
        return number_format($days, 1);
    }

    return number_format($days, 0);
};
$tabQuery = fn (string $next) => array_filter([
    'tab' => $next,
    'item_type' => $itemTypeQuery !== '' ? $itemTypeQuery : null,
    'from' => request()->query('from'),
    'to' => request()->query('to'),
]);
$typeQuery = fn (string $typeValue) => array_filter([
    'tab' => $tab,
    'item_type' => $typeValue !== '' ? $typeValue : null,
    'from' => request()->query('from'),
    'to' => request()->query('to'),
]);
@endphp

<div class="flex flex-col gap-4 p-4" data-testid="restock-recommendations-page">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Restock recommendations</h1>
            <p class="mt-1 text-sm text-gray-500">
                Suggestions from
                <a href="{{ route('reports.inventory-health') }}" class="text-blue-600 hover:underline">Inventory Health</a>
                (fast movers / low stock) and
                <a href="{{ route('reports.item-insights') }}" class="text-blue-600 hover:underline">Item Insights</a>
                (margin &amp; velocity). Uses warehouse stats and insight snapshots — not the reporting cutover summaries.
            </p>
        </div>
        <a href="{{ route('restock.index') }}"
           class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Back to restock sheets
        </a>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-600">
        <div class="flex flex-wrap gap-x-6 gap-y-2">
            <span>
                <span class="font-medium text-gray-900">Health window:</span>
                {{ $healthWindows['period_from'] }} → {{ $healthWindows['period_to'] }}
                ({{ $healthWindows['period_days'] }} days, source: {{ $healthSource }})
            </span>
            @if($insightCalculated && $insightPeriod)
                <span>
                    <span class="font-medium text-gray-900">Item insights:</span>
                    {{ $insightPeriod->periodLabel() }}
                    @if($insightPeriod->calculated_at)
                        <span class="text-gray-400">· calculated {{ $insightPeriod->calculated_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</span>
                    @endif
                </span>
            @else
                <span class="text-amber-700">Item insights not calculated yet — high-margin tab will be empty until you recalculate on Item Insights.</span>
            @endif
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-medium uppercase tracking-wide text-gray-500">Item type</span>
        @foreach($itemTypeOptions as $value => $label)
            <a href="{{ route('restock.recommendations', $typeQuery((string) $value)) }}"
               class="rounded-lg px-3 py-1.5 text-sm font-medium {{ (string) $itemTypeQuery === (string) $value ? 'bg-gray-900 text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}"
               data-testid="restock-recommendations-type-{{ $value === '' ? 'all' : $value }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('restock.recommendations', $tabQuery('fast')) }}"
           class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $tab === 'fast' ? 'bg-blue-600 text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}"
           data-testid="restock-recommendations-tab-fast">
            Fast selling · low / out of stock ({{ $fastMoving->count() }})
        </a>
        <a href="{{ route('restock.recommendations', $tabQuery('margin')) }}"
           class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $tab === 'margin' ? 'bg-blue-600 text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}"
           data-testid="restock-recommendations-tab-margin">
            High margin ({{ $highMargin->count() }})
        </a>
    </div>

    @if($tab === 'fast')
        @if($fastMoving->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-gray-500" data-testid="restock-recommendations-empty">
                No fast-moving low-stock SKUs for the selected health window.
                <a href="{{ $inventoryHealthUrl }}" class="text-blue-600 hover:underline">Open Inventory Health</a>
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm" data-testid="restock-recommendations-fast-table">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-3 py-2">SKU</th>
                            <th class="px-3 py-2 text-right">Stock</th>
                            <th class="px-3 py-2 text-right">Net sold (period)</th>
                            <th class="px-3 py-2 text-right">Days cover</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Why restock</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($fastMoving as $row)
                            <tr data-testid="restock-recommendation-row-{{ $row['item_id'] }}">
                                <td class="px-3 py-2">
                                    <a href="{{ $row['show_url'] }}" class="font-medium text-blue-600 hover:underline">
                                        {{ $row['item_code'] ?: $row['item_id'] }}
                                    </a>
                                    <div class="text-xs text-gray-500">{{ $row['item_name'] }}</div>
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row['stock_qty'], 0) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row['net_period'], 0) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $fmtCover($row['days_of_cover']) }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $row['health_label'] }}</td>
                                <td class="px-3 py-2 text-gray-600">
                                    <ul class="list-disc pl-4 space-y-0.5">
                                        @foreach($row['reasons'] as $reason)
                                            <li>{{ $reason }}</li>
                                        @endforeach
                                    </ul>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @else
        @if($highMargin->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-gray-500" data-testid="restock-recommendations-empty">
                @if(! $insightCalculated)
                    Recalculate Item Insights for a recent month to populate high-margin picks.
                @else
                    No high-margin SKUs need restock right now (margin ≥ {{ \App\Services\Restock\RestockRecommendationService::HIGH_MARGIN_MIN_PCT }}%, not overstocked).
                @endif
                <a href="{{ $itemInsightsUrl }}" class="text-blue-600 hover:underline">Open Item Insights</a>
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm" data-testid="restock-recommendations-margin-table">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-3 py-2">SKU</th>
                            <th class="px-3 py-2 text-right">Margin</th>
                            <th class="px-3 py-2 text-right">Stock</th>
                            <th class="px-3 py-2 text-right">Days cover</th>
                            <th class="px-3 py-2">Health</th>
                            <th class="px-3 py-2">Why restock</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($highMargin as $row)
                            <tr data-testid="restock-recommendation-row-{{ $row['item_id'] }}">
                                <td class="px-3 py-2">
                                    <a href="{{ $row['show_url'] }}" class="font-medium text-blue-600 hover:underline">
                                        {{ $row['item_code'] ?: $row['item_id'] }}
                                    </a>
                                    <div class="text-xs text-gray-500">{{ $row['item_name'] }}</div>
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row['margin_pct'], 1) }}%</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row['stock_qty'], 0) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $fmtCover($row['days_of_cover']) }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $row['health_label'] }}</td>
                                <td class="px-3 py-2 text-gray-600">
                                    <ul class="list-disc pl-4 space-y-0.5">
                                        @foreach($row['reasons'] as $reason)
                                            <li>{{ $reason }}</li>
                                        @endforeach
                                    </ul>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
@endsection

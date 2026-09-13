@extends('layouts.app')

@section('title', 'Item Insights')

@section('content')
@php
use App\Models\ItemInsightRanking;

$breadcrumbs = [
    ['title' => 'Reports', 'href' => '#'],
    ['title' => 'Item Insights', 'href' => route('reports.item-insights')],
];
$queryParams = fn (array $extra = []) => array_filter(array_merge([
    'period' => $periodKey,
    'tab' => $category,
], $extra));
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Item Insights</h2>
        <p class="mt-0.5 text-sm text-gray-500">Monthly top items by sales, profit, loss leaders, and sell velocity. Figures are read from pre-calculated snapshots — use Recalculate to refresh a month.</p>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800" data-testid="item-insights-flash">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-wrap gap-2">
        @foreach($categoryLabels as $key => $label)
            <a href="{{ route('reports.item-insights', $queryParams(['tab' => $key])) }}"
               class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $category === $key ? 'bg-blue-600 text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <form method="GET" action="{{ route('reports.item-insights') }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-3 lg:col-span-2">
            <input type="hidden" name="tab" value="{{ $category }}">
            <div class="flex flex-col gap-1">
                <label for="period" class="text-xs font-medium uppercase text-gray-500">View month</label>
                <input type="month" name="period" id="period" value="{{ $periodKey }}"
                       class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm" data-testid="item-insights-period">
            </div>
            <button type="submit" class="rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-800">Apply</button>
        </form>

        <form method="POST" action="{{ route('reports.item-insights.recalculate') }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-3">
            @csrf
            <input type="hidden" name="tab" value="{{ $category }}">
            <div class="flex flex-col gap-1">
                <label for="recalc-period" class="text-xs font-medium uppercase text-gray-500">Recalculate month</label>
                <input type="month" name="period" id="recalc-period" value="{{ $periodKey }}"
                       class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm"
                       data-testid="item-insights-recalc-period">
            </div>
            <button type="submit" class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700" data-testid="item-insights-recalculate">
                Recalculate
            </button>
        </form>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-3 text-sm text-gray-600">
        @if($calculated && $monthMeta)
            <span class="text-gray-900 font-medium">{{ $periodKey }}</span> calculated
            {{ $monthMeta->calculated_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
            · {{ number_format($monthMeta->row_count) }} rows stored
        @else
            <span class="text-amber-700 font-medium">{{ $periodKey }} has not been calculated yet.</span>
            Choose a month below or run Recalculate for this period.
        @endif
        <p class="mt-1 text-xs text-gray-500">Profit uses current item cost × net qty; velocity is net units per calendar day in the month.</p>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-3">
        <h3 class="text-sm font-semibold text-gray-900">Calculated months</h3>
        @if($calculatedMonths->isEmpty())
            <p class="mt-2 text-sm text-gray-500">No months calculated yet.</p>
        @else
            <div class="mt-2 flex flex-wrap gap-2" data-testid="item-insights-month-tracker">
                @foreach($calculatedMonths as $entry)
                    <a href="{{ route('reports.item-insights', ['period' => $entry->periodLabel(), 'tab' => $category]) }}"
                       class="rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $entry->year === $year && $entry->month === $month ? 'border-blue-600 bg-blue-50 text-blue-800' : 'border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                        {{ $entry->periodLabel() }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-3 py-2">#</th>
                    <th class="px-3 py-2">Item</th>
                    <th class="px-3 py-2">SKU</th>
                    <th class="px-3 py-2 text-right">Net qty</th>
                    <th class="px-3 py-2 text-right">Net value</th>
                    @if(in_array($category, [ItemInsightRanking::CATEGORY_MOST_PROFITABLE, ItemInsightRanking::CATEGORY_LOSS_LEADER], true))
                        <th class="px-3 py-2 text-right">Cost</th>
                        <th class="px-3 py-2 text-right">Profit</th>
                        <th class="px-3 py-2 text-right">Margin %</th>
                    @endif
                    @if($category === ItemInsightRanking::CATEGORY_FASTEST_SELLING)
                        <th class="px-3 py-2 text-right">Units / day</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($rows as $row)
                    <tr>
                        <td class="px-3 py-2 text-gray-500">{{ $row->rank }}</td>
                        <td class="px-3 py-2 font-medium text-gray-900">{{ $row->item_name }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $row->item_code ?? '—' }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row->net_qty, 0) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row->net_value, 0) }}</td>
                        @if(in_array($category, [ItemInsightRanking::CATEGORY_MOST_PROFITABLE, ItemInsightRanking::CATEGORY_LOSS_LEADER], true))
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row->cost_total, 0) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums {{ $row->profit < 0 ? 'text-red-600' : 'text-gray-900' }}">{{ number_format($row->profit, 0) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $row->margin_pct !== null ? number_format($row->margin_pct, 1) : '—' }}</td>
                        @endif
                        @if($category === ItemInsightRanking::CATEGORY_FASTEST_SELLING)
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row->daily_velocity, 2) }}</td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-8 text-center text-gray-500">
                            @if($calculated)
                                No ranked items for this category in {{ $periodKey }}.
                            @else
                                Run Recalculate for {{ $periodKey }} to populate this table.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

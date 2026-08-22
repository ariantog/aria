@extends('layouts.app')

@section('title', 'Statistik Potong')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'Reports', 'href' => '#'],
    ['title' => 'Statistik Potong', 'href' => route('reports.produksi-potong')],
];
$monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$totalKitir = $workerSummary->sum('kitir_count');
$totalQty = $workerSummary->sum('total_qty');
$yearlyQty = $monthlyTotals->sum('total_qty');
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Statistik Potong</h1>
            <p class="text-sm text-gray-500">
                Potong output for
                {{ $filters['month'] ? $monthNames[$filters['month']].' '.$filters['year'] : 'year '.$filters['year'] }}
                (grouped by <span class="font-medium">potong date</span>).
            </p>
        </div>
        <a href="{{ route('reports.produksi-qc', ['year' => $filters['year'], 'month' => $filters['month'] ?? 0]) }}" class="text-sm font-medium text-blue-600 hover:underline">View QC statistics →</a>
    </div>

    @include('reports.partials.month-year-filter', [
        'action' => route('reports.produksi-potong'),
        'filters' => $filters,
        'yearList' => $yearList,
    ])

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-500">Total Kitir</p>
            <p class="text-2xl font-bold tabular-nums text-gray-900">{{ number_format($totalKitir) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-500">Total Pieces (Jumlah)</p>
            <p class="text-2xl font-bold tabular-nums text-blue-600">{{ number_format($totalQty) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-500">Year {{ $filters['year'] }} Total Pieces</p>
            <p class="text-2xl font-bold tabular-nums text-gray-900">{{ number_format($yearlyQty) }}</p>
        </div>
    </div>

    @if(! $filters['month'])
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-4 py-3">
            <h2 class="font-semibold text-gray-900">Monthly Overview — {{ $filters['year'] }}</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        @foreach($monthlyTotals as $row)
                        <th class="px-3 py-2 font-medium">{{ $monthNames[$row->month] }}</th>
                        @endforeach
                        <th class="px-3 py-2 font-medium">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-t">
                        @foreach($monthlyTotals as $row)
                        <td class="px-3 py-2 tabular-nums">{{ number_format($row->total_qty) }}</td>
                        @endforeach
                        <td class="px-3 py-2 font-bold tabular-nums">{{ number_format($yearlyQty) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-4 py-3">
            <h2 class="font-semibold text-gray-900">Per Worker</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Potong Worker</th>
                        <th class="px-4 py-3 text-right font-medium">Kitir</th>
                        <th class="px-4 py-3 text-right font-medium">Pieces</th>
                        <th class="px-4 py-3 text-right font-medium">Avg / Kitir</th>
                        <th class="px-4 py-3 text-right font-medium">Distinct SJP</th>
                        <th class="px-4 py-3 text-right font-medium">Distinct Kode</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($workerSummary as $row)
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $row->worker_name }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row->kitir_count) }}</td>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums text-blue-600">{{ number_format($row->total_qty) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row->avg_qty, 1) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row->sjp_count) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row->kode_count) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">No potong records in this period.</td></tr>
                    @endforelse
                    @if($workerSummary->isNotEmpty())
                    <tr class="bg-gray-50 font-bold">
                        <td class="px-4 py-3">Total</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($totalKitir) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-blue-600">{{ number_format($totalQty) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $totalKitir > 0 ? number_format($totalQty / $totalKitir, 1) : '0' }}</td>
                        <td class="px-4 py-3" colspan="2"></td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Laporan PPh Final')

@section('content')
@php
$fmt = fn ($v) => format_amount($v);
$monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$csvQuery = http_build_query([
    'year' => $filters['year'],
    'month' => $filters['month'],
    'entity' => $filters['entity'],
    'export' => 'csv',
]);
$xlsxQuery = http_build_query([
    'year' => $filters['year'],
    'month' => $filters['month'],
    'entity' => $filters['entity'],
    'export' => 'xlsx',
]);
$ratePct = rtrim(rtrim(number_format($report['rate'] * 100, 2, '.', ''), '0'), '.');
@endphp

<div
    class="flex flex-col gap-4 p-4"
    x-data="{
        detailOpen: true,
        toggleDetail() { this.detailOpen = !this.detailOpen },
        isDetailOpen() { return this.detailOpen },
    }"
    data-testid="pph-page"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Laporan PPh Final</h2>
            <p class="mt-1 text-sm text-gray-500">
                {{ $report['entity_label'] }} — {{ $monthNames[$filters['month']] }} {{ $filters['year'] }}
                (non-PKP CashIn × {{ $ratePct }}%, data {{ \App\Services\Reporting\PphFinalReportService::MIN_YEAR }}+)
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a
                href="{{ route('reports.tax.pph') }}?{{ $csvQuery }}"
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                data-testid="pph-export-csv"
            >
                Export CSV
            </a>
            <a
                href="{{ route('reports.tax.pph') }}?{{ $xlsxQuery }}"
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                data-testid="pph-export-xlsx"
            >
                Export Excel
            </a>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('reports.tax.pph') }}" class="flex flex-wrap items-end gap-4">
            <div class="grid w-[180px] gap-1.5">
                <label class="text-sm font-medium" for="month">Bulan</label>
                <select id="month" name="month" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm" data-testid="pph-month">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected((int) $filters['month'] === $m)>{{ $monthNames[$m] }}</option>
                    @endfor
                </select>
            </div>
            <div class="grid w-[140px] gap-1.5">
                <label class="text-sm font-medium" for="year">Tahun</label>
                <select id="year" name="year" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm" data-testid="pph-year">
                    @foreach($yearList as $y)
                        <option value="{{ $y }}" @selected((int) $filters['year'] === (int) $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid min-w-[220px] gap-1.5">
                <label class="text-sm font-medium" for="entity">Entitas</label>
                <select id="entity" name="entity" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm" data-testid="pph-entity">
                    <option value="{{ \App\Services\Reporting\PphFinalReportService::CONSOLIDATED_ENTITY }}" @selected((int) $filters['entity'] === \App\Services\Reporting\PphFinalReportService::CONSOLIDATED_ENTITY)>
                        Konsolidasi (non-PKP)
                    </option>
                    @foreach($entities as $entity)
                        <option value="{{ $entity->id }}" @selected((int) $filters['entity'] === $entity->id)>
                            {{ $entity->name }} (non-PKP)
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
                <a href="{{ route('reports.tax.pph') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Clear</a>
            </div>
        </form>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" data-testid="pph-ringkasan">
        <h3 class="mb-3 text-sm font-semibold text-gray-900">Ringkasan</h3>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <p class="text-xs text-gray-500">Gross CashIn</p>
                <p class="text-lg font-semibold tabular-nums text-gray-900">{{ $fmt($report['gross_cash_in']) }}</p>
            </div>
            <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-3">
                <p class="text-xs text-emerald-800">Net omzet (CashIn − CashOut pihak sama)</p>
                <p class="text-lg font-semibold tabular-nums text-emerald-900">{{ $fmt($report['net_omzet']) }}</p>
            </div>
            <div class="rounded-lg border border-blue-100 bg-blue-50 p-3">
                <p class="text-xs text-blue-700">PPh Final ({{ $ratePct }}%)</p>
                <p class="text-lg font-semibold tabular-nums text-blue-900">{{ $fmt($report['pph_final']) }}</p>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <p class="text-xs text-gray-500">Tax Paid</p>
                <p class="text-lg font-semibold tabular-nums text-gray-900">{{ $fmt($report['tax_paid']) }}</p>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <button
            type="button"
            class="flex w-full items-center justify-between px-4 py-3 text-left"
            @click="toggleDetail()"
            data-testid="pph-detail-toggle"
        >
            <span class="text-sm font-semibold text-gray-900">Net omzet per pihak ({{ $report['rows']->count() }} baris)</span>
            <svg :class="isDetailOpen() ? 'rotate-180' : ''" class="h-4 w-4 text-gray-500 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div x-show="isDetailOpen()" x-cloak class="border-t border-gray-100">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" data-testid="pph-cashin-table">
                    <thead>
                        <tr class="border-b bg-gray-50 text-left text-xs text-gray-500">
                            <th class="px-3 py-2 font-medium">Pihak</th>
                            @if($report['is_consolidated'])
                                <th class="px-3 py-2 font-medium">Entitas</th>
                            @endif
                            <th class="px-3 py-2 text-right font-medium">Cash In</th>
                            <th class="px-3 py-2 text-right font-medium">Cash Out</th>
                            <th class="px-3 py-2 text-right font-medium">Net omzet</th>
                            <th class="px-3 py-2 text-right font-medium">PPh Final</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report['rows'] as $row)
                            <tr class="border-b">
                                <td class="px-3 py-2">
                                    <a href="{{ route('addrbook.transactions', $row['party_id']) }}" class="text-blue-600 hover:underline">{{ $row['party'] }}</a>
                                </td>
                                @if($report['is_consolidated'])
                                    <td class="px-3 py-2">{{ $row['entity_name'] }}</td>
                                @endif
                                <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($row['cash_in_gross']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($row['cash_out_gross']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-medium">{{ $fmt($row['net_omzet']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($row['pph_final']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $report['is_consolidated'] ? 8 : 7 }}" class="px-3 py-6 text-center text-gray-500">Tidak ada CashIn non-PKP untuk periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

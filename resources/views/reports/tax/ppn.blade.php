@extends('layouts.app')

@section('title', 'Laporan PPN')

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
@endphp

<div
    class="flex flex-col gap-4 p-4"
    x-data="{
        keluaranOpen: true,
        masukanOpen: true,
    }"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Laporan PPN</h2>
            <p class="mt-1 text-sm text-gray-500">
                {{ $entityLabel }} — {{ $monthNames[$filters['month']] }} {{ $filters['year'] }}
                (data agregat {{ \App\Services\Reporting\TaxReportService::MIN_YEAR }}+)
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('report-tax-faktur')
            <a
                href="{{ route('reports.tax.faktur.index') }}"
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
                Faktur Pajak
            </a>
            @endcan
            <a
                href="{{ route('reports.tax.ppn') }}?{{ $csvQuery }}"
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                data-testid="ppn-export-csv"
            >
                Export CSV
            </a>
            <a
                href="{{ route('reports.tax.ppn') }}?{{ $xlsxQuery }}"
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                data-testid="ppn-export-xlsx"
            >
                Export Excel
            </a>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('reports.tax.ppn') }}" class="flex flex-wrap items-end gap-4">
            <div class="grid w-[180px] gap-1.5">
                <label class="text-sm font-medium" for="month">Bulan</label>
                <select id="month" name="month" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected((int) $filters['month'] === $m)>{{ $monthNames[$m] }}</option>
                    @endfor
                </select>
            </div>
            <div class="grid w-[140px] gap-1.5">
                <label class="text-sm font-medium" for="year">Tahun</label>
                <select id="year" name="year" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                    @foreach($yearList as $y)
                        <option value="{{ $y }}" @selected((int) $filters['year'] === (int) $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid min-w-[220px] gap-1.5">
                <label class="text-sm font-medium" for="entity">Entitas</label>
                <select id="entity" name="entity" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                    <option value="{{ \App\Services\Reporting\TaxReportService::CONSOLIDATED_ENTITY }}" @selected((int) $filters['entity'] === \App\Services\Reporting\TaxReportService::CONSOLIDATED_ENTITY)>
                        Konsolidasi (semua entitas aktif)
                    </option>
                    @foreach($entities as $entity)
                        <option value="{{ $entity->id }}" @selected((int) $filters['entity'] === $entity->id)>
                            {{ $entity->name }}{{ $entity->is_pkp ? '' : ' (non-PKP)' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
                <a href="{{ route('reports.tax.ppn') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Clear</a>
            </div>
        </form>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" data-testid="ppn-ringkasan">
        <h3 class="mb-3 text-sm font-semibold text-gray-900">Ringkasan</h3>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <p class="text-xs text-gray-500">Keluaran DPP</p>
                <p class="text-lg font-semibold tabular-nums text-gray-900">{{ $fmt($ringkasan['keluaran_dpp']) }}</p>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <p class="text-xs text-gray-500">Keluaran PPN</p>
                <p class="text-lg font-semibold tabular-nums text-gray-900">{{ $fmt($ringkasan['keluaran_tax']) }}</p>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <p class="text-xs text-gray-500">Masukan DPP</p>
                <p class="text-lg font-semibold tabular-nums text-gray-900">{{ $fmt($ringkasan['masukan_dpp']) }}</p>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <p class="text-xs text-gray-500">Masukan PPN</p>
                <p class="text-lg font-semibold tabular-nums text-gray-900">{{ $fmt($ringkasan['masukan_tax']) }}</p>
            </div>
            <div class="rounded-lg border border-blue-100 bg-blue-50 p-3">
                <p class="text-xs text-blue-700">Net PPN (keluaran − masukan)</p>
                <p class="text-lg font-semibold tabular-nums text-blue-900">{{ $fmt($ringkasan['net_ppn']) }}</p>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <p class="text-xs text-gray-500">PPh Final / Tax Paid</p>
                <p class="text-sm tabular-nums text-gray-700">
                    {{ $fmt($ringkasan['pph_final']) }} / {{ $fmt($ringkasan['tax_paid']) }}
                </p>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <button
            type="button"
            class="flex w-full items-center justify-between px-4 py-3 text-left"
            @click="keluaranOpen = !keluaranOpen"
            data-testid="ppn-keluaran-toggle"
        >
            <span class="text-sm font-semibold text-gray-900">Keluaran ({{ $keluaranRows->count() }} baris)</span>
            <svg :class="keluaranOpen ? 'rotate-180' : ''" class="h-4 w-4 text-gray-500 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div x-show="keluaranOpen" x-cloak class="border-t border-gray-100">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" data-testid="ppn-keluaran-table">
                    <thead>
                        <tr class="border-b bg-gray-50 text-left text-xs text-gray-500">
                            <th class="px-3 py-2 font-medium">Tanggal</th>
                            <th class="px-3 py-2 font-medium">Invoice</th>
                            <th class="px-3 py-2 font-medium">Tipe</th>
                            <th class="px-3 py-2 font-medium">Sumber</th>
                            <th class="px-3 py-2 font-medium">Pihak</th>
                            @if($showEntityColumn)
                                <th class="px-3 py-2 font-medium">Entitas</th>
                            @endif
                            <th class="px-3 py-2 text-right font-medium">DPP</th>
                            <th class="px-3 py-2 text-right font-medium">PPN</th>
                            <th class="px-3 py-2 font-medium">Tx</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($keluaranRows as $row)
                            <tr class="border-b">
                                <td class="px-3 py-2 tabular-nums">{{ $row['date'] }}</td>
                                <td class="px-3 py-2">{{ $row['invoice'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['type_label'] }}</td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ $row['source_label'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['party'] }}</td>
                                @if($showEntityColumn)
                                    <td class="px-3 py-2">{{ $row['entity_name'] }}</td>
                                @endif
                                <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($row['dpp']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($row['ppn']) }}</td>
                                <td class="px-3 py-2">
                                    @if(($row['link_type'] ?? 'transaction') === 'faktur')
                                        <a href="{{ route('reports.tax.faktur.show', $row['link_id']) }}" class="text-blue-600 hover:underline">Faktur</a>
                                    @else
                                        <a href="{{ route('transactions.show', $row['link_id']) }}" class="text-blue-600 hover:underline">#{{ $row['link_id'] }}</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $showEntityColumn ? 9 : 8 }}" class="px-3 py-6 text-center text-gray-500">Tidak ada baris keluaran untuk periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <button
            type="button"
            class="flex w-full items-center justify-between px-4 py-3 text-left"
            @click="masukanOpen = !masukanOpen"
            data-testid="ppn-masukan-toggle"
        >
            <span class="text-sm font-semibold text-gray-900">Masukan ({{ $masukanRows->count() }} baris)</span>
            <svg :class="masukanOpen ? 'rotate-180' : ''" class="h-4 w-4 text-gray-500 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div x-show="masukanOpen" x-cloak class="border-t border-gray-100">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" data-testid="ppn-masukan-table">
                    <thead>
                        <tr class="border-b bg-gray-50 text-left text-xs text-gray-500">
                            <th class="px-3 py-2 font-medium">Tanggal</th>
                            <th class="px-3 py-2 font-medium">Invoice</th>
                            <th class="px-3 py-2 font-medium">Tipe</th>
                            <th class="px-3 py-2 font-medium">Sumber</th>
                            <th class="px-3 py-2 font-medium">Pihak</th>
                            @if($showEntityColumn)
                                <th class="px-3 py-2 font-medium">Entitas</th>
                            @endif
                            <th class="px-3 py-2 text-right font-medium">DPP</th>
                            <th class="px-3 py-2 text-right font-medium">PPN</th>
                            <th class="px-3 py-2 font-medium">Tx</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($masukanRows as $row)
                            <tr class="border-b">
                                <td class="px-3 py-2 tabular-nums">{{ $row['date'] }}</td>
                                <td class="px-3 py-2">{{ $row['invoice'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['type_label'] }}</td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ $row['source_label'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['party'] }}</td>
                                @if($showEntityColumn)
                                    <td class="px-3 py-2">{{ $row['entity_name'] }}</td>
                                @endif
                                <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($row['dpp']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($row['ppn']) }}</td>
                                <td class="px-3 py-2">
                                    @if(($row['link_type'] ?? 'transaction') === 'faktur')
                                        <a href="{{ route('reports.tax.faktur.show', $row['link_id']) }}" class="text-blue-600 hover:underline">Faktur</a>
                                    @else
                                        <a href="{{ route('transactions.show', $row['link_id']) }}" class="text-blue-600 hover:underline">#{{ $row['link_id'] }}</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $showEntityColumn ? 9 : 8 }}" class="px-3 py-6 text-center text-gray-500">Tidak ada baris masukan untuk periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

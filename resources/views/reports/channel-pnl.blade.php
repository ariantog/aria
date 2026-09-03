@extends('layouts.app')

@section('title', 'Laporan Channel P&L')

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
$warnings = $report['mapping_warnings'];
$hasWarning = $warnings['fulfillment'] || $warnings['marketplace_ledgers'] || $warnings['toko_ledgers'];
$pendapatanByChannel = collect($report['drilldown']['pendapatan'])->groupBy('channel_key');
$biayaByChannel = collect($report['drilldown']['biaya'])->groupBy('channel_key');
@endphp

<div
    class="flex flex-col gap-4 p-4"
    x-data="{
        openKey: null,
        toggleChannel(key) { this.openKey = this.openKey === key ? null : key },
        isChannelOpen(key) { return this.openKey === key },
    }"
    data-testid="channel-pnl-page"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Laporan Channel P&L</h2>
            <p class="mt-1 text-sm text-gray-500">
                {{ $report['entity_label'] }} —
                {{ $monthNames[$filters['month']] }} {{ $filters['year'] }}
                ({{ $report['period_start'] }} — {{ $report['period_end'] }};
                Cash In/Out bank per marketplace/channel)
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a
                href="{{ route('reports.channel-pnl') }}?{{ $csvQuery }}"
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                data-testid="channel-pnl-export-csv"
            >
                Export CSV
            </a>
            <a
                href="{{ route('reports.channel-pnl') }}?{{ $xlsxQuery }}"
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                data-testid="channel-pnl-export-xlsx"
            >
                Export Excel
            </a>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('reports.channel-pnl') }}" class="flex flex-wrap items-end gap-4">
            <div class="grid w-[180px] gap-1.5">
                <label class="text-sm font-medium" for="month">Bulan</label>
                <select id="month" name="month" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm" data-testid="channel-pnl-month">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected((int) $filters['month'] === $m)>{{ $monthNames[$m] }}</option>
                    @endfor
                </select>
            </div>
            <div class="grid w-[140px] gap-1.5">
                <label class="text-sm font-medium" for="year">Tahun</label>
                <select id="year" name="year" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm" data-testid="channel-pnl-year">
                    @foreach($yearList as $y)
                        <option value="{{ $y }}" @selected((int) $filters['year'] === (int) $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid min-w-[220px] gap-1.5">
                <label class="text-sm font-medium" for="entity">Entitas</label>
                <select id="entity" name="entity" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm" data-testid="channel-pnl-entity">
                    <option value="{{ \App\Services\Reporting\ChannelPnlService::CONSOLIDATED_ENTITY }}" @selected((int) $filters['entity'] === \App\Services\Reporting\ChannelPnlService::CONSOLIDATED_ENTITY)>
                        Konsolidasi (semua entitas aktif)
                    </option>
                    @foreach($entities as $entity)
                        <option value="{{ $entity->id }}" @selected((int) $filters['entity'] === $entity->id)>
                            {{ $entity->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
                <a href="{{ route('reports.channel-pnl') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Clear</a>
            </div>
        </form>
    </div>

    @if($hasWarning)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-testid="channel-pnl-mapping-warning">
            Mapping belum lengkap — isi dulu di
            <a href="{{ route('reports.entities.index') }}" class="font-medium underline">Reporting Entities</a>
            agar perbandingan channel bermakna.
            <ul class="mt-1 list-disc pl-5">
                @if($warnings['fulfillment'])
                    <li>Belum ada warehouse fulfillment (gudang → customer/channel).</li>
                @endif
                @if($warnings['marketplace_ledgers'])
                    <li>Belum ada ledger role <span class="font-medium">marketplace_cost</span>.</li>
                @endif
                @if($warnings['toko_ledgers'])
                    <li>Belum ada ledger role <span class="font-medium">toko_cost</span>.</li>
                @endif
            </ul>
        </div>
    @endif

    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600" data-testid="channel-pnl-notes">
        <p class="font-medium text-gray-700">Cara hitung</p>
        <ul class="mt-1 list-disc pl-5 space-y-0.5">
            @foreach($report['notes'] as $note)
                <li>{{ $note }}</li>
            @endforeach
        </ul>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white p-4 shadow-sm" data-testid="channel-pnl-table">
        <table class="w-full min-w-[800px] text-sm">
            <thead>
                <tr class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                    <th class="py-2 text-left">Channel</th>
                    <th class="py-2 text-left">Gudang</th>
                    <th class="py-2 text-right">Pendapatan</th>
                    <th class="py-2 text-right">Biaya Marketplace</th>
                    <th class="py-2 text-right">Biaya Toko</th>
                    <th class="py-2 text-right">Kontribusi</th>
                    <th class="py-2 text-right">Margin</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($report['rows'] as $row)
                    <tr data-testid="channel-pnl-row-{{ $row['key'] }}">
                        <td class="py-2">
                            <button type="button" class="text-left text-blue-700 hover:underline" @click="toggleChannel(@js($row['key']))">
                                {{ $row['name'] }}
                            </button>
                            @if($row['kind'] === 'unmapped')
                                <p class="text-[11px] text-gray-400">Cash In customer/reseller yang belum di-map ke channel</p>
                            @elseif($row['kind'] === 'unallocated')
                                <p class="text-[11px] text-gray-400">Biaya marketplace/toko yang namanya tidak cocok ke channel/gudang</p>
                            @endif
                        </td>
                        <td class="py-2 text-gray-600">
                            {{ $row['warehouses'] !== [] ? implode(', ', $row['warehouses']) : '—' }}
                        </td>
                        <td class="py-2 text-right tabular-nums">{{ $fmt($row['pendapatan']) }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $fmt($row['marketplace_cost']) }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $fmt($row['toko_cost']) }}</td>
                        <td class="py-2 text-right tabular-nums font-medium">{{ $fmt($row['kontribusi']) }}</td>
                        <td class="py-2 text-right tabular-nums text-gray-600">
                            {{ $row['margin'] === null ? '—' : number_format($row['margin'], 1).'%' }}
                        </td>
                    </tr>
                    <tr x-show="isChannelOpen(@js($row['key']))" x-cloak>
                        <td colspan="7" class="pb-3">
                            <div class="grid gap-3 rounded-lg bg-gray-50 p-3 text-xs text-gray-600 md:grid-cols-2">
                                <div>
                                    <p class="mb-1 font-medium text-gray-700">Pendapatan</p>
                                    <ul class="space-y-1">
                                        @forelse($pendapatanByChannel->get($row['key'], collect()) as $line)
                                            <li class="flex justify-between gap-3">
                                                <span>
                                                    {{ $line['date'] }}
                                                    {{ $line['party'] }}
                                                    @if($line['invoice'])
                                                        <span class="text-gray-400">{{ $line['invoice'] }}</span>
                                                    @endif
                                                    @if($line['entity_name'])
                                                        <span class="text-gray-400">({{ $line['entity_name'] }})</span>
                                                    @endif
                                                </span>
                                                <span class="tabular-nums">{{ $fmt($line['amount']) }}</span>
                                            </li>
                                        @empty
                                            <li>Tidak ada Cash In channel ini.</li>
                                        @endforelse
                                    </ul>
                                </div>
                                <div>
                                    <p class="mb-1 font-medium text-gray-700">Biaya</p>
                                    <ul class="space-y-1">
                                        @forelse($biayaByChannel->get($row['key'], collect()) as $line)
                                            <li class="flex justify-between gap-3">
                                                <span>
                                                    {{ $line['date'] }}
                                                    {{ $line['cost_kind'] === 'toko' ? 'Toko' : 'Marketplace' }}
                                                    — {{ $line['party'] }}
                                                    @if($line['entity_name'])
                                                        <span class="text-gray-400">({{ $line['entity_name'] }})</span>
                                                    @endif
                                                </span>
                                                <span class="tabular-nums">{{ $fmt($line['amount']) }}</span>
                                            </li>
                                        @empty
                                            <li>Tidak ada biaya teralokasi.</li>
                                        @endforelse
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-6 text-center text-gray-500">Tidak ada data channel pada periode ini.</td>
                    </tr>
                @endforelse
            </tbody>
            @if($report['rows'] !== [])
                <tfoot>
                    <tr class="border-t border-gray-200 font-semibold text-gray-900">
                        <td class="py-3" colspan="2">Total</td>
                        <td class="py-3 text-right tabular-nums" data-testid="channel-pnl-pendapatan">{{ $fmt($report['totals']['pendapatan']) }}</td>
                        <td class="py-3 text-right tabular-nums" data-testid="channel-pnl-marketplace">{{ $fmt($report['totals']['marketplace_cost']) }}</td>
                        <td class="py-3 text-right tabular-nums" data-testid="channel-pnl-toko">{{ $fmt($report['totals']['toko_cost']) }}</td>
                        <td class="py-3 text-right tabular-nums" data-testid="channel-pnl-kontribusi">{{ $fmt($report['totals']['kontribusi']) }}</td>
                        <td class="py-3"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Faktur Pajak')

@section('content')
@php
$fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
$monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$queryWithoutOverdue = collect($filters)->except('overdue')->filter()->all();
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Faktur Pajak</h2>
            <p class="text-sm text-gray-500">Riwayat upload & review faktur untuk laporan PPN keluaran / masukan.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('reports.tax.ppn') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Laporan PPN</a>
            @if($canImport)
                <a href="{{ route('reports.tax.faktur.create') }}" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" data-testid="faktur-upload-btn">Upload PDF</a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <form method="GET" action="{{ route('reports.tax.faktur.index') }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div>
            <label for="filter-year" class="mb-1 block text-xs font-medium text-gray-500">Tahun laporan</label>
            <select id="filter-year" name="year" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <option value="">Semua</option>
                @foreach($years as $y)
                    <option value="{{ $y }}" @selected((string) $filters['year'] === (string) $y)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filter-month" class="mb-1 block text-xs font-medium text-gray-500">Bulan</label>
            <select id="filter-month" name="month" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <option value="">Semua</option>
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected((string) $filters['month'] === (string) $m)>{{ $monthNames[$m] }}</option>
                @endfor
            </select>
        </div>
        <div>
            <label for="filter-entity" class="mb-1 block text-xs font-medium text-gray-500">Entitas</label>
            <select id="filter-entity" name="entity" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <option value="">Semua</option>
                @foreach($entities as $entity)
                    <option value="{{ $entity->id }}" @selected((string) $filters['entity'] === (string) $entity->id)>{{ $entity->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filter-direction" class="mb-1 block text-xs font-medium text-gray-500">Arah</label>
            <select id="filter-direction" name="direction" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <option value="">Semua</option>
                <option value="keluaran" @selected($filters['direction'] === 'keluaran')>Keluaran</option>
                <option value="masukan" @selected($filters['direction'] === 'masukan')>Masukan</option>
            </select>
        </div>
        <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">Filter</button>
        <a href="{{ route('reports.tax.faktur.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">Reset</a>
    </form>

    <div class="flex gap-2">
        <a href="{{ route('reports.tax.faktur.index', $queryWithoutOverdue) }}" class="rounded-lg px-3 py-1.5 text-sm {{ !$filters['overdue'] ? 'bg-blue-100 text-blue-800 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Semua</a>
        <a href="{{ route('reports.tax.faktur.index', array_merge($queryWithoutOverdue, ['overdue' => 1])) }}" class="rounded-lg px-3 py-1.5 text-sm {{ $filters['overdue'] ? 'bg-amber-100 text-amber-900 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Terlambat bayar</a>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-sm" data-testid="faktur-import-table">
            <thead>
                <tr class="border-b bg-gray-50 text-left text-xs text-gray-500">
                    <th class="px-3 py-2 font-medium">Faktur</th>
                    <th class="px-3 py-2 font-medium">Tanggal</th>
                    <th class="px-3 py-2 font-medium">Laporan</th>
                    <th class="px-3 py-2 font-medium">Arah</th>
                    <th class="px-3 py-2 font-medium">Lawan</th>
                    <th class="px-3 py-2 font-medium">Entitas</th>
                    <th class="px-3 py-2 text-right font-medium">DPP</th>
                    <th class="px-3 py-2 text-right font-medium">PPN</th>
                    <th class="px-3 py-2 font-medium">Jatuh tempo</th>
                    <th class="px-3 py-2 font-medium">Bayar</th>
                    <th class="px-3 py-2 font-medium">Diimport</th>
                </tr>
            </thead>
            <tbody>
                @forelse($imports as $row)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-3 py-2">
                            <a href="{{ route('reports.tax.faktur.show', $row) }}" class="font-mono text-xs text-blue-700 hover:underline" data-testid="faktur-show-link-{{ $row->id }}">
                                {{ $row->faktur_number }}
                            </a>
                        </td>
                        <td class="px-3 py-2 tabular-nums">{{ $row->faktur_date?->format('Y-m-d') }}</td>
                        <td class="px-3 py-2 tabular-nums text-xs">{{ $monthNames[$row->report_month] ?? $row->report_month }} {{ $row->report_year }}</td>
                        <td class="px-3 py-2">{{ $row->direction === 'masukan' ? 'Masukan' : 'Keluaran' }}</td>
                        <td class="px-3 py-2">{{ $row->counterparty?->name }}</td>
                        <td class="px-3 py-2">{{ $row->reportingEntity?->name }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($row->dpp) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($row->ppn) }}</td>
                        <td class="px-3 py-2">
                            @if($row->expected_payment_date)
                                <span class="tabular-nums">{{ $row->expected_payment_date->format('Y-m-d') }}</span>
                                @if($row->isPaymentOverdue())
                                    <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-900">Terlambat</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 py-2 text-xs">
                            @if($row->payment_received_date)
                                <span class="text-green-700">{{ $fmt($row->payment_received_amount) }}</span>
                                @if($row->payment_variance && abs((float)$row->payment_variance) > 0.01)
                                    <span class="text-gray-500">(Δ {{ $fmt($row->payment_variance) }})</span>
                                @endif
                            @else
                                <span class="text-gray-400">Belum</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-500">
                            <span class="tabular-nums">{{ $row->created_at?->format('Y-m-d H:i') }}</span>
                            @if($row->user)
                                <span class="block">{{ $row->user->username }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-3 py-8 text-center text-gray-500">Belum ada faktur diimport.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $imports->links() }}
</div>
@endsection

@extends('layouts.app')

@section('title', 'Faktur Pajak')

@section('content')
@php
$fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Faktur Pajak</h2>
            <p class="text-sm text-gray-500">Imported e-faktur for PPN keluaran / masukan reporting.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('reports.tax.ppn') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Laporan PPN</a>
            <a href="{{ route('reports.tax.faktur.create') }}" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" data-testid="faktur-upload-btn">Upload PDF</a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="flex gap-2">
        <a href="{{ route('reports.tax.faktur.index') }}" class="rounded-lg px-3 py-1.5 text-sm {{ !$filterOverdue ? 'bg-blue-100 text-blue-800 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Semua</a>
        <a href="{{ route('reports.tax.faktur.index', ['overdue' => 1]) }}" class="rounded-lg px-3 py-1.5 text-sm {{ $filterOverdue ? 'bg-amber-100 text-amber-900 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Terlambat bayar</a>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-sm" data-testid="faktur-import-table">
            <thead>
                <tr class="border-b bg-gray-50 text-left text-xs text-gray-500">
                    <th class="px-3 py-2 font-medium">Faktur</th>
                    <th class="px-3 py-2 font-medium">Tanggal</th>
                    <th class="px-3 py-2 font-medium">Arah</th>
                    <th class="px-3 py-2 font-medium">Lawan</th>
                    <th class="px-3 py-2 font-medium">Entitas</th>
                    <th class="px-3 py-2 text-right font-medium">DPP</th>
                    <th class="px-3 py-2 text-right font-medium">PPN</th>
                    <th class="px-3 py-2 font-medium">Jatuh tempo</th>
                    <th class="px-3 py-2 font-medium">Bayar</th>
                </tr>
            </thead>
            <tbody>
                @forelse($imports as $row)
                    <tr class="border-b">
                        <td class="px-3 py-2 font-mono text-xs">{{ $row->faktur_number }}</td>
                        <td class="px-3 py-2 tabular-nums">{{ $row->faktur_date?->format('Y-m-d') }}</td>
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
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-3 py-8 text-center text-gray-500">Belum ada faktur diimport.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $imports->links() }}
</div>
@endsection

@extends('layouts.app')

@section('title', 'Faktur '.$import->faktur_number)

@section('content')
@php
$fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
$monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$lineItems = $import->line_items ?? [];
$gross = $import->fakturGross();
@endphp

<div class="flex flex-col gap-4 p-4">
    <div>
        <a href="{{ route('reports.tax.faktur.index') }}" class="text-sm text-blue-600 hover:underline">← Kembali ke daftar faktur</a>
        <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Faktur {{ $import->faktur_number }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $import->directionLabel() }} — {{ $monthNames[$import->report_month] ?? $import->report_month }} {{ $import->report_year }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($hasPdf)
                    <a href="{{ route('reports.tax.faktur.pdf', $import) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" data-testid="faktur-download-pdf">
                        Download PDF
                    </a>
                @endif
                @if($canImport)
                    <a href="{{ route('reports.tax.faktur.create') }}" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">Upload baru</a>
                @endif
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm text-sm">
            <h3 class="mb-3 font-semibold text-gray-900">Data faktur</h3>
            <dl class="space-y-2">
                <div><dt class="text-gray-500">Tanggal faktur</dt><dd class="tabular-nums">{{ $import->faktur_date?->format('Y-m-d') }} @if($import->faktur_date_place)<span class="text-xs text-gray-400">({{ $import->faktur_date_place }})</span>@endif</dd></div>
                <div><dt class="text-gray-500">Penjual (PKP)</dt><dd>{{ $import->seller_name }} <span class="text-xs text-gray-400">{{ $import->seller_npwp }}</span></dd></div>
                <div><dt class="text-gray-500">Pembeli</dt><dd>{{ $import->buyer_name }} <span class="text-xs text-gray-400">{{ $import->buyer_npwp }}</span></dd></div>
                <div><dt class="text-gray-500">Reporting entity</dt><dd>{{ $import->reportingEntity?->name }}</dd></div>
                <div><dt class="text-gray-500">Lawan transaksi</dt><dd>{{ $import->counterparty?->name }}</dd></div>
                <div><dt class="text-gray-500">DPP / PPN / PPnBM</dt><dd class="tabular-nums">{{ $fmt($import->dpp) }} / {{ $fmt($import->ppn) }} / {{ $fmt($import->ppnbm) }}</dd></div>
                <div><dt class="text-gray-500">Total faktur (DPP+PPN)</dt><dd class="tabular-nums font-medium">{{ $fmt($gross) }}</dd></div>
                @if($import->signatory_name)
                    <div><dt class="text-gray-500">Penandatangan</dt><dd>{{ $import->signatory_name }}</dd></div>
                @endif
                @if($import->notes)
                    <div><dt class="text-gray-500">Catatan</dt><dd>{{ $import->notes }}</dd></div>
                @endif
            </dl>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm text-sm">
            <h3 class="mb-3 font-semibold text-gray-900">Pembayaran & import</h3>
            <dl class="space-y-2">
                <div>
                    <dt class="text-gray-500">Jatuh tempo (perkiraan)</dt>
                    <dd>
                        @if($import->expected_payment_date)
                            <span class="tabular-nums">{{ $import->expected_payment_date->format('Y-m-d') }}</span>
                            @if($import->isPaymentOverdue())
                                <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-900">Terlambat</span>
                            @endif
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Pembayaran diterima</dt>
                    <dd>
                        @if($import->payment_received_date)
                            <span class="tabular-nums text-green-700">{{ $fmt($import->payment_received_amount) }}</span>
                            <span class="text-gray-500"> pada {{ $import->payment_received_date->format('Y-m-d') }}</span>
                        @else
                            <span class="text-gray-400">Belum dibayar</span>
                        @endif
                    </dd>
                </div>
                @if($import->payment_variance && abs((float) $import->payment_variance) > 0.01)
                    <div>
                        <dt class="text-gray-500">Selisih vs faktur</dt>
                        <dd class="tabular-nums">{{ $fmt($import->payment_variance) }}
                            @if($import->varianceExpenseAccount)
                                <span class="text-xs text-gray-500">→ {{ $import->varianceExpenseAccount->name }}</span>
                            @endif
                        </dd>
                    </div>
                @endif
                <div>
                    <dt class="text-gray-500">Diimport</dt>
                    <dd>
                        <span class="tabular-nums">{{ $import->created_at?->format('Y-m-d H:i') }}</span>
                        @if($import->user)
                            <span class="text-gray-500"> oleh {{ $import->user->username }}</span>
                        @endif
                    </dd>
                </div>
                <div><dt class="text-gray-500">Format sumber</dt><dd class="text-xs text-gray-600">{{ $import->source_format ?? '—' }}</dd></div>
            </dl>
        </div>
    </div>

    @if(count($lineItems) > 0)
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-4 py-3">
                <h3 class="text-sm font-semibold text-gray-900">Baris item ({{ count($lineItems) }})</h3>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-gray-50 text-left text-xs text-gray-500">
                        <th class="px-3 py-2 font-medium">#</th>
                        <th class="px-3 py-2 font-medium">Nama</th>
                        <th class="px-3 py-2 text-right font-medium">Qty</th>
                        <th class="px-3 py-2 text-right font-medium">Harga</th>
                        <th class="px-3 py-2 text-right font-medium">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lineItems as $item)
                        <tr class="border-b">
                            <td class="px-3 py-2 tabular-nums">{{ $item['line_no'] ?? '' }}</td>
                            <td class="px-3 py-2">{{ $item['name'] ?? '' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ isset($item['quantity']) ? $fmt($item['quantity']) : '' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ isset($item['unit_price']) ? $fmt($item['unit_price']) : '' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ isset($item['total']) ? $fmt($item['total']) : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection

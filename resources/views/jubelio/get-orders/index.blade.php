@extends('layouts.app')

@section('title', 'Jubelio Get Orders')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Jubelio', 'href' => route('jubelio.index')],
    ['title' => 'Get Orders', 'href' => route('jubelio.get-orders.index')],
];
@endphp

<div class="flex flex-col gap-6 p-4">
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="flex items-center gap-2 text-2xl font-bold">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M4 8V7a3 3 0 013-3h10a3 3 0 013 3v1M12 12v9m0-9V3"/></svg>
                Jubelio Get Orders
            </h1>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">
                Tarik order dari API Jubelio per rentang tanggal untuk menemukan invoice yang webhook-nya tidak pernah sampai ke Aria.
                Cron mengambil beberapa halaman sekaligus per menit; gunakan <code class="text-xs">php artisan jubelio:get-orders --sync</code> untuk backfill cepat.
            </p>
        </div>
    </div>

    @if(! $import)
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-lg font-semibold">Mulai Import</h2>
        <form method="POST" action="{{ route('jubelio.get-orders.store') }}" class="flex flex-wrap items-end gap-4">
            @csrf
            <div>
                <label for="from" class="mb-1 block text-sm font-medium text-gray-700">Tanggal mulai</label>
                <input type="date" name="from" id="from" required value="{{ old('from', now()->subDay()->toDateString()) }}"
                       class="rounded-md border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="to" class="mb-1 block text-sm font-medium text-gray-700">Jumlah hari</label>
                <input type="number" name="to" id="to" min="0" max="366" required value="{{ old('to', 0) }}"
                       class="w-28 rounded-md border border-gray-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-gray-400">0 = hari yang sama saja</p>
            </div>
            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                Mulai Import
            </button>
        </form>
    </div>
    @else
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Import #{{ $import->id }}</h2>
                <p class="text-sm text-gray-500">
                    {{ \Carbon\Carbon::parse($import->from)->translatedFormat('d M Y') }}
                    + {{ $import->to }} hari
                    @if($dateFrom && $dateTo)
                    · API: {{ $dateFrom }} → {{ $dateTo }}
                    @endif
                </p>
            </div>
            <div class="text-right text-sm">
                @if($import->isRunning())
                <span class="inline-flex rounded bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">Berjalan (step {{ $import->step }})</span>
                @else
                <span class="inline-flex rounded bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Selesai</span>
                @endif
                <p class="mt-1 font-mono text-xs text-gray-500">Halaman {{ $import->count }}/{{ max($import->total, 1) }}</p>
            </div>
        </div>

        @if($import->total > 0)
        <div class="mb-6">
            <div class="mb-1 flex justify-between text-xs text-gray-500">
                <span>Progress fetch API</span>
                <span>{{ $progress }}%</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                <div class="h-full rounded-full bg-blue-600 transition-all" style="width: {{ $progress }}%"></div>
            </div>
        </div>
        @endif

        <div class="flex flex-wrap gap-2">
            @if($import->status === 1 && ($details->total() ?? 0) > 0)
            <form method="POST" action="{{ route('jubelio.get-orders.import') }}">
                @csrf
                <button type="submit" class="rounded-lg bg-green-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-800"
                        data-testid="jubelio-get-orders-import">
                    Import ke Jubelio Orders ({{ $details->total() }})
                </button>
            </form>
            @endif
            <form method="POST" action="{{ route('jubelio.get-orders.check-transactions') }}">
                @csrf
                <button type="submit" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Cek Transaksi
                </button>
            </form>
            <form method="POST" action="{{ route('jubelio.get-orders.check-existing') }}">
                @csrf
                <button type="submit" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Cek Jubelio/Transaksi
                </button>
            </form>
            <form method="POST" action="{{ route('jubelio.get-orders.reset') }}" onsubmit="return confirm('Reset import ini?')">
                @csrf
                <button type="submit" class="rounded-lg border border-red-300 bg-red-50 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-100">
                    Reset
                </button>
            </form>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="font-semibold">Order belum ada di Aria ({{ $details->total() ?? 0 }})</h2>
            <p class="text-xs text-gray-500">Setelah import selesai, pindahkan ke Jubelio Orders lalu proses seperti webhook biasa.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3">Invoice</th>
                        <th class="px-6 py-3">Store</th>
                        <th class="px-6 py-3">Location</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3 text-center">Canceled</th>
                        <th class="px-6 py-3 text-center">Di Aria?</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($details as $detail)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 font-mono text-xs">{{ $detail->invoice }}</td>
                        <td class="px-6 py-3">{{ $detail->store_name ?: '—' }}</td>
                        <td class="px-6 py-3">{{ $detail->location_name ?: '—' }}</td>
                        <td class="px-6 py-3"><span class="rounded bg-gray-100 px-2 py-0.5 text-xs">{{ $detail->order_status }}</span></td>
                        <td class="px-6 py-3 text-center">{{ $detail->is_canceled ?: '—' }}</td>
                        <td class="px-6 py-3 text-center">
                            @if(($existingInvoices[$detail->invoice] ?? false))
                            <span class="text-green-600">Ya</span>
                            @else
                            <span class="font-medium text-red-600">Tidak</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-sm italic text-gray-400">
                            @if($import->isRunning())
                            Menunggu cron <code class="text-xs">jubelio:get-orders</code> mengambil data...
                            @else
                            Tidak ada order missing — semua sudah ada di Aria atau sudah diimport.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($details instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $details->hasPages())
        @include('partials.pagination', ['paginator' => $details, 'label' => 'orders'])
        @endif
    </div>
    @endif
</div>
@endsection

@extends('layouts.app')

@section('title', 'Jubelio Cek Order')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Jubelio', 'href' => route('jubelio.index')],
    ['title' => 'Cek Order', 'href' => route('jubelio.order.cek')],
];
$inspection = $lookup['inspection'] ?? null;
@endphp

<div class="flex flex-col gap-6 p-4">
    <div>
        <h1 class="text-2xl font-bold">Jubelio Cek Order</h1>
        <p class="mt-1 text-sm text-gray-500">
            Cari order di API Jubelio berdasarkan Order ID, lalu antrikan ke
            <a href="{{ route('jubelio.index') }}" class="text-blue-700 hover:underline">Jubelio Orders</a>
            jika belum ada di Aria.
        </p>
    </div>

    @if(session('success'))
    <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="max-w-2xl rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-900">Cari Order</h2>
        </div>
        <div class="p-6">
            <form method="GET" action="{{ route('jubelio.order.cek') }}" class="flex flex-col gap-4 sm:flex-row sm:items-end">
                <div class="flex-1 space-y-2">
                    <label for="order_id" class="block text-sm font-medium text-gray-700">Jubelio Order ID</label>
                    <input type="text" name="order_id" id="order_id" required
                           value="{{ $orderId }}"
                           placeholder="contoh: 12345678"
                           class="h-10 w-full rounded-lg border border-gray-300 px-3 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                           data-testid="jubelio-cek-order-id">
                </div>
                <button type="submit"
                        class="h-10 rounded-lg bg-blue-700 px-4 text-sm font-medium text-white hover:bg-blue-800"
                        data-testid="jubelio-cek-submit">
                    Cek
                </button>
            </form>
        </div>
    </div>

    @if($lookupError)
    <div class="max-w-2xl rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $lookupError }}</div>
    @endif

    @if($inspection)
    <div class="max-w-3xl rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="font-semibold">Hasil Pengecekan</h2>
        </div>
        <div class="space-y-6 p-6">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium uppercase text-gray-400">Order ID</dt>
                    <dd class="mt-1 font-mono text-sm">{{ $inspection['order_id'] ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase text-gray-400">Invoice</dt>
                    <dd class="mt-1 font-mono text-sm">{{ $inspection['invoice'] ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase text-gray-400">Status</dt>
                    <dd class="mt-1"><span class="rounded bg-gray-100 px-2 py-0.5 text-xs">{{ $inspection['status'] ?: '—' }}</span></dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase text-gray-400">Tanggal</dt>
                    <dd class="mt-1 text-sm">{{ $inspection['transaction_date'] ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase text-gray-400">Store</dt>
                    <dd class="mt-1 text-sm">{{ $inspection['store_name'] ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase text-gray-400">Gudang</dt>
                    <dd class="mt-1 text-sm">{{ $inspection['location_name'] ?: '—' }}</dd>
                </div>
            </dl>

            <div class="space-y-2 rounded-lg bg-gray-50 p-4 text-sm">
                <div class="flex items-center justify-between gap-4">
                    <span class="text-gray-600">Sudah di transaksi Aria?</span>
                    @if($inspection['in_transaction'])
                    <span class="font-medium text-red-600">Ya</span>
                    @else
                    <span class="font-medium text-green-600">Tidak</span>
                    @endif
                </div>
                <div class="flex items-center justify-between gap-4">
                    <span class="text-gray-600">Sudah di antrian Jubelio Orders?</span>
                    @if($inspection['in_queue'])
                    <span class="font-medium text-red-600">Ya</span>
                    @else
                    <span class="font-medium text-green-600">Tidak</span>
                    @endif
                </div>
                @if(! $inspection['eligible'])
                <p class="border-t border-gray-200 pt-2 text-amber-700">
                    Status order tidak memenuhi syarat antrian (harus SHIPPED, COMPLETED, atau RETURNED dan tidak dibatalkan).
                </p>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4">
                @if($inspection['can_queue'])
                <form method="POST" action="{{ route('jubelio.order.cek.queue') }}"
                      onsubmit="return confirm('Antrikan order ini ke Jubelio Orders?')">
                    @csrf
                    <input type="hidden" name="order_id" value="{{ $orderId }}">
                    <button type="submit"
                            class="rounded-lg bg-green-700 px-4 py-2 text-sm font-medium text-white hover:bg-green-800"
                            data-testid="jubelio-cek-queue">
                        Antrikan ke Jubelio Orders
                    </button>
                </form>
                @else
                @php
                    $disabledReason = match (true) {
                        $inspection['in_transaction'] => 'Invoice sudah ada di transaksi',
                        $inspection['in_queue'] => 'Order sudah ada di antrian',
                        ! $inspection['eligible'] => 'Status tidak memenuhi syarat',
                        default => 'Tidak dapat diantri',
                    };
                @endphp
                <button type="button" disabled
                        class="cursor-not-allowed rounded-lg bg-gray-200 px-4 py-2 text-sm font-medium text-gray-500"
                        data-testid="jubelio-cek-queue-disabled"
                        title="{{ $disabledReason }}">
                    Antrikan ke Jubelio Orders
                </button>
                @if($inspection['in_transaction'])
                <p class="text-sm text-gray-500">Invoice sudah ada di tabel transaksi.</p>
                @elseif($inspection['in_queue'] && $inspection['existing_order'])
                <a href="{{ route('jubelio.show', $inspection['existing_order']) }}" class="text-sm text-blue-700 hover:underline">
                    Lihat di Jubelio Orders →
                </a>
                @endif
                @endif
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

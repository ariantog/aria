@extends('layouts.app')

@section('title', 'Jubelio Order #' . $order->invoice)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Jubelio Orders', 'href' => route('jubelio.index')],
    ['title' => 'Order #' . $order->invoice, 'href' => route('jubelio.show', $order->id)],
];
$statusConfig = [
    0 => ['label' => 'Pending', 'cls' => 'bg-gray-100 text-gray-700'],
    1 => ['label' => 'Failed',  'cls' => 'bg-red-600 text-white'],
    2 => ['label' => 'Processed', 'cls' => 'bg-blue-600 text-white'],
];
$sc = $statusConfig[$order->status] ?? ['label' => 'Unknown', 'cls' => 'border border-gray-200 bg-white text-gray-600'];
@endphp

<div class="flex flex-col gap-6 p-6">
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div class="flex items-center gap-4">
            <a href="{{ route('jubelio.index') }}" class="flex h-9 w-9 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <h1 class="flex items-center gap-2 text-2xl font-bold">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 7l-1 11a1 1 0 01-1 1H8a1 1 0 01-1-1L6 7m3-3h6m-9 3h12"/></svg>
                Order #{{ $order->invoice }}
            </h1>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if($order->canProcessManually())
            <form method="POST" action="{{ route('jubelio.process', $order) }}" onsubmit="return confirm('Proses order ini menjadi transaksi Aria?')">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800"
                        data-testid="jubelio-process-order">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Buat Transaksi Manual
                </button>
            </form>
            @endif

            @if($order->canMarkSolved())
            <form method="POST" action="{{ route('jubelio.solve', $order) }}" onsubmit="return confirm('Tandai order ini selesai tanpa membuat transaksi?')">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg border border-yellow-400 bg-yellow-50 px-4 py-2 text-sm font-medium text-yellow-800 hover:bg-yellow-100"
                        data-testid="jubelio-mark-solved">
                    Tandai Selesai
                </button>
            </form>
            @endif

            <a href="{{ $transactionsUrl }}"
               class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                Cek duplikat di Transaksi
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-1">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold">Order Details</h2>
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm text-gray-400">Jubelio Order ID</dt>
                        <dd class="text-sm font-medium">{{ $order->jubelio_order_id }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-400">Type</dt>
                        <dd class="text-sm font-medium"><span class="inline-flex rounded border border-gray-200 bg-white px-2 py-0.5 text-xs">{{ $order->type }}</span></dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-400">Order Status</dt>
                        <dd class="text-sm font-medium"><span class="inline-flex rounded bg-gray-100 px-2 py-0.5 text-xs">{{ $order->order_status }}</span></dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-400">Sync Status</dt>
                        <dd class="text-sm font-medium">
                            @include('jubelio.partials.sync-status-badge', ['status' => $order->status, 'errorType' => $order->error_type, 'executeBy' => $order->user->name ?? null])
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-400">Run Count</dt>
                        <dd class="text-sm font-medium">{{ $order->run_count }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-400">Executed By</dt>
                        <dd class="text-sm font-medium">{{ $order->user->name ?? 'System' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-400">Received</dt>
                        <dd class="text-sm font-medium">{{ \Carbon\Carbon::parse($order->created_at)->translatedFormat('d/m/Y H:i') }}</dd>
                    </div>
                    @if($summary['transaction_date'])
                    <div>
                        <dt class="text-sm text-gray-400">Transaction Date</dt>
                        <dd class="text-sm font-medium">{{ \Carbon\Carbon::parse($summary['transaction_date'])->translatedFormat('d/m/Y H:i') }}</dd>
                    </div>
                    @endif
                </dl>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold">Summary</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-400">Store</dt>
                        <dd class="text-right font-medium">{{ $summary['store_name'] ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-400">Location</dt>
                        <dd class="text-right font-medium">{{ $summary['location_name'] ?: '—' }}</dd>
                    </div>
                    @if($parties['warehouse'])
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-400">Aria Gudang</dt>
                        <dd class="text-right font-medium">
                            <a href="{{ $parties['warehouse']['url'] }}" class="text-blue-700 hover:underline">{{ $parties['warehouse']['name'] }}</a>
                        </dd>
                    </div>
                    @endif
                    @if($parties['customer'] || $summary['customer_name'])
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-400">Customer</dt>
                        <dd class="text-right font-medium">
                            @if($parties['customer'])
                            <a href="{{ $parties['customer']['url'] }}" class="text-blue-700 hover:underline">{{ $parties['customer']['name'] }}</a>
                            @elseif($summary['customer_name'])
                            {{ $summary['customer_name'] }}
                            @else
                            —
                            @endif
                        </dd>
                    </div>
                    @endif
                    @if($summary['payment_method'])
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-400">Payment</dt>
                        <dd class="text-right font-medium">{{ $summary['payment_method'] }}</dd>
                    </div>
                    @endif
                    <div class="flex justify-between gap-4 border-t border-gray-100 pt-3">
                        <dt class="text-gray-400">Subtotal</dt>
                        <dd class="font-mono font-medium">{{ $summary['sub_total'] !== null ? format_amount($summary['sub_total']) : '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="font-semibold text-gray-700">Grand Total</dt>
                        <dd class="font-mono font-bold">{{ $summary['real_total'] !== null ? format_amount($summary['real_total']) : '—' }}</dd>
                    </div>
                </dl>
            </div>

            @if($order->trx)
            <div class="rounded-xl border border-blue-500/30 bg-blue-50 p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold text-blue-700">Linked Transaction</h2>
                <p class="mb-4 text-sm text-blue-600/70">Order ini sudah terhubung ke transaksi internal.</p>
                <a href="{{ route('transactions.show', $order->trx->id) }}"
                   class="flex w-full items-center justify-center gap-2 rounded-lg border border-blue-300 bg-white px-4 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50">
                    View Transaction
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
            </div>
            @endif
        </div>

        <div class="space-y-6 lg:col-span-2">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold">Items ({{ count($items) }})</h2>
                @if(count($items) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-100 text-xs font-semibold uppercase text-gray-500">
                            <tr>
                                <th class="px-3 py-2">SKU</th>
                                @if($parties['warehouse'])
                                <th class="px-3 py-2 text-right">Stok Aria</th>
                                @endif
                                <th class="px-3 py-2 text-right">Qty</th>
                                <th class="px-3 py-2 text-right">Price</th>
                                <th class="px-3 py-2 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($items as $item)
                            @php $lineTotal = (float) $item['quantity'] * (float) ($item['price'] ?? 0); @endphp
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs">
                                    @if($item['item_url'])
                                    <a href="{{ $item['item_url'] }}" class="text-blue-700 hover:underline">{{ $item['item_code'] }}</a>
                                    @if($item['item_name'])
                                    <div class="mt-0.5 font-sans text-gray-500">{{ $item['item_name'] }}</div>
                                    @endif
                                    @else
                                    {{ $item['item_code'] }}
                                    @endif
                                </td>
                                @if($parties['warehouse'])
                                <td class="px-3 py-2 text-right font-mono text-xs {{ isset($item['aria_stock']) && (float) $item['quantity'] > (float) $item['aria_stock'] ? 'text-red-600 font-semibold' : '' }}">
                                    {{ $item['aria_stock'] !== null ? format_amount((float) $item['aria_stock'], 0) : '—' }}
                                </td>
                                @endif
                                <td class="px-3 py-2 text-right font-mono text-xs">{{ format_amount((float) $item['quantity'], 0) }}</td>
                                <td class="px-3 py-2 text-right font-mono text-xs">{{ $item['price'] !== null ? format_amount($item['price']) : '—' }}</td>
                                <td class="px-3 py-2 text-right font-mono text-xs">{{ $item['price'] !== null ? format_amount($lineTotal) : '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-sm text-gray-400 italic">Tidak ada item di payload.</p>
                @endif
            </div>

            @if($order->error)
            <div class="rounded-xl border border-red-900/30 bg-red-50 p-6 shadow-sm">
                <h2 class="mb-2 text-lg font-semibold text-red-600">Error Details</h2>
                @if($order->status === 1)
                <p class="mb-4 text-sm text-red-700">
                    Biasanya terjadi karena stok di Aria tidak cukup di gudang yang dipetakan
                    @if($parties['warehouse'])
                    (<a href="{{ $parties['warehouse']['url'] }}" class="font-medium underline">{{ $parties['warehouse']['name'] }}</a>)
                    @endif
                    — Jubelio masih punya stok, tapi gudang Aria sudah 0 atau kurang.
                    Perbaiki stok di Aria terlebih dahulu, lalu klik <strong>Buat Transaksi Manual</strong> di atas.
                </p>
                @endif
                <div class="rounded-lg border border-red-900/30 bg-red-100 p-4 font-mono text-xs text-red-700">
                    <pre class="whitespace-pre-wrap">{{ $order->error }}</pre>
                </div>
            </div>
            @endif

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm"
                 x-data="{
                    open: false,
                    loading: false,
                    loaded: false,
                    raw: null,
                    error: null,
                    async loadPayload() {
                        if (this.loaded || this.loading) {
                            this.open = !this.open;
                            return;
                        }
                        this.loading = true;
                        this.open = true;
                        this.error = null;
                        try {
                            const res = await fetch(@js(route('jubelio.payload', $order->id)), {
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            });
                            if (!res.ok) throw new Error('HTTP ' + res.status);
                            const data = await res.json();
                            this.raw = JSON.stringify(data.payload, null, 2);
                            this.loaded = true;
                        } catch (e) {
                            this.error = 'Gagal memuat payload JSON.';
                        } finally {
                            this.loading = false;
                        }
                    }
                 }">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-lg font-semibold">Raw Payload</h2>
                    <button type="button"
                            @click="loadPayload()"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            data-testid="jubelio-load-payload">
                        <span x-show="!open">Tampilkan JSON</span>
                        <span x-show="open" x-cloak>Sembunyikan JSON</span>
                    </button>
                </div>
                <p class="mt-1 text-xs text-gray-500">JSON dimuat on-demand agar halaman tidak berat.</p>

                <div x-show="open" x-cloak class="mt-4">
                    <template x-if="loading">
                        <p class="text-sm text-gray-500">Memuat payload...</p>
                    </template>
                    <template x-if="error">
                        <p class="text-sm text-red-600" x-text="error"></p>
                    </template>
                    <template x-if="loaded && raw">
                        <div class="max-h-[32rem] overflow-auto rounded-lg border border-gray-800 bg-gray-900 p-4 font-mono text-xs text-green-300">
                            <pre x-text="raw"></pre>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

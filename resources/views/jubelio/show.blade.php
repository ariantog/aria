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
    1 => ['label' => 'Success', 'cls' => 'bg-blue-600 text-white'],
    2 => ['label' => 'Failed',  'cls' => 'bg-red-600 text-white'],
];
$sc = $statusConfig[$order->status] ?? ['label' => 'Unknown', 'cls' => 'border border-gray-200 bg-white text-gray-600'];
@endphp

<div class="flex flex-col gap-6 p-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('jubelio.index') }}" class="flex h-9 w-9 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <h1 class="flex items-center gap-2 text-2xl font-bold">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 7l-1 11a1 1 0 01-1 1H8a1 1 0 01-1-1L6 7m3-3h6m-9 3h12"/></svg>
                Order #{{ $order->invoice }}
            </h1>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <div class="space-y-6 md:col-span-1">
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
                        <dd class="text-sm font-medium"><span class="inline-flex rounded px-2 py-0.5 text-xs {{ $sc['cls'] }}">{{ $sc['label'] }}</span></dd>
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
                        <dt class="text-sm text-gray-400">Date</dt>
                        <dd class="text-sm font-medium">{{ \Carbon\Carbon::parse($order->created_at)->translatedFormat('d/m/Y H:i') }}</dd>
                    </div>
                </dl>
            </div>

            @if($order->trx)
            <div class="rounded-xl border border-blue-500/30 bg-blue-50 p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold text-blue-700">Linked Transaction</h2>
                <p class="mb-4 text-sm text-blue-600/70">This order is linked to an internal transaction.</p>
                <a href="{{ url('/transactions/' . $order->trx->id) }}"
                   class="flex w-full items-center justify-center gap-2 rounded-lg border border-blue-300 bg-white px-4 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50">
                    View Transaction
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
            </div>
            @endif
        </div>

        <div class="space-y-6 md:col-span-2">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold">Payload</h2>
                <div class="overflow-x-auto rounded-lg border border-gray-800 bg-gray-900 p-4 font-mono text-xs text-green-300">
                    <pre>{{ json_encode(json_decode($order->payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: $order->payload }}</pre>
                </div>
            </div>

            @if($order->error)
            <div class="rounded-xl border border-red-900/30 bg-red-50 p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold text-red-600">Error Details</h2>
                <div class="rounded-lg border border-red-900/30 bg-red-100 p-4 font-mono text-xs text-red-700">
                    <pre class="whitespace-pre-wrap">{{ $order->error }}</pre>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', $channelLabel.' '.$detail['order_ref'])

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Omnichannel', 'href' => route('omnichannel.orders.index', ['view' => 'orders'])],
    ['title' => $detail['order_ref'], 'href' => url()->current()],
];
@endphp

<div class="flex flex-col gap-4 p-2 sm:gap-6 sm:p-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $channelLabel }} {{ $detail['order_ref'] }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Riwayat webhook dan ringkasan order (read-only).</p>
        </div>
        <a href="{{ route('omnichannel.orders.index', ['view' => 'orders', 'channel' => $storageChannel === 'tiktok' && ($detail['commerce_platform'] ?? '') === 'TOKOPEDIA' ? 'tokopedia' : $storageChannel]) }}"
           class="text-sm font-medium text-blue-700 hover:underline">← Kembali ke Current orders</a>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800/50">
        @include('omnichannel.partials.order-detail-panel', [
            'detail' => $detail,
            'statusPresenter' => $statusPresenter,
        ])
    </div>
</div>
@endsection
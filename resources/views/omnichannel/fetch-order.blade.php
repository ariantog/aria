@extends('layouts.app')

@section('title', 'Omnichannel — Tarik order (API)')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Omnichannel', 'href' => route('omnichannel.index')],
    ['title' => 'Tarik order', 'href' => route('omnichannel.fetch-order.index')],
];
$shopRequired = $shopOptions->count() !== 1;
@endphp

<div class="flex flex-col gap-4 p-2 sm:gap-6 sm:p-4" data-testid="omnichannel-fetch-order-page">
    <div class="max-w-2xl">
        <h1 class="text-2xl font-bold text-gray-900">Tarik order dari marketplace API</h1>
        <p class="mt-1 text-sm text-gray-600">
            Maintainer: ambil satu order / retur terkait lewat API channel, lalu simpan seperti webhook masuk
            (<code class="rounded bg-gray-100 px-1 text-xs">marketplace_webhook_events</code>,
            <code class="rounded bg-gray-100 px-1 text-xs">marketplace_channel_orders</code>).
            Tokopedia memakai API TikTok Shop (pilih channel TikTok/Tokopedia).
        </p>
        <p class="mt-2 text-xs text-gray-500">
            <a href="{{ route('omnichannel.orders.index', ['view' => 'orders']) }}" class="text-blue-700 hover:underline">Current orders</a>
            ·
            <a href="{{ route('omnichannel.returns.index') }}" class="text-blue-700 hover:underline">Marketplace returns</a>
        </p>
    </div>

    @if(! $unifiedStorage)
    <div class="max-w-2xl rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-testid="omnichannel-fetch-order-unified-warn">
        <code>MARKETPLACE_WEBHOOK_UNIFIED_STORAGE=false</code> — baris tidak masuk tabel unified. Aktifkan env untuk lihat di Current orders / Webhooks.
    </div>
    @endif

    @if(session('error'))
    <div class="max-w-2xl rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <form method="post" action="{{ route('omnichannel.fetch-order.store') }}" class="max-w-2xl space-y-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm" data-testid="omnichannel-fetch-order-form">
        @csrf

        <div>
            <label for="fetch-channel" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Channel</label>
            <select id="fetch-channel" name="channel" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm" onchange="window.location='{{ route('omnichannel.fetch-order.index') }}?channel='+this.value;">
                <option value="shopee" @selected(old('channel', $selectedChannel) === 'shopee')>Shopee</option>
                <option value="tiktok" @selected(old('channel', $selectedChannel) === 'tiktok')>TikTok Shop</option>
                <option value="tokopedia" @selected(old('channel', $selectedChannel) === 'tokopedia')>Tokopedia (via TikTok API)</option>
            </select>
            @error('channel')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="fetch-order-ref" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Order ref</label>
            <input type="text" id="fetch-order-ref" name="order_ref" value="{{ old('order_ref') }}" required maxlength="128"
                class="mt-1 w-full rounded-md border-gray-300 font-mono text-sm shadow-sm"
                placeholder="{{ in_array(old('channel', $selectedChannel), ['tiktok', 'tokopedia'], true) ? 'order_id TikTok' : 'ordersn Shopee' }}"
                data-testid="fetch-order-ref-input" />
            @error('order_ref')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="fetch-shop-ref" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">
                Shop {{ in_array($selectedChannel, ['tiktok', 'tokopedia'], true) ? '(shop_ref)' : '(shop_id)' }}
            </label>
            @if($shopOptions->isEmpty())
            <input type="text" id="fetch-shop-ref" name="shop_ref" value="{{ old('shop_ref') }}" {{ $shopRequired ? 'required' : '' }}
                class="mt-1 w-full rounded-md border-gray-300 font-mono text-sm shadow-sm"
                data-testid="fetch-shop-ref-input" />
            <p class="mt-1 text-xs text-amber-700">Belum ada mapping sync — isi manual shop id/ref.</p>
            @else
            <select id="fetch-shop-ref" name="shop_ref" class="mt-1 w-full rounded-md border-gray-300 font-mono text-sm shadow-sm" {{ $shopRequired ? 'required' : '' }} data-testid="fetch-shop-ref-select">
                @if(! $shopRequired)
                <option value="{{ $shopOptions->first() }}">{{ $shopOptions->first() }}</option>
                @else
                <option value="">— pilih shop —</option>
                @foreach($shopOptions as $ref)
                <option value="{{ $ref }}" @selected(old('shop_ref') === (string) $ref)>{{ $ref }}</option>
                @endforeach
                @endif
            </select>
            @endif
            @error('shop_ref')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-wrap gap-2 pt-2">
            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" data-testid="fetch-order-submit">
                Tarik &amp; simpan
            </button>
            <a href="{{ route('omnichannel.orders.index', ['view' => 'events']) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Batal</a>
        </div>
    </form>

    <p class="max-w-2xl text-xs text-gray-500">
        CLI: <code class="text-xs">php artisan marketplace:pull-order</code> <code>ORDER_REF</code> <code>--channel=shopee</code> <code>--shop-ref=</code>
    </p>
</div>
@endsection
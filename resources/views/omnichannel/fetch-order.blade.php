@extends('layouts.app')

@section('title', 'Omnichannel — Tarik order (API)')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Omnichannel', 'href' => route('omnichannel.orders.index', ['view' => 'orders'])],
    ['title' => 'Tarik order', 'href' => route('omnichannel.fetch-order.index')],
];
$selectedChannel = old('channel', $selectedChannel);
$isTikTokFamily = in_array($selectedChannel, ['tiktok', 'tokopedia'], true);
$shopRequired = $shopOptions->count() !== 1;
$channelOptions = [
    'shopee' => 'Shopee',
    'tiktok' => 'TikTok Shop',
    'tokopedia' => 'Tokopedia (via TikTok API)',
];
$shopOptionsList = [];
if ($shopOptions->isEmpty()) {
    $shopOptionsList = [];
} elseif (! $shopRequired) {
    $shopOptionsList = [(string) $shopOptions->first() => (string) $shopOptions->first()];
} else {
    $shopOptionsList = ['' => '— pilih shop —'];
    foreach ($shopOptions as $ref) {
        $shopOptionsList[(string) $ref] = (string) $ref;
    }
}
@endphp

<div class="flex flex-col gap-4 p-2 sm:gap-6 sm:p-4" data-testid="omnichannel-fetch-order-page">
    <div class="flex flex-col justify-between gap-3 md:flex-row md:items-end">
        <div class="max-w-3xl">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Tarik order (marketplace API)</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Maintainer: ambil satu order / retur terkait lewat API channel, lalu simpan seperti webhook masuk
                (<code class="rounded bg-gray-100 px-1 text-xs dark:bg-gray-800">marketplace_webhook_events</code>,
                <code class="rounded bg-gray-100 px-1 text-xs dark:bg-gray-800">marketplace_channel_orders</code>).
                Tokopedia memakai API TikTok Shop.
            </p>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                <a href="{{ route('omnichannel.orders.index', ['view' => 'orders']) }}" class="text-blue-700 hover:underline dark:text-blue-400">Current orders</a>
                ·
                <a href="{{ route('omnichannel.returns.index') }}" class="text-blue-700 hover:underline dark:text-blue-400">Marketplace returns</a>
                ·
                <a href="{{ route('omnichannel.missed-push.index') }}" class="text-blue-700 hover:underline dark:text-blue-400">Missed push</a>
            </p>
        </div>
    </div>

    @if(session('success'))
    <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900 dark:border-green-800 dark:bg-green-950/40 dark:text-green-200" data-testid="omnichannel-fetch-order-flash-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40" data-testid="omnichannel-fetch-order-flash-error">{{ session('error') }}</div>
    @endif
    @if(session('warning'))
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40" data-testid="omnichannel-fetch-order-flash-warning">{{ session('warning') }}</div>
    @endif

    @if(! $unifiedStorage)
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40" data-testid="omnichannel-fetch-order-unified-warn">
        <code class="text-xs">MARKETPLACE_WEBHOOK_UNIFIED_STORAGE=false</code> — baris tidak masuk tabel unified. Aktifkan env untuk lihat di Current orders / Webhooks.
    </div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800/50" data-testid="omnichannel-fetch-order-form-card">
        <div class="border-b border-gray-100 px-3 py-2.5 dark:border-gray-700">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Parameter pull</span>
        </div>
        <form method="post"
              action="{{ route('omnichannel.fetch-order.store') }}"
              class="p-3"
              data-testid="omnichannel-fetch-order-form"
              x-data="{
                  channelIndexUrl: @js(route('omnichannel.fetch-order.index')),
                  onChannelPickerChange(event) {
                      window.location = this.channelIndexUrl + '?channel=' + encodeURIComponent(event.target.value);
                  },
              }">
            @csrf

            <div class="flex flex-wrap items-end gap-2">
                <div class="flex min-w-[10rem] flex-col gap-1" data-testid="fetch-order-channel-field">
                    <label for="fetch-channel" class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Channel</label>
                    <select id="fetch-channel" name="channel"
                            class="h-9 rounded-md border border-gray-300 bg-white px-2 text-sm text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                            @change="onChannelPickerChange($event)">
                        @foreach($channelOptions as $value => $label)
                        <option value="{{ $value }}" @selected($selectedChannel === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('channel')<p class="text-[10px] text-red-600">{{ $message }}</p>@enderror
                </div>

                @include('omnichannel.partials.form-field', [
                    'name' => 'order_ref',
                    'id' => 'fetch-order-ref',
                    'label' => 'Order ref',
                    'type' => 'text',
                    'value' => old('order_ref'),
                    'required' => true,
                    'mono' => true,
                    'placeholder' => $isTikTokFamily ? 'order_id TikTok' : 'ordersn Shopee',
                    'testid' => 'fetch-order-ref-input',
                    'extraClass' => 'min-w-[12rem] md:min-w-[16rem]',
                    'attributes' => 'maxlength="128"',
                ])

                @if($shopOptions->isEmpty())
                    @include('omnichannel.partials.form-field', [
                        'name' => 'shop_ref',
                        'id' => 'fetch-shop-ref',
                        'label' => $isTikTokFamily ? 'Shop (shop_ref)' : 'Shop (shop_id)',
                        'type' => 'text',
                        'value' => old('shop_ref'),
                        'required' => $shopRequired,
                        'mono' => true,
                        'testid' => 'fetch-shop-ref-input',
                        'hint' => 'Belum ada mapping sync — isi manual shop id/ref.',
                        'attributes' => $shopRequired ? 'required' : '',
                    ])
                @else
                    @include('omnichannel.partials.form-field', [
                        'name' => 'shop_ref',
                        'id' => 'fetch-shop-ref',
                        'label' => $isTikTokFamily ? 'Shop (shop_ref)' : 'Shop (shop_id)',
                        'type' => 'select',
                        'selected' => old('shop_ref', $shopRequired ? '' : (string) $shopOptions->first()),
                        'required' => $shopRequired,
                        'mono' => true,
                        'testid' => 'fetch-shop-ref-select',
                        'options' => $shopOptionsList,
                        'extraClass' => 'max-w-[16rem]',
                        'attributes' => $shopRequired ? 'required' : '',
                    ])
                @endif

                <div class="flex gap-2 pb-0.5">
                    <button type="submit"
                            class="h-9 rounded-lg bg-blue-700 px-4 text-sm font-medium text-white hover:bg-blue-800"
                            data-testid="fetch-order-submit">
                        Tarik &amp; simpan
                    </button>
                    <a href="{{ route('omnichannel.orders.index', ['view' => 'events']) }}"
                       class="inline-flex h-9 items-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                        Batal
                    </a>
                </div>
            </div>

            <p class="mt-2 text-[10px] text-gray-500 dark:text-gray-400">
                Pilih channel lalu isi order ref + shop. Status terbaru dari API akan ditulis ke unified storage (sama seperti webhook).
            </p>
        </form>
    </div>

    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-300" data-testid="omnichannel-fetch-order-cli-hint">
        <span class="font-semibold text-gray-900 dark:text-gray-100">CLI</span>
        <code class="ml-1 text-xs">php artisan marketplace:pull-order ORDER_REF --channel=shopee --shop-ref=</code>
    </div>
</div>
@endsection
@extends('layouts.app')

@section('title', 'TikTok — Sinkron gudang')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Gudang marketplace', 'href' => route('marketplace.warehouse-sync.index', ['channel' => 'tiktok'])],
    ['title' => 'Tambah TikTok', 'href' => route('marketplace.warehouse-sync.create', ['channel' => 'tiktok'])],
];
    require resource_path('views/partials/form-control-class-vars.php');
$selectedCipher = old('shop_cipher');
@endphp

<div class="flex flex-col gap-6 p-4" data-testid="tiktok-shop-sync-create">
    <div class="flex items-center gap-4">
        <a href="{{ route('marketplace.warehouse-sync.index', ['channel' => 'tiktok']) }}" class="flex h-9 w-9 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700/50">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Tambah toko TikTok</h1>
    </div>

    @if(! $connectionReady)
        <div class="max-w-2xl rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
            @if(! $oauthAuthorized)
                OAuth TikTok Shop belum tersambung. <a href="{{ route('tiktok-shop.authorize', ['return' => 'tiktok-sync']) }}" class="font-medium underline">Authorize di sini</a>.
            @else
                App key / secret atau token akses belum siap.
            @endif
        </div>
    @endif

    <div class="max-w-2xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800/50">
        <form method="POST" action="{{ route('marketplace.warehouse-sync.store', ['channel' => 'tiktok']) }}" class="space-y-6"
              x-data="{
                onShopPick(cipher) {
                    const opt = document.querySelector('#tiktok_authorized_shop option:checked');
                    document.getElementById('shop_ref').value = opt ? (opt.dataset.shopId || '') : '';
                    document.getElementById('shop_cipher').value = cipher;
                    document.getElementById('shop_name').value = opt ? (opt.dataset.shopName || '') : '';
                }
              }">
            @csrf
            <div class="space-y-2">
                <label for="tiktok_authorized_shop" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Toko (OAuth)</label>
                <select id="tiktok_authorized_shop" class="{{ $formControlClassLg }}" @disabled(! $connectionReady || count($authorizedShops) === 0) @change="onShopPick($event.target.value)">
                    <option value="">Pilih toko…</option>
                    @foreach($authorizedShops as $shop)
                    <option value="{{ $shop['cipher'] }}" data-shop-id="{{ $shop['id'] }}" data-shop-name="{{ $shop['name'] }}" @selected($selectedCipher === $shop['cipher'])>{{ $shop['name'] }} · {{ $shop['id'] ?: '—' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="shop_ref" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">shop_ref</label>
                <input type="text" name="shop_ref" id="shop_ref" value="{{ old('shop_ref') }}" required maxlength="64" class="{{ $formControlClassLg }} font-mono">
            </div>
            <div>
                <label for="shop_cipher" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">shop_cipher</label>
                <input type="text" name="shop_cipher" id="shop_cipher" value="{{ old('shop_cipher') }}" required maxlength="64" class="{{ $formControlClassLg }} font-mono">
            </div>
            <div>
                <label for="shop_name" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama toko</label>
                <input type="text" name="shop_name" id="shop_name" value="{{ old('shop_name') }}" maxlength="255" class="{{ $formControlClassLg }}">
            </div>
            <div class="flex justify-end gap-3">
                <a href="{{ route('marketplace.warehouse-sync.index', ['channel' => 'tiktok']) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">Batal</a>
                <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white dark:bg-gray-100 dark:text-gray-900" data-testid="tiktok-shop-sync-sync-warehouses">Sinkron gudang</button>
            </div>
        </form>
    </div>
</div>
@endsection
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

    @if($flash['error'] ?? null)
        <div class="max-w-2xl rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100" data-testid="tiktok-warehouse-sync-error">{{ $flash['error'] }}</div>
    @endif

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

    <div class="max-w-2xl rounded-xl border border-dashed border-gray-300 bg-gray-50/80 p-6 dark:border-gray-600 dark:bg-gray-900/30">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Tambah gudang manual (tanpa API)</h2>
        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Jika <code class="font-mono">GET /logistics/…/warehouses</code> gagal atau kosong, isi <code class="font-mono">platform_warehouse_id</code> dari Partner Center / payload webhook.</p>
        <form method="POST" action="{{ route('marketplace.warehouse-sync.tiktok.manual') }}" class="mt-4 space-y-4">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="manual_shop_ref" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">shop_ref</label>
                <input type="text" name="shop_ref" id="manual_shop_ref" value="{{ old('shop_ref') }}" required maxlength="64" class="{{ $formControlClassLg }} font-mono text-sm">
            </div>
            <div>
                <label for="manual_shop_cipher" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">shop_cipher</label>
                <input type="text" name="shop_cipher" id="manual_shop_cipher" value="{{ old('shop_cipher') }}" required maxlength="64" class="{{ $formControlClassLg }} font-mono text-sm">
            </div>
            </div>
            <div>
                <label for="manual_shop_name" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Nama toko</label>
                <input type="text" name="shop_name" id="manual_shop_name" value="{{ old('shop_name') }}" maxlength="255" class="{{ $formControlClassLg }} text-sm">
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="manual_platform_warehouse_id" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">platform_warehouse_id</label>
                    <input type="text" name="platform_warehouse_id" id="manual_platform_warehouse_id" value="{{ old('platform_warehouse_id') }}" required maxlength="64" class="{{ $formControlClassLg }} font-mono text-sm" placeholder="7392489215199020821">
                </div>
                <div>
                    <label for="manual_platform_warehouse_name" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Nama platform</label>
                    <input type="text" name="platform_warehouse_name" id="manual_platform_warehouse_name" value="{{ old('platform_warehouse_name') }}" maxlength="255" class="{{ $formControlClassLg }} text-sm" placeholder="CITOS">
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-lg border border-gray-400 bg-white px-4 py-2 text-sm font-medium text-gray-900 hover:bg-gray-50 dark:border-gray-500 dark:bg-gray-800 dark:text-gray-100" data-testid="tiktok-shop-sync-manual-warehouse">Simpan baris manual</button>
            </div>
        </form>
    </div>
</div>
@endsection
@extends('layouts.app')

@section('title', 'TikTok Shop — Tambah mapping')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'TikTok mapping', 'href' => route('tiktok-shop.sync.index')],
    ['title' => 'Tambah', 'href' => route('tiktok-shop.sync.create')],
];
$warehouseRoute = route('transactions.lookup', ['type' => 'sell', 'role' => 'sender', 'addrbook_type' => $addrbookTypes['warehouse']]);
    require resource_path('views/partials/form-control-class-vars.php');
$selectedCipher = old('shop_cipher');
@endphp

<div class="flex flex-col gap-6 p-4" data-testid="tiktok-shop-sync-create">
    <div class="flex items-center gap-4">
        <a href="{{ route('tiktok-shop.sync.index') }}" class="flex h-9 w-9 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700/50">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Tambah mapping TikTok Shop</h1>
    </div>

    @if(! $connectionReady)
        <div class="max-w-2xl rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
            @if(! $oauthAuthorized)
                OAuth TikTok Shop belum tersambung. <a href="{{ route('tiktok-shop.authorize', ['return' => 'tiktok-sync']) }}" class="font-medium underline">Authorize di sini</a> lalu kembali untuk memilih toko.
            @else
                App key / secret atau token akses belum siap — periksa <code class="text-xs">TIKTOK_SHOP_*</code> di .env atau authorize ulang.
            @endif
        </div>
    @endif

    <div class="max-w-2xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800/50">
        <form method="POST" action="{{ route('tiktok-shop.sync.store') }}" class="space-y-6"
              x-data="{
                warehousesByCipher: @js($sellerWarehousesByCipher),
                sellerWarehouses: @js($initialSellerWarehouses),
                onShopPick(cipher) {
                    const opt = document.querySelector('#tiktok_authorized_shop option:checked');
                    const id = opt ? (opt.dataset.shopId || '') : '';
                    const name = opt ? (opt.dataset.shopName || '') : '';
                    document.getElementById('shop_ref').value = id;
                    document.getElementById('shop_cipher').value = cipher;
                    document.getElementById('shop_name').value = name;
                    this.sellerWarehouses = this.warehousesByCipher[cipher] || [];
                }
              }">
            @csrf
            <input type="hidden" name="warehouse_id" id="warehouse_id" value="{{ old('warehouse_id') }}">

            <div class="space-y-2">
                <label for="tiktok_authorized_shop" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Toko TikTok (OAuth)</label>
                <select id="tiktok_authorized_shop"
                        class="{{ $formControlClassLg }}"
                        @disabled(! $connectionReady || count($authorizedShops) === 0)
                        @change="onShopPick($event.target.value)">
                    <option value="">Pilih toko dari Partner API…</option>
                    @foreach($authorizedShops as $shop)
                    <option value="{{ $shop['cipher'] }}"
                            data-shop-id="{{ $shop['id'] }}"
                            data-shop-name="{{ $shop['name'] }}"
                            @selected($selectedCipher === $shop['cipher'])>
                        {{ $shop['name'] }} · {{ $shop['id'] ?: '—' }}
                    </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 dark:text-gray-400">Mengisi <code class="text-[11px]">shop_ref</code>, <code class="text-[11px]">shop_cipher</code>, dan nama toko untuk webhook.</p>
            </div>

            <div>
                <label for="shop_ref" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">shop_ref / shop_id (webhook)</label>
                <input type="text" name="shop_ref" id="shop_ref" value="{{ old('shop_ref') }}" required maxlength="64" class="{{ $formControlClassLg }} font-mono">
                @error('shop_ref')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="shop_cipher" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">shop_cipher</label>
                <input type="text" name="shop_cipher" id="shop_cipher" value="{{ old('shop_cipher') }}" maxlength="64" class="{{ $formControlClassLg }} font-mono">
            </div>
            <div>
                <label for="shop_name" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama toko (opsional)</label>
                <input type="text" name="shop_name" id="shop_name" value="{{ old('shop_name') }}" maxlength="255" class="{{ $formControlClassLg }}">
            </div>

            <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 text-sm dark:border-gray-600 dark:bg-gray-900/40" x-show="sellerWarehouses.length > 0" x-cloak>
                <p class="text-[10px] font-bold uppercase text-gray-400 dark:text-gray-500">Gudang penjual TikTok (read-only)</p>
                <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Referensi untuk memetakan gudang Aria — Tokopedia pada seller yang sama memakai TikTok logistics; tidak ada API terpisah.</p>
                <ul class="mt-2 space-y-1 font-mono text-xs text-gray-700 dark:text-gray-300">
                    <template x-for="wh in sellerWarehouses" :key="wh.id">
                        <li>
                            <span x-text="wh.name"></span>
                            <span class="text-gray-500" x-text="' · ' + wh.id + (wh.type ? ' · ' + wh.type : '') + (wh.effect_status ? ' · ' + wh.effect_status : '')"></span>
                        </li>
                    </template>
                </ul>
            </div>

            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Gudang Aria</label>
                @include('jubelio.partials.lookup-combobox', [
                    'endpoint' => $warehouseRoute,
                    'placeholder' => 'Cari gudang…',
                    'hiddenField' => 'warehouse_id',
                    'initialId' => old('warehouse_id'),
                    'initialName' => null,
                ])
                @error('warehouse_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('tiktok-shop.sync.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700/50">Batal</a>
                <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white">Simpan</button>
            </div>
        </form>
    </div>
</div>
@endsection
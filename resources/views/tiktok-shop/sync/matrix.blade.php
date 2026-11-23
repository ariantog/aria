@extends('layouts.app')

@section('title', 'TikTok Shop — Matrix gudang')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'TikTok mapping', 'href' => route('tiktok-shop.sync.index')],
    ['title' => $shopName ?: $shopRef, 'href' => route('tiktok-shop.sync.matrix', ['shopRef' => $shopRef])],
];
    require resource_path('views/partials/form-control-class-vars.php');
@endphp

<div class="flex flex-col gap-6 p-4" data-testid="tiktok-shop-sync-matrix">
    @if($flash['success'] ?? null)
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-100">{{ $flash['success'] }}</div>
    @endif
    @if($flash['error'] ?? null)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100">{{ $flash['error'] }}</div>
    @endif

    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <a href="{{ route('tiktok-shop.sync.index') }}" class="mb-2 inline-flex items-center text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">← Daftar toko</a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Matrix gudang — {{ $shopName ?: 'Toko' }}</h1>
            <p class="mt-1 font-mono text-xs text-gray-600 dark:text-gray-400">shop_ref: {{ $shopRef }} · cipher: {{ $shopCipher ?: '—' }}</p>
        </div>
        @if($connectionReady)
        <form method="POST" action="{{ route('tiktok-shop.sync.matrix.refresh', ['shopRef' => $shopRef]) }}">
            @csrf
            <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-800 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700/50" data-testid="tiktok-shop-sync-refresh">Refresh dari TikTok</button>
        </form>
        @endif
    </div>

    <form method="POST" action="{{ route('tiktok-shop.sync.matrix.update', ['shopRef' => $shopRef]) }}" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800/50">
        @csrf
        @method('PATCH')
        <div class="overflow-x-auto">
            <table class="w-full min-w-[48rem] text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                    <tr>
                        <th class="px-3 py-3">platform warehouse_id</th>
                        <th class="px-3 py-3">Nama platform</th>
                        <th class="px-3 py-3">Tipe</th>
                        <th class="px-3 py-3 w-36">Commerce</th>
                        <th class="px-3 py-3">Gudang Aria</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($rows as $index => $row)
                    <tr>
                        <td class="px-3 py-2 font-mono text-xs">{{ $row->platform_warehouse_id }}</td>
                        <td class="px-3 py-2 text-gray-800 dark:text-gray-200">{{ $row->platform_warehouse_name ?: '—' }}</td>
                        <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-400">{{ $row->platform_warehouse_type ?: '—' }}</td>
                        <td class="px-3 py-2">
                            <input type="hidden" name="mappings[{{ $index }}][id]" value="{{ $row->id }}">
                            <select name="mappings[{{ $index }}][commerce_platform]" class="{{ $formControlClass }} text-xs">
                                <option value="" @selected($row->commerce_platform === '')>Keduanya</option>
                                <option value="TIKTOK_SHOP" @selected($row->commerce_platform === 'TIKTOK_SHOP')>TikTok Shop</option>
                                <option value="TOKOPEDIA" @selected($row->commerce_platform === 'TOKOPEDIA')>Tokopedia</option>
                            </select>
                        </td>
                        <td class="px-3 py-2">
                            <select name="mappings[{{ $index }}][warehouse_id]" class="{{ $formControlClassLg }} text-sm min-w-[12rem]">
                                <option value="0">— belum dipetakan —</option>
                                @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" @selected((int) $row->warehouse_id === (int) $wh->id)>{{ $wh->name }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="flex justify-end border-t border-gray-100 px-4 py-3 dark:border-gray-700">
            <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white">Simpan mapping</button>
        </div>
    </form>
</div>
@endsection
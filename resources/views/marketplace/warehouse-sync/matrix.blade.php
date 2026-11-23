@extends('layouts.app')

@section('title', 'Matrix gudang marketplace')

@section('content')
@php
    require resource_path('views/partials/form-control-class-vars.php');
    $matrixShopRef = $shopRef;
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
            <a href="{{ route('marketplace.warehouse-sync.index', ['channel' => $channel]) }}" class="mb-2 inline-block text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400">← Daftar {{ $channel }}</a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Matrix — {{ ucfirst($channel) }}</h1>
            <p class="mt-1 font-mono text-xs text-gray-600 dark:text-gray-400">shop_ref: {{ $shopRef === '_oauth' ? '—' : $shopRef }}</p>
        </div>
        @if($connectionReady)
        <form method="POST" action="{{ route('marketplace.warehouse-sync.matrix.refresh', ['channel' => $channel, 'shopRef' => $matrixShopRef]) }}">
            @csrf
            <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium dark:border-gray-600 dark:bg-gray-800" data-testid="tiktok-shop-sync-refresh">Refresh dari {{ $channel === 'tiktok' ? 'TikTok' : 'Shopee' }}</button>
        </form>
        @endif
    </div>

    <form method="POST" action="{{ route('marketplace.warehouse-sync.matrix.update', ['channel' => $channel, 'shopRef' => $matrixShopRef]) }}" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800/50">
        @csrf
        @method('PATCH')
        <div class="overflow-x-auto">
            <table class="w-full min-w-[48rem] text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-700 dark:bg-gray-900/40">
                    <tr>
                        <th class="px-3 py-3">Location</th>
                        <th class="px-3 py-3">warehouse_id</th>
                        <th class="px-3 py-3">Nama platform</th>
                        @if($showCommerceColumn)
                        <th class="px-3 py-3 w-36">Commerce</th>
                        @endif
                        <th class="px-3 py-3">Gudang Aria</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($rows as $index => $row)
                    <tr>
                        <input type="hidden" name="mappings[{{ $index }}][id]" value="{{ $row->id }}">
                        <td class="px-3 py-2 font-mono text-xs">{{ $row->platform_location_id ?: '—' }}</td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $row->platform_warehouse_id }}</td>
                        <td class="px-3 py-2">{{ $row->platform_warehouse_name ?: '—' }}</td>
                        @if($showCommerceColumn)
                        <td class="px-3 py-2">
                            <select name="mappings[{{ $index }}][commerce_platform]" class="{{ $formControlClass }} text-xs">
                                <option value="" @selected($row->commerce_platform === '')>Keduanya</option>
                                <option value="TIKTOK_SHOP" @selected($row->commerce_platform === 'TIKTOK_SHOP')>TikTok Shop</option>
                                <option value="TOKOPEDIA" @selected($row->commerce_platform === 'TOKOPEDIA')>Tokopedia</option>
                            </select>
                        </td>
                        @endif
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
            <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white dark:bg-gray-100 dark:text-gray-900">Simpan mapping</button>
        </div>
    </form>
</div>
@endsection
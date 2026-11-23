@extends('layouts.app')

@section('title', 'Shopee Order Lookup')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Shopee FLEXI Shops', 'href' => route('shopee.flexi.index')],
    ['title' => 'Order lookup', 'href' => route('shopee.flexi.lookup')],
];
@endphp

<div class="flex flex-col gap-6 p-4" data-testid="shopee-flexi-order-lookup">
    <div>
        <h1 class="text-2xl font-bold">Shopee order lookup</h1>
        <p class="mt-1 max-w-3xl text-sm text-gray-500">
            Tarik <code>get_order_detail</code> tanpa menunggu webhook atau poll — untuk menemukan
            <strong class="font-medium text-gray-700">shop_id</strong>, status, lokasi pickup, dan preview mapping gudang.
            <strong class="font-medium text-gray-700">shop_id</strong> dan <strong class="font-medium text-gray-700">ordersn</strong> keduanya wajib (token OAuth per shop).
        </p>
    </div>

    @if(! empty($authorizedShopIds))
        <p class="text-xs text-gray-600">
            OAuth shop IDs: <span class="font-mono" data-testid="shopee-oauth-shop-ids">{{ implode(', ', array_map('strval', $authorizedShopIds)) }}</span>
        </p>
    @else
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Belum ada shop di OAuth — authorize lewat <a href="{{ route('shopee-admin.authorize') }}" class="font-medium underline">Shopee Admin</a> dulu.
        </div>
    @endif

    <form method="POST" action="{{ route('shopee.flexi.lookup.search') }}" class="max-w-xl space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        <div>
            <label for="order_ref" class="block text-sm font-medium text-gray-700">Order ref</label>
            <input type="text" name="order_ref" id="order_ref" value="{{ $orderRef }}" required
                placeholder="ordersn atau SPMY-240101ABC"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm focus:border-orange-500 focus:ring-1 focus:ring-orange-500">
            <p class="mt-1 text-xs text-gray-500">Ordersn mentah atau invoice ber-prefix (<code>SPMY-…</code>) — prefix akan di-strip.</p>
            @error('order_ref') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="shop_id" class="block text-sm font-medium text-gray-700">Shop ID</label>
            <input type="number" name="shop_id" id="shop_id" value="{{ $shopId }}" required min="1"
                placeholder="contoh: 1925605693"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm focus:border-orange-500 focus:ring-1 focus:ring-orange-500"
                data-testid="shopee-lookup-shop-id">
            @error('shop_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="submit" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">
                Lookup
            </button>
            <a href="{{ route('shopee.flexi.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">FLEXI shops</a>
        </div>
    </form>

    @if($result !== null)
        <div class="space-y-4" data-testid="shopee-lookup-result">
            @if(! ($result['ok'] ?? false))
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" data-testid="shopee-lookup-error">
                    <p>{{ $result['error'] ?? 'Lookup gagal.' }}</p>
                    @if(filled($result['error_detail'] ?? null) && ($result['error_detail'] ?? '') !== ($result['error'] ?? ''))
                        <p class="mt-2 font-mono text-xs text-red-900/90">{{ $result['error_detail'] }}</p>
                    @endif
                </div>
            @else
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">
                    Order ditemukan — shop_id <span class="font-mono font-semibold" data-testid="result-shop-id">{{ $result['shop_id'] }}</span>,
                    ordersn <span class="font-mono">{{ $result['order_sn'] }}</span>
                </div>
            @endif

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="w-full text-left text-sm">
                    <tbody class="divide-y divide-gray-100">
                        <tr><th class="w-48 bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">shop_id</th>
                            <td class="px-4 py-3 font-mono" data-testid="result-shop-id-cell">{{ $result['shop_id'] ?? '—' }}</td></tr>
                        <tr><th class="bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">OAuth shop</th>
                            <td class="px-4 py-3">{{ ($result['shop_authorized'] ?? false) ? 'Ya' : 'Tidak' }}</td></tr>
                        <tr><th class="bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Region hint</th>
                            <td class="px-4 py-3">{{ $result['region_hint'] ?? '—' }}</td></tr>
                        <tr><th class="bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Invoice prefix</th>
                            <td class="px-4 py-3 font-mono">{{ $result['invoice_prefix'] ?? '—' }}</td></tr>
                        <tr><th class="bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Invoice preview</th>
                            <td class="px-4 py-3 font-mono">{{ $result['invoice_preview'] ?? '—' }}</td></tr>
                        <tr><th class="bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">FLEXI row</th>
                            <td class="px-4 py-3 text-gray-600">
                                @if(! empty($result['flexi_mapping']))
                                    <span class="font-mono">{{ $result['flexi_mapping']['invoice_prefix'] }}</span>
                                    @if($result['flexi_mapping']['direct_order_ingest'] ?? false) · poll ingest @endif
                                    @if(filled($result['flexi_mapping']['label'] ?? '')) · {{ $result['flexi_mapping']['label'] }} @endif
                                @else
                                    <span class="text-gray-400">Belum di /shopee-flexi — preview prefix dari config/sync legacy.</span>
                                @endif
                            </td></tr>
                        <tr><th class="bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Order status</th>
                            <td class="px-4 py-3 font-mono" data-testid="result-order-status">{{ $result['order_status'] ?? '—' }}</td></tr>
                        <tr><th class="bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Pickup</th>
                            <td class="px-4 py-3 font-mono text-xs">
                                @if(! empty($result['pickup']))
                                    loc: {{ $result['pickup']['shopee_location_id'] !== '' ? $result['pickup']['shopee_location_id'] : '—' }}
                                    · wh: {{ $result['pickup']['shopee_warehouse_id'] ?? 0 }}
                                @else
                                    —
                                @endif
                            </td></tr>
                        <tr><th class="bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Aria gudang</th>
                            <td class="px-4 py-3">
                                @if(($result['warehouse_id'] ?? 0) > 0)
                                    #{{ $result['warehouse_id'] }} {{ $result['warehouse_name'] ?? '' }}
                                @else
                                    <span class="text-amber-800">Tidak ter-resolve — cek product_location_id vs <a href="{{ route('shopee.sync.index') }}" class="underline">Warehouse Map</a>.</span>
                                @endif
                            </td></tr>
                    </tbody>
                </table>
            </div>

            @if(! empty($result['sync_matches']))
                <div>
                    <h2 class="text-sm font-semibold text-gray-800">Matching shopee_syncs rows</h2>
                    <ul class="mt-2 list-inside list-disc text-sm text-gray-600">
                        @foreach($result['sync_matches'] as $match)
                            <li class="font-mono text-xs">
                                #{{ $match['id'] }} gudang {{ $match['warehouse_name'] }}
                                (loc {{ $match['shopee_location_id'] ?: '—' }}, wh {{ $match['shopee_warehouse_id'] }})
                                @if($match['matched_resolved']) <span class="text-green-700">← resolved</span> @endif
                                @if($match['matched_pickup']) <span class="text-blue-700">← pickup match</span> @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(! empty($result['api_snippet']))
                <details class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-xs">
                    <summary class="cursor-pointer font-medium text-gray-800">API snippet</summary>
                    <pre class="mt-3 max-h-96 overflow-auto whitespace-pre-wrap font-mono text-gray-700">{{ json_encode($result['api_snippet'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </details>
            @endif
        </div>
    @endif
</div>
@endsection
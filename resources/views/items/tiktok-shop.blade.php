@extends('layouts.app')

@section('title', 'TikTok Shop: ' . $item->name)

@section('content')
@php
$base = $item->isAssetLancar() ? '/assetlancar' : '/items';
$breadcrumbs = [
    ['title' => $item->isAssetLancar() ? 'Assets' : 'Items', 'href' => $base],
    ['title' => $item->name, 'href' => $item->showUrl()],
    ['title' => 'TikTok Shop', 'href' => '#'],
];
$tiktokPerms = \App\Models\TikTokShop::getPermissions();
@endphp

<div class="p-4 sm:p-6">
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-gray-900">Detail Item #{{ $item->code }}</h1>
    </div>

    @include('items.partials.item-tabs', ['active' => 'TikTok Shop', 'item' => $item])

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('success') }}</div>
    @endif

    @if(! ($connectionReady ?? false))
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Product search memakai TikTok Shop Partner OAuth (<code class="text-xs">tiktok_shop_oauth</code>).
            @can($tiktokPerms['sync'])
            <a href="{{ route('tiktok-shop.authorize') }}" class="font-medium text-violet-800 underline">Authorize TikTok Shop</a>
            atau buka <a href="{{ route('tiktok-shop.sync.index') }}" class="underline">Shop sync</a>.
            @endcan
            @if($searchErrorHint ?? null)
                <span class="mt-2 block text-xs text-red-800">{{ $searchErrorHint }}</span>
            @endif
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-6 py-4">
                <div>
                    <h3 class="font-semibold text-gray-900">TikTok Shop / Tokopedia link</h3>
                    <p class="text-sm text-gray-500">Partner product ID + SKU ID (satu toko per <code class="text-xs">shop_cipher</code>)</p>
                </div>
                @can(\App\Models\Item::getPermissions()['edit'])
                <a href="{{ route('items.tiktok-shop-search', ['item' => $item->id, 'shop_cipher' => $shopCipher ?? '']) }}" class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700">
                    Link SKU
                </a>
                @endcan
            </div>
            <div class="divide-y divide-gray-100 p-4 text-sm">
                <div class="grid grid-cols-2 gap-2 py-2">
                    <span class="font-bold text-gray-500">Product ID</span>
                    <span class="font-mono break-all">{{ $item->tiktok_shop_product_id ?: 'Not linked' }}</span>
                </div>
                <div class="grid grid-cols-2 gap-2 py-2">
                    <span class="font-bold text-gray-500">SKU ID</span>
                    <span class="font-mono break-all">{{ $item->tiktok_shop_sku_id ?: '—' }}</span>
                </div>
                @if(filled($shopCipher ?? null))
                <div class="grid grid-cols-2 gap-2 py-2">
                    <span class="font-bold text-gray-500">shop_cipher</span>
                    <span class="font-mono text-xs break-all text-gray-600">{{ $shopCipher }}</span>
                </div>
                @endif
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 bg-gray-50 px-6 py-4">
                <h3 class="font-semibold text-gray-900">Catatan</h3>
            </div>
            <div class="space-y-3 p-6 text-sm text-gray-700">
                <p>Tokopedia (via TikTok Partner) memakai API dan <strong>shop_cipher</strong> yang sama dengan TikTok Shop.</p>
                <p>Pencocokan SKU memakai <span class="font-mono">{{ $item->code }}</span>@if($item->legacy_code) atau legacy <span class="font-mono">{{ $item->legacy_code }}</span>@endif — field Partner <strong>seller_sku</strong>.</p>
                <p>Scope OAuth yang dibutuhkan: <code class="text-xs">seller.product.basic</code> (product search / read).</p>
            </div>
        </div>
    </div>
</div>
@endsection
@extends('layouts.app')

@section('title', 'Shopee: ' . $item->name)

@section('content')
@php
$base = $item->isAssetLancar() ? '/assetlancar' : '/items';
$breadcrumbs = [
    ['title' => $item->isAssetLancar() ? 'Assets' : 'Items', 'href' => $base],
    ['title' => $item->name, 'href' => $item->showUrl()],
    ['title' => 'Shopee', 'href' => '#'],
];
@endphp

<div class="p-4 sm:p-6">
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-gray-900">Detail Item #{{ $item->code }}</h1>
    </div>

    @include('items.partials.item-tabs', ['active' => 'Shopee', 'item' => $item])

    @if(! ($connectionReady ?? false))
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Authorize Shopee di <a href="{{ route('shopee-ads.index') }}" class="font-medium underline">Shopee Ads</a> (OAuth yang sama untuk stok & iklan).
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-6 py-4">
                <div>
                    <h3 class="font-semibold text-gray-900">Shopee Product Link</h3>
                    <p class="text-sm text-gray-500">Seller Center stock via Open Platform</p>
                </div>
                @can(\App\Models\Item::getPermissions()['edit'])
                <a href="{{ route('items.shopee-search', $item->id) }}" class="inline-flex items-center gap-2 rounded-xl bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">
                    Link SKU
                </a>
                @endcan
            </div>
            <div class="divide-y divide-gray-100 p-4 text-sm">
                <div class="grid grid-cols-2 gap-2 py-2">
                    <span class="font-bold text-gray-500">Shopee item ID</span>
                    <span class="font-mono">{{ $item->shopee_item_id ?: 'Not linked' }}</span>
                </div>
                <div class="grid grid-cols-2 gap-2 py-2">
                    <span class="font-bold text-gray-500">Model ID</span>
                    <span class="font-mono">{{ $item->shopee_model_id ?: '—' }}</span>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 bg-gray-50 px-6 py-4">
                <h3 class="font-semibold text-gray-900">Sellable stock (all locations)</h3>
            </div>
            @if($message !== 'ok')
                <div class="bg-yellow-50 p-8 text-center text-sm italic text-yellow-800">{{ $message }}</div>
            @else
                <div class="space-y-3 p-6">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Sellable</span>
                        <span class="text-2xl font-bold text-orange-600">{{ format_amount($dataShopee['sellable'] ?? 0, 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Reserved</span>
                        <span class="text-lg font-semibold text-gray-700">{{ format_amount($dataShopee['reserved'] ?? 0, 0) }}</span>
                    </div>
                    @if(! empty($dataShopee['location_breakdown']))
                        <div class="mt-4 border-t border-gray-100 pt-4">
                            <p class="mb-2 text-[10px] font-bold uppercase text-gray-400">Per location</p>
                            <ul class="space-y-1 text-xs font-mono">
                                @foreach($dataShopee['location_breakdown'] as $loc)
                                    <li class="flex justify-between"><span>{{ $loc['location_id'] ?: 'default' }}</span><span>{{ format_amount($loc['stock'], 0) }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

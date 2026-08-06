@extends('layouts.app')

@section('title', 'Item: ' . $item->name)

@section('content')
@php
$base = $isAsset ? '/assetlancar' : '/items';
$breadcrumbs = [
    ['title' => $isAsset ? 'Assets' : 'Items', 'href' => $base],
    ['title' => 'Detail', 'href' => '#'],
];
$warehouseItems = $item->warehouseItems ?? collect();
$totalStock = $warehouseItems->sum('quantity');
$groupProductName = optional($item->group)->name ?? '-';
$desc = optional($item->group)->description ?? ($item->description ?? '-');
$nb = optional($item->group)->description2 ?? ($item->description2 ?? '-');
@endphp

<div class="p-4 sm:p-6" x-data="{ showZero: false }">
    {{-- Header --}}
    <div class="mb-6 flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
            <h1 class="mb-1 text-2xl font-bold text-gray-900">Detail Item #{{ $item->code }}</h1>
            <p class="text-sm text-gray-500">Last updated {{ optional($item->updated_at)->format('d/m/Y H:i') ?? 'recently' }}</p>
        </div>
        <div class="flex gap-3">
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print Label
            </button>
            <a href="{{ $base }}/{{ $item->id }}/edit" class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit Details
            </a>
        </div>
    </div>

    @include('items.partials.item-tabs', ['active' => 'Detail'])

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        {{-- Image --}}
        <div class="xl:col-span-5">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                @if($item->image_url)
                    <img src="{{ $item->image_url }}" alt="{{ $item->name }}" class="aspect-[4/3] w-full rounded-xl object-cover">
                @else
                    <div class="flex aspect-[4/3] w-full items-center justify-center rounded-xl bg-gray-50 text-gray-300">
                        <svg class="h-24 w-24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                @endif
            </div>
        </div>

        {{-- Specs --}}
        <div class="xl:col-span-7">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center gap-3 border-b border-gray-100 bg-gray-50 px-6 py-4">
                    <div class="h-6 w-2 rounded-full bg-blue-500"></div>
                    <h3 class="text-sm font-semibold uppercase tracking-widest text-gray-900">Item Specifications</h3>
                </div>
                <div class="space-y-6 p-6">
                    <div class="grid grid-cols-1 gap-6 rounded-xl border border-gray-100 bg-gray-50 p-4 md:grid-cols-2">
                        <div>
                            <p class="mb-1 text-[10px] font-bold uppercase tracking-widest text-gray-500">Product Name</p>
                            <p class="text-lg font-semibold text-gray-900">{{ $item->name }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-[10px] font-bold uppercase tracking-widest text-gray-500">SKU Reference</p>
                            <span class="inline-block rounded border border-blue-200 bg-blue-50 px-2 py-1 font-mono text-sm text-blue-600">{{ $item->code }}</span>
                        </div>
                    </div>

                    <div>
                        <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-gray-500">Barcode / Alias</p>
                        <div class="flex items-center gap-3">
                            <span class="min-w-[80px] font-medium text-gray-900">{{ $item->id }}</span>
                            <span class="h-4 w-px bg-gray-200"></span>
                            <div class="flex-1 rounded-md border border-gray-100 bg-gray-50 px-3 py-1.5">
                                <span class="italic text-gray-500">{{ $groupProductName }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 border-t border-gray-100 pt-4">
                        <div>
                            <p class="mb-1 text-[10px] font-bold uppercase tracking-tight text-gray-500">Description</p>
                            <p class="text-xs leading-relaxed text-gray-600">{{ $desc }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-[10px] font-bold uppercase tracking-tight text-gray-500">NB</p>
                            <p class="text-xs leading-relaxed text-gray-600">{{ $nb }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 border-t border-gray-100 pt-4 md:grid-cols-2">
                        <div>
                            <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-gray-500">Pricing</p>
                            <p class="text-2xl font-black tracking-tight text-gray-900">Rp {{ number_format($item->price, 0, ',', '.') }}</p>
                            <p class="mt-1 text-[10px] text-gray-500">Base Cost: <span class="text-gray-700">Rp {{ number_format($item->cost, 0, ',', '.') }}</span></p>
                        </div>
                        <div>
                            <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-gray-500">Group &amp; Tags</p>
                            <div class="flex flex-wrap items-center gap-2">
                                @if($item->group)
                                <span class="text-xs font-medium text-blue-600 underline underline-offset-4">{{ $item->group->name }}</span>
                                @endif
                                @forelse($item->tags as $tag)
                                <span class="rounded border border-blue-200 bg-blue-50 px-2 py-1 text-[9px] font-bold uppercase tracking-tighter text-blue-600">{{ $tag->name }}</span>
                                @empty
                                    @unless($item->group)<span class="text-[10px] text-gray-400">No tags</span>@endunless
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Warehouse --}}
    <div class="mt-6">
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-col items-start justify-between gap-3 border-b border-gray-100 px-6 py-4 md:flex-row md:items-center">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-900">Warehouse Availability</h3>
                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-gray-500">
                        <input type="checkbox" x-model="showZero" class="rounded border-gray-300"> Show empty warehouses
                    </label>
                    <div class="text-xs font-medium text-gray-500">Total Stock: <span class="text-gray-900">{{ number_format($totalStock, 0, ',', '.') }} Units</span></div>
                </div>
            </div>
            <div class="p-6">
                @if($warehouseItems->isEmpty())
                    <div class="py-8 text-center text-sm italic text-gray-500">No warehouse data available.</div>
                @else
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        @foreach($warehouseItems as $wh)
                        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 p-4 {{ $wh->quantity < 1 ? 'opacity-60' : '' }}"
                             @if($wh->quantity < 1) x-show="showZero" @endif>
                            <div>
                                <p class="font-medium text-gray-900">{{ optional($wh->warehouse)->name ?? 'Unknown' }}</p>
                                <p class="text-[10px] uppercase text-gray-500">ID: {{ optional($wh->warehouse)->id }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-bold {{ $wh->quantity > 0 ? 'text-blue-600' : 'text-gray-400' }}">{{ number_format($wh->quantity, 0, ',', '.') }}</p>
                                <p class="text-[10px] font-bold uppercase text-gray-400">{{ $wh->quantity > 0 ? 'Units' : 'Out of Stock' }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

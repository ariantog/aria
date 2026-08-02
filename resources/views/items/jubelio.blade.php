@extends('layouts.app')

@section('title', 'Jubelio: ' . $item->name)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Items', 'href' => route('items.index')],
    ['title' => $item->name, 'href' => route('items.show', $item->id)],
    ['title' => 'Jubelio', 'href' => '#'],
];
$isAsset = $item->type->value == 2;
@endphp

<div class="p-4 sm:p-6">
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-gray-900">Detail Item #{{ $item->code }}</h1>
    </div>

    @include('items.partials.item-tabs', ['active' => 'Jubelio'])

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        {{-- Image --}}
        <div>
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                @if($item->image_url)
                    <img src="{{ $item->image_url }}" class="aspect-[4/3] w-full rounded-xl object-cover">
                @else
                    <div class="flex aspect-[4/3] w-full items-center justify-center rounded-xl bg-gray-50 text-gray-300">
                        <svg class="h-24 w-24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                @endif
            </div>
        </div>

        {{-- Jubelio data --}}
        <div class="space-y-6">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-6 py-4">
                    <div>
                        <h3 class="font-semibold text-gray-900">Jubelio Sync</h3>
                        <p class="text-sm text-gray-500">Real-time data from Jubelio API</p>
                    </div>
                    <a href="{{ route('items.jubelio-search', $item->id) }}" class="inline-flex items-center gap-2 rounded-xl bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Link Item
                    </a>
                </div>
                <div class="divide-y divide-gray-100">
                    <div class="grid grid-cols-2 p-4">
                        <span class="font-bold text-gray-500">Jubelio Item ID</span>
                        <span class="font-mono text-gray-900">{{ $item->jubelio_item_id ?: 'Not Linked' }}</span>
                    </div>

                    @if($message !== 'ok')
                        <div class="bg-yellow-50 p-8 text-center text-sm italic text-yellow-700">{{ $message }}</div>
                    @else
                        <div class="grid grid-cols-2 p-4">
                            <span class="font-bold text-gray-500">Jubelio Item Name</span>
                            <span class="text-gray-900">{{ $dataJubelio['item_name'] ?? '-' }}</span>
                        </div>
                        <div class="border-y border-gray-100 bg-gray-50 p-4">
                            <span class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-gray-500">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                Inventory Status
                            </span>
                        </div>
                        <div class="grid grid-cols-2 items-center p-4">
                            <span class="ml-4 text-gray-500">On Hand</span>
                            <span class="text-xl font-bold text-blue-600">{{ $dataJubelio['total_stocks']['on_hand'] ?? 0 }}</span>
                        </div>
                        <div class="grid grid-cols-2 items-center p-4">
                            <span class="ml-4 text-gray-500">On Order</span>
                            <span class="text-xl font-bold text-orange-500">{{ $dataJubelio['total_stocks']['on_order'] ?? 0 }}</span>
                        </div>
                        <div class="grid grid-cols-2 items-center p-4">
                            <span class="ml-4 text-gray-500">Available</span>
                            <span class="text-xl font-bold text-green-600">{{ $dataJubelio['total_stocks']['available'] ?? 0 }}</span>
                        </div>
                    @endif
                </div>
            </div>

            @if($item->jubelio_item_id)
            <div class="flex justify-end">
                <button type="button" onclick="window.location.reload()" class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm text-gray-500 hover:bg-gray-100 hover:text-gray-900">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Refresh Data
                </button>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

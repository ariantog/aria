@extends('layouts.app')

@section('title', 'Group Detail: ' . $group->name)

@section('content')
@php
$isAssetGroup = $group->items->contains(fn ($i) => $i->type->value === 2);
$breadcrumbs = [
    ['title' => $isAssetGroup ? 'Assets' : 'Items', 'href' => $isAssetGroup ? '/assetlancar' : '/items'],
    ['title' => 'Groups', 'href' => route('items.group')],
    ['title' => $group->name, 'href' => '#'],
];
@endphp

<div class="p-4 sm:p-6" x-data="{ showZero: false }">
    <div class="mb-4">
        <a href="{{ route('items.group') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back to Group List
        </a>
    </div>

    @if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    {{-- Group info --}}
    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white lg:col-span-1">
            <div class="flex min-h-[280px] items-center justify-center p-4">
                @if($group->image_url)
                    <img src="{{ $group->image_url }}" alt="{{ $group->name }}" class="max-h-[360px] w-auto object-contain">
                @else
                    <svg class="h-20 w-20 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                @endif
            </div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white lg:col-span-2">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="flex items-center gap-2 text-2xl font-bold text-gray-900">
                    <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Group Details
                </h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 gap-x-12 gap-y-6 md:grid-cols-2">
                    @if($pcode)
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wider text-gray-500">Production Code (PCode)</p>
                        <p class="font-mono text-xl font-medium text-gray-900">{{ $pcode }}</p>
                    </div>
                    @endif
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wider text-gray-500">Product Name</p>
                        <p class="text-xl font-medium text-gray-900">
                            {{ $group->name }}
                            @if($usesPlaceholder)
                                <span class="ml-2 rounded bg-amber-100 px-2 py-0.5 text-xs font-normal text-amber-800">pcode placeholder</span>
                            @endif
                        </p>
                    </div>
                    <div><p class="text-sm font-semibold uppercase tracking-wider text-gray-500">Master</p><p class="text-gray-700">{{ $group->master ?: '-' }}</p></div>
                    <div><p class="text-sm font-semibold uppercase tracking-wider text-gray-500">Variant</p><p class="text-gray-700">{{ $group->variant ?: '-' }}</p></div>
                    <div class="border-t border-gray-100 pt-4 md:col-span-2"><p class="text-sm font-semibold uppercase tracking-wider text-gray-500">Description</p><p class="leading-relaxed text-gray-700">{{ $group->description ?: 'No description available for this group.' }}</p></div>

                    @if($canEditGroup)
                    <div class="border-t border-gray-100 pt-4 md:col-span-2">
                        <p class="mb-2 text-sm font-semibold uppercase tracking-wider text-gray-500">Rename Product</p>
                        <p class="mb-3 text-sm text-gray-600">Updates the product name for every item in this group (all sizes with the same pcode).</p>
                        <form method="POST" action="{{ route('items.group-update', $group->id) }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                            @csrf
                            @method('PUT')
                            <div class="flex-1">
                                <label for="group-product-name" class="sr-only">Product name</label>
                                <input id="group-product-name" type="text" name="name"
                                       value="{{ old('name', $usesPlaceholder ? '' : $group->name) }}"
                                       placeholder="{{ $pcode ?: 'Product name' }}"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('name') border-red-500 @enderror">
                                @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                            </div>
                            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save Product Name</button>
                        </form>
                    </div>
                    @endif

                    <div class="flex flex-wrap items-center gap-4 pt-4 md:col-span-2">
                        <label class="flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">
                            <input type="checkbox" x-model="showZero" class="rounded border-gray-300"> Show 0 Quantity
                        </label>
                        @if($isAssetGroup)
                        <a href="{{ route('restock.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-blue-200 px-4 py-2 text-sm font-medium text-blue-600 hover:bg-blue-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            Restock
                        </a>
                        @endif
                        <a href="{{ route('items.group-stats', $group->id) }}" class="inline-flex items-center gap-2 rounded-lg border border-green-200 px-4 py-2 text-sm font-medium text-green-600 hover:bg-green-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            Group Stats
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-900">
        <svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        Items / Variants in Group
    </h2>

    <div class="space-y-6">
        @foreach($group->items as $item)
        @php
            $base = $item->type->value === 2 ? '/assetlancar' : '/items';
            $totalQty = $item->warehouseItems->sum('quantity');
        @endphp
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-col items-start justify-between gap-4 border-b border-gray-100 bg-gray-50 px-6 py-4 md:flex-row md:items-center">
                <div class="flex flex-col">
                    <div class="mb-1 flex items-center gap-3">
                        <span class="rounded border border-gray-200 bg-white px-2 py-0.5 text-xs text-gray-500">{{ $item->id }}</span>
                        <h3 class="text-lg font-bold text-gray-900">{{ $item->code }} - <a href="{{ $base }}/{{ $item->id }}" class="text-blue-600 hover:underline">{{ $item->name }}</a></h3>
                    </div>
                    <p class="font-mono text-xs text-gray-400">{{ $item->pcode }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($isAssetGroup ?? false)
                    <a href="{{ route('restock.index') }}" class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm text-blue-600 hover:bg-blue-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        Restock
                    </a>
                    @endif
                    <a href="{{ $base }}/{{ $item->id }}/transactions" class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100">Transactions</a>
                    <a href="{{ $base }}/{{ $item->id }}/stats" class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100">Stats</a>
                    <a href="{{ $base }}/{{ $item->id }}/edit" class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100">Edit</a>
                </div>
            </div>
            <div class="p-6">
                <div class="max-w-2xl divide-y divide-gray-100">
                    @php $visible = $item->warehouseItems; @endphp
                    @forelse($visible as $wh)
                    <div class="flex justify-between py-3" @if($wh->quantity < 1) x-show="showZero" @endif>
                        <span class="font-medium text-gray-700">{{ $wh->warehouse?->name ?? 'Warehouse #'.$wh->warehouse_id }}</span>
                        <span class="font-mono font-bold {{ $wh->quantity > 0 ? 'text-green-600' : 'text-gray-400' }}">{{ number_format($wh->quantity, 0, ',', '.') }}</span>
                    </div>
                    @empty
                    <div class="py-4 italic text-gray-400">No stock found in warehouses.</div>
                    @endforelse
                    <div class="mt-2 flex justify-between border-t border-gray-200 py-4 text-lg font-bold">
                        <span class="text-gray-900">Total Quantity</span>
                        <span class="font-mono text-green-600">{{ number_format($totalQty, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection

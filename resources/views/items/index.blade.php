@extends('layouts.app')

@section('title', $isAsset ? 'Asset Lancar' : 'Item List')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Items', 'href' => route('items.index')],
    ['title' => $isAsset ? 'Assets' : 'List', 'href' => $baseUrl],
];
$typeTags = ($tags[\App\Models\Tag::TYPE_TYPE] ?? collect());
$sizeTags = ($tags[\App\Models\Tag::TYPE_SIZE] ?? collect());
$warnaTags = ($tags[\App\Models\Tag::TYPE_WARNA] ?? collect());
$jahitTags = ($tags[\App\Models\Tag::TYPE_JAHIT] ?? collect());
$idr = fn ($v) => 'Rp ' . format_amount($v, 0);
$filtersStorageKey = $isAsset ? 'aria-assetlancar-index-filters-open' : 'aria-items-index-filters-open';
@endphp

<div class="flex flex-col gap-3 p-3 sm:p-4" x-data="{
    showImage: true,
    filtersOpen: true,
    filtersStorageKey: @js($filtersStorageKey),
    init() {
        const saved = localStorage.getItem(this.filtersStorageKey);
        this.filtersOpen = saved === null ? true : saved === '1';
        this.$watch('filtersOpen', (value) => {
            localStorage.setItem(this.filtersStorageKey, value ? '1' : '0');
        });
    },
}">
    {{-- Header --}}
    <div class="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">{{ $isAsset ? 'Asset List' : 'Item List' }}</h2>
            <p class="mt-0.5 text-sm text-gray-500">Manage your {{ $isAsset ? 'asset' : 'product' }} inventory efficiently.</p>
        </div>
        <div class="flex items-center gap-3">
            <label class="flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">
                <input type="checkbox" x-model="showImage" class="rounded border-gray-300">
                Show Images
            </label>
            @if(($isAsset && $can['create_asset']) || (! $isAsset && $can['create']))
            <a href="{{ $isAsset ? route('assetlancar.create') : route('items.create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add {{ $isAsset ? 'Asset' : 'Item' }}
            </a>
            @endif
        </div>
    </div>

    @include('items.partials.list-filters', [
        'formAction' => $baseUrl,
        'resetUrl' => $baseUrl,
        'filters' => $filters,
        'typeTags' => $typeTags,
        'sizeTags' => $sizeTags,
        'warnaTags' => $warnaTags,
        'jahitTags' => $jahitTags,
        'showTagFilters' => ! $isAsset,
        'filtersStorageKey' => $filtersStorageKey,
        'testId' => 'items-index-filters',
    ])

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
        <table class="w-full min-w-[1180px] text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="w-16 px-2 py-2.5 font-bold" x-show="showImage">Image</th>
                    <th class="w-16 px-2 py-2.5 font-bold">Barcode</th>
                    <th class="whitespace-nowrap px-2 py-2.5 font-bold">Code</th>
                    <th class="min-w-[16rem] px-2 py-2.5 font-bold">Name</th>
                    <th class="min-w-[8rem] px-2 py-2.5 font-bold">Desc</th>
                    <th class="w-28 px-2 py-2.5 text-right font-bold">Price</th>
                    <th class="min-w-[8rem] px-2 py-2.5 font-bold">NB</th>
                    <th class="w-16 px-2 py-2.5 text-right font-bold">Qty</th>
                    <th class="w-20 px-2 py-2.5 font-bold">Jubelio</th>
                    <th class="w-12 px-2 py-2.5 text-center font-bold"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($items as $item)
                    <tr class="align-middle hover:bg-gray-50">
                        <td class="px-2 py-2" x-show="showImage">
                            @if($item->image_url)
                                <img src="{{ $item->image_url }}" class="h-10 w-10 rounded-md border border-gray-200 object-cover">
                            @else
                                <div class="flex h-10 w-10 items-center justify-center rounded-md border border-gray-200 bg-gray-50 text-gray-300">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 6h16v12H4z"/></svg>
                                </div>
                            @endif
                        </td>
                        <td class="px-2 py-2">
                            <a href="{{ $baseUrl }}/{{ $item->id }}" class="font-medium text-blue-600 hover:underline">{{ $item->id }}</a>
                        </td>
                        <td class="whitespace-nowrap px-2 py-2 font-mono text-xs text-gray-800" data-testid="item-list-code-{{ $item->id }}">{{ $item->code ?: '-' }}</td>
                        <td class="px-2 py-2 text-gray-800" data-testid="item-list-name-{{ $item->id }}">{{ $item->name ?: '-' }}</td>
                        <td class="max-w-[14rem] px-2 py-2 text-gray-700" title="{{ $item->description }}">{{ $item->description ?: '-' }}</td>
                        <td class="whitespace-nowrap px-2 py-2 text-right font-bold tabular-nums text-gray-800">{{ $idr($item->price) }}</td>
                        <td class="max-w-[14rem] px-2 py-2 text-gray-500" title="{{ $item->description2 }}">{{ $item->description2 ?: '--' }}</td>
                        <td class="whitespace-nowrap px-2 py-2 text-right font-bold tabular-nums text-emerald-600">{{ format_amount((float) ($item->active_qty ?? 0), 0) }}</td>
                        <td class="px-2 py-2">
                            @if($item->jubelio_item_id)
                                <span class="inline-flex rounded-full border border-blue-200 bg-blue-100 px-2 py-0.5 text-[10px] text-blue-700">{{ $item->jubelio_item_id }}</span>
                            @else
                                <span class="text-[10px] text-gray-400">no sync</span>
                            @endif
                        </td>
                        <td class="px-2 py-2 text-center">
                            <a href="{{ $baseUrl }}/{{ $item->id }}/edit" class="inline-flex h-8 w-8 items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-blue-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-4 py-12 text-center text-sm italic text-gray-500">No {{ $isAsset ? 'assets' : 'items' }} found matching your filters.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        @include('partials.pagination', ['paginator' => $items, 'label' => $isAsset ? 'assets' : 'items'])
    </div>
</div>
@endsection

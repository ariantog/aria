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
$columnsStorageKey = $isAsset ? 'aria-assetlancar-index-columns' : 'aria-items-index-columns';
@endphp

<div class="flex flex-col gap-3 p-3 sm:p-4" x-data="itemsIndexPage(@js($filtersStorageKey), @js($columnsStorageKey), @js($isAsset))">
    {{-- Header --}}
    <div class="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">{{ $isAsset ? 'Asset List' : 'Item List' }}</h2>
            <p class="mt-0.5 text-sm text-gray-500">Manage your {{ $isAsset ? 'asset' : 'product' }} inventory efficiently.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2" data-testid="items-index-column-toggles">
                <span class="text-[10px] font-bold uppercase text-gray-500">Columns:</span>
                <label class="inline-flex items-center gap-1.5 text-sm text-gray-600">
                    <input type="checkbox" x-model="showImage" class="rounded border-gray-300">
                    Image
                </label>
                <label class="inline-flex items-center gap-1.5 text-sm text-gray-600">
                    <input type="checkbox" x-model="showName" class="rounded border-gray-300">
                    Name
                </label>
                <label class="inline-flex items-center gap-1.5 text-sm text-gray-600">
                    <input type="checkbox" x-model="showDesc" class="rounded border-gray-300">
                    Desc
                </label>
                @if($isAsset)
                <label class="inline-flex items-center gap-1.5 text-sm text-gray-600">
                    <input type="checkbox" x-model="showNb" class="rounded border-gray-300">
                    NB
                </label>
                @endif
            </div>
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
        <table class="w-full min-w-[980px] text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-3 py-2.5 text-left font-medium" x-show="showImage">Image</th>
                    <th class="px-3 py-2.5 text-left font-medium">Barcode</th>
                    <th class="px-3 py-2.5 text-left font-medium">Code</th>
                    <th class="px-3 py-2.5 text-left font-medium">Group</th>
                    <th class="px-3 py-2.5 text-left font-medium" x-show="showName">Name</th>
                    <th class="px-3 py-2.5 text-left font-medium" x-show="showDesc">Desc</th>
                    <th class="px-3 py-2.5 text-right font-medium">Price</th>
                    @if($isAsset)
                    <th class="px-3 py-2.5 text-left font-medium" x-show="showNb">NB</th>
                    @else
                    <th class="px-3 py-2.5 text-left font-medium">NB</th>
                    @endif
                    <th class="px-3 py-2.5 text-right font-medium">Qty</th>
                    <th class="px-3 py-2.5 text-left font-medium">Jubelio</th>
                    <th class="w-12 px-3 py-2.5 text-center font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($items as $item)
                    @php $groupUrl = $item->groupParentUrl(); @endphp
                    <tr class="align-middle hover:bg-gray-50">
                        <td class="px-3 py-2.5" x-show="showImage">
                            @if($item->image_url)
                                <img src="{{ $item->image_url }}" class="h-10 w-10 rounded-md border border-gray-200 object-cover">
                            @else
                                <div class="flex h-10 w-10 items-center justify-center rounded-md border border-gray-200 bg-gray-50 text-gray-300">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 6h16v12H4z"/></svg>
                                </div>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5">
                            <a href="{{ $baseUrl }}/{{ $item->id }}" class="font-medium text-blue-600 hover:underline">{{ $item->id }}</a>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5 font-mono text-xs text-gray-800" data-testid="item-list-code-{{ $item->id }}">{{ $item->code ?: '-' }}</td>
                        <td class="max-w-[160px] px-3 py-2.5" data-testid="item-list-group-{{ $item->id }}">
                            @if($groupUrl)
                                <a href="{{ $groupUrl }}" class="block truncate font-mono text-xs text-blue-600 hover:underline" title="{{ $item->group?->name ?: '-' }}">{{ $item->group?->name ?: '-' }}</a>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="max-w-[220px] px-3 py-2.5 text-gray-800" data-testid="item-list-name-{{ $item->id }}" x-show="showName">
                            <div class="truncate" title="{{ $item->name ?: '-' }}">{{ $item->name ?: '-' }}</div>
                        </td>
                        <td class="max-w-[200px] px-3 py-2.5 text-gray-700" data-testid="item-list-desc-{{ $item->id }}" x-show="showDesc">
                            <div class="truncate" title="{{ $item->catalogDescription() }}">{{ $item->catalogDescription() ?: '-' }}</div>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-right font-semibold tabular-nums text-gray-800">{{ $idr($item->price) }}</td>
                        @if($isAsset)
                        <td class="max-w-[200px] px-3 py-2.5 text-gray-500" x-show="showNb">
                            <div class="truncate" title="{{ $item->catalogDescription2() }}">{{ $item->catalogDescription2() ?: '--' }}</div>
                        </td>
                        @else
                        <td class="max-w-[200px] px-3 py-2.5 text-gray-500">
                            <div class="truncate" title="{{ $item->catalogDescription2() }}">{{ $item->catalogDescription2() ?: '--' }}</div>
                        </td>
                        @endif
                        <td class="whitespace-nowrap px-3 py-2.5 text-right font-semibold tabular-nums text-emerald-600">{{ format_amount((float) ($item->active_qty ?? 0), 0) }}</td>
                        <td class="whitespace-nowrap px-3 py-2.5">
                            @if($item->jubelio_item_id)
                                <span class="inline-flex rounded-full border border-blue-200 bg-blue-100 px-2 py-0.5 text-[10px] text-blue-700">{{ $item->jubelio_item_id }}</span>
                            @else
                                <span class="text-[10px] text-gray-400">no sync</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            <a href="{{ $baseUrl }}/{{ $item->id }}/edit" class="inline-flex h-8 w-8 items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-blue-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="px-4 py-12 text-center text-sm italic text-gray-500">No {{ $isAsset ? 'assets' : 'items' }} found matching your filters.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        @include('partials.pagination', ['paginator' => $items, 'label' => $isAsset ? 'assets' : 'items'])
    </div>
</div>

@push('scripts')
<script>
function itemsIndexPage(filtersStorageKey, columnsStorageKey, isAsset) {
    return {
        showImage: true,
        showName: true,
        showDesc: true,
        showNb: true,
        filtersOpen: true,
        filtersStorageKey: filtersStorageKey,
        columnsStorageKey: columnsStorageKey,
        isAsset: isAsset,
        init() {
            const savedFilters = localStorage.getItem(this.filtersStorageKey);
            this.filtersOpen = savedFilters === null ? true : savedFilters === '1';
            this.$watch('filtersOpen', (value) => {
                localStorage.setItem(this.filtersStorageKey, value ? '1' : '0');
            });

            try {
                const columns = JSON.parse(localStorage.getItem(this.columnsStorageKey) || '{}');
                if (typeof columns.showImage === 'boolean') {
                    this.showImage = columns.showImage;
                }
                if (typeof columns.showName === 'boolean') {
                    this.showName = columns.showName;
                }
                if (typeof columns.showDesc === 'boolean') {
                    this.showDesc = columns.showDesc;
                }
                if (this.isAsset && typeof columns.showNb === 'boolean') {
                    this.showNb = columns.showNb;
                }
            } catch (e) {}

            this.$watch('showImage', () => this.persistColumns());
            this.$watch('showName', () => this.persistColumns());
            this.$watch('showDesc', () => this.persistColumns());
            if (this.isAsset) {
                this.$watch('showNb', () => this.persistColumns());
            }
        },
        persistColumns() {
            const payload = {
                showImage: this.showImage,
                showName: this.showName,
                showDesc: this.showDesc,
            };
            if (this.isAsset) {
                payload.showNb = this.showNb;
            }
            localStorage.setItem(this.columnsStorageKey, JSON.stringify(payload));
        },
    };
}
</script>
@endpush
@endsection

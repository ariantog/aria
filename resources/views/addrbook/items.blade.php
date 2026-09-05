@extends('layouts.app')

@section('title', 'Items: ' . $addrbook->name)

@section('content')
@php
$baseUrl = '/' . $addrbook->type_slug . '/' . $addrbook->id . '/items';
$exportUrl = '/' . $addrbook->type_slug . '/' . $addrbook->id . '/items/export';
$exportQuery = request()->query();
$perPage = $perPage ?? (int) request()->query('per_page', 1000);
$breadcrumbs = [
    ['title' => 'Address Book', 'href' => \App\Models\Addrbook::typeIndexRoute($addrbook->type_slug)],
    ['title' => $addrbook->name, 'href' => '/' . $addrbook->type_slug . '/' . $addrbook->id],
    ['title' => 'Items', 'href' => $baseUrl],
];
$typeTags = ($tags[\App\Models\Tag::TYPE_TYPE] ?? collect());
$sizeTags = ($tags[\App\Models\Tag::TYPE_SIZE] ?? collect());
$warnaTags = ($tags[\App\Models\Tag::TYPE_WARNA] ?? collect());
$jahitTags = ($tags[\App\Models\Tag::TYPE_JAHIT] ?? collect());
$filtersStorageKey = 'aria-warehouse-items-filters-open-' . $addrbook->id;
$columnsStorageKey = 'aria-warehouse-items-columns-' . $addrbook->id;
$hasJubelio = (bool) ($jubelioSync ?? null);
$idr = fn ($v) => 'IDR ' . format_amount($v, 0);
$currentSort = $filters['sort'] ?? 'codeasc';
$sortColumn = preg_replace('/(asc|desc)$/', '', $currentSort);
$sortDirection = str_ends_with($currentSort, 'desc') ? 'desc' : 'asc';
$sortLink = function (string $column) use ($filters, $sortColumn, $sortDirection, $baseUrl) {
    $nextDirection = ($sortColumn === $column && $sortDirection === 'asc') ? 'desc' : 'asc';
    $query = array_merge($filters, ['sort' => $column . $nextDirection]);

    return $baseUrl . '?' . http_build_query(array_filter(
        $query,
        fn ($value) => $value !== null && $value !== '',
    ));
};
$jubelioQtyCell = function (?array $jubelio, string $field, bool $highlightMismatch = false) {
    if (! $jubelio || ! ($jubelio['linked'] ?? false)) {
        return '<span class="text-gray-300">—</span>';
    }

    $value = $jubelio[$field] ?? null;
    if ($value === null) {
        return '<span class="text-gray-300">—</span>';
    }

    $classes = 'font-mono tabular-nums';
    if ($highlightMismatch && ($jubelio['mismatch'] ?? false)) {
        $classes .= ' font-semibold text-red-600';
    } else {
        $classes .= ' text-gray-700';
    }

    return '<span class="' . $classes . '">' . e(format_amount($value, 0)) . '</span>';
};
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4" x-data="warehouseItemsPage(@js($filtersStorageKey), @js($columnsStorageKey), @js($hasJubelio))">
    {{-- Header --}}
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
            <div class="mb-1 flex items-center gap-2">
                <a href="/{{ $addrbook->type_slug }}/{{ $addrbook->id }}" class="text-gray-400 hover:text-gray-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <span class="font-mono text-sm text-gray-400">#{{ $addrbook->id }}</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Warehouse Stock</h1>
            <p class="text-sm text-gray-500">Available inventory for <span class="text-blue-600">{{ $addrbook->name }}</span></p>
            @if($hasJubelio)
                <p class="mt-1 text-xs text-gray-500">
                    Jubelio location:
                    <span class="font-medium text-gray-700">{{ $jubelioSync->jubelio_location_name }}</span>
                    <span class="text-gray-400">· mismatch compares Aria stock to Jubelio available</span>
                </p>
            @endif
        </div>
    </div>

    @include('addrbook.partials.tabs', ['active' => 'items'])

    @if(($jubelioUnlinkedCount ?? 0) > 0)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <strong>{{ $jubelioUnlinkedCount }}</strong> item(s) on this page are not linked to Jubelio (missing Jubelio item ID).
        </div>
    @endif

    @if(($jubelioFetchFailed ?? false) && $hasJubelio)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            Could not fetch Jubelio stock right now. Aria stock is still shown below.
        </div>
    @endif

    @include('items.partials.list-filters', [
        'formAction' => $baseUrl,
        'resetUrl' => $baseUrl,
        'filters' => $filters,
        'typeTags' => $typeTags,
        'sizeTags' => $sizeTags,
        'warnaTags' => $warnaTags,
        'jahitTags' => $jahitTags,
        'showTagFilters' => true,
        'filtersStorageKey' => $filtersStorageKey,
        'testId' => 'warehouse-items-filters',
        'additionalFieldsView' => 'addrbook.partials.warehouse-items-filter-extra',
        'additionalFieldsData' => ['filters' => $filters],
    ])

    <div class="rounded-xl border border-gray-200 bg-white p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex flex-col gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-[10px] font-bold uppercase text-gray-500">Display:</span>
                    <div class="flex rounded-lg border border-gray-200 bg-gray-50 p-1">
                        <button type="button" @click="onlineName = false" :class="!onlineName ? 'bg-gray-800 text-white' : 'text-gray-500'" class="rounded-md px-3 py-1 text-[10px] font-bold uppercase">Normal Name</button>
                        <button type="button" @click="onlineName = true" :class="onlineName ? 'bg-gray-800 text-white' : 'text-gray-500'" class="rounded-md px-3 py-1 text-[10px] font-bold uppercase">Online Name</button>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2" data-testid="warehouse-items-column-toggles">
                    <span class="text-[10px] font-bold uppercase text-gray-500">Columns:</span>
                    <label class="inline-flex items-center gap-1.5 text-xs text-gray-600">
                        <input type="checkbox" x-model="showImage" class="rounded border-gray-300">
                        Image
                    </label>
                    <label class="inline-flex items-center gap-1.5 text-xs text-gray-600">
                        <input type="checkbox" x-model="showName" class="rounded border-gray-300">
                        Name
                    </label>
                    <label class="inline-flex items-center gap-1.5 text-xs text-gray-600">
                        <input type="checkbox" x-model="showId" class="rounded border-gray-300">
                        ID
                    </label>
                    <label class="inline-flex items-center gap-1.5 text-xs text-gray-600">
                        <input type="checkbox" x-model="showDescription" class="rounded border-gray-300">
                        Desc
                    </label>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button"
                        @click="copyRowsTable()"
                        data-testid="copy-warehouse-items-table"
                        title="Copy table for Excel"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span x-text="copyFeedback ? 'Copied!' : 'Copy rows'"></span>
                </button>
                <a href="{{ $exportUrl . (count($exportQuery) ? '?' . http_build_query($exportQuery) : '') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Export Excel
                </a>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto overflow-y-hidden border-b border-gray-100 bg-gray-50/80"
             x-ref="scrollTop"
             @scroll="syncHorizontalScroll('top')"
             data-testid="warehouse-items-scroll-top">
            <div class="h-3" :style="`width: ${tableScrollWidth}px`"></div>
        </div>
        <div class="overflow-x-auto overflow-y-auto max-h-[min(70vh,calc(100vh-14rem))]"
             x-ref="scrollBody"
             @scroll="syncHorizontalScroll('body')"
             data-testid="warehouse-items-scroll-body">
        <table x-ref="itemsTable" class="w-full min-w-[960px] text-sm">
            <thead class="sticky top-0 z-[1] border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-3 py-2.5 text-left font-medium" data-copy-col="id" x-show="showId">
                        <a href="{{ $sortLink('id') }}" class="inline-flex items-center gap-0.5 hover:text-gray-900">ID @if($sortColumn === 'id')<span class="text-blue-600">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    <th class="px-3 py-2.5 text-left font-medium" data-copy-col="image" x-show="showImage">Img</th>
                    <th class="px-3 py-2.5 text-left font-medium" data-copy-col="name" x-show="showName">
                        <a href="{{ $sortLink('name') }}" class="inline-flex items-center gap-0.5 hover:text-gray-900">Name @if($sortColumn === 'name')<span class="text-blue-600">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    <th class="px-3 py-2.5 text-left font-medium" data-copy-col="code">
                        <a href="{{ $sortLink('code') }}" class="inline-flex items-center gap-0.5 hover:text-gray-900">Code @if($sortColumn === 'code')<span class="text-blue-600">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    <th class="px-3 py-2.5 text-left font-medium" data-copy-col="description" x-show="showDescription">Desc</th>
                    <th class="px-3 py-2.5 text-right font-medium" data-copy-col="price">
                        <a href="{{ $sortLink('price') }}" class="inline-flex items-center justify-end gap-0.5 hover:text-gray-900">Price @if($sortColumn === 'price')<span class="text-blue-600">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    <th class="px-3 py-2.5 text-right font-medium" data-copy-col="qty">
                        <a href="{{ $sortLink('qty') }}" class="inline-flex items-center justify-end gap-0.5 hover:text-gray-900">Stock @if($sortColumn === 'qty')<span class="text-blue-600">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    @if($hasJubelio)
                        <th class="px-3 py-2.5 text-right font-medium" data-copy-col="jb_on_hand" title="Jubelio on hand">On hand</th>
                        <th class="px-3 py-2.5 text-right font-medium" data-copy-col="jb_on_order" title="Jubelio on order">On order</th>
                        <th class="px-3 py-2.5 text-right font-medium" data-copy-col="jb_reserved" title="Jubelio reserved">Rsv</th>
                        <th class="px-3 py-2.5 text-right font-medium" data-copy-col="jb_available" title="Jubelio available">Avail</th>
                    @endif
                    <th class="w-12 px-3 py-2.5 text-center font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($items as $item)
                    @php
                        $g = $item->group;
                        $normalName = $g->description ?? $item->name;
                        $onlineNm = $g->description2 ?? $g->description ?? $item->name;
                        $desc = $item->catalogDescription() ?: '-';
                        $qty = (float) ($item->pivot->quantity ?? 0);
                        $jubelio = ($jubelioStocks ?? [])[$item->id] ?? null;
                        $itemShowUrl = $item->showUrl();
                        $itemEditUrl = $item->editUrl();
                        $jubelioLinked = $jubelio && ($jubelio['linked'] ?? false);
                    @endphp
                    <tr class="cursor-pointer align-top hover:bg-gray-50" onclick="window.location='{{ $itemShowUrl }}'">
                        <td class="whitespace-nowrap px-3 py-2.5 font-mono text-xs" data-copy-col="id" x-show="showId">
                            <a href="{{ $itemShowUrl }}" onclick="event.stopPropagation()" class="text-blue-600 hover:underline">{{ $item->id }}</a>
                        </td>
                        <td class="px-3 py-2.5" data-copy-col="image" x-show="showImage">
                            <img src="{{ $item->image_url ?: '/images/default-item.png' }}" alt="{{ $item->name }}" onerror="this.src='/images/default-item.png'" class="h-8 w-8 rounded border border-gray-200 object-cover">
                        </td>
                        <td class="max-w-[220px] px-3 py-2.5" data-copy-col="name" x-show="showName">
                            <a href="{{ $itemShowUrl }}" onclick="event.stopPropagation()" class="block truncate font-medium text-gray-800 hover:text-blue-600" title="{{ $normalName }}">
                                <span x-show="!onlineName">{{ $normalName }}</span>
                                <span x-show="onlineName" x-cloak>{{ $onlineNm }}</span>
                            </a>
                            <div class="truncate font-mono text-[10px] text-gray-400" title="{{ $item->name }}">{{ $item->name }}</div>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5 font-mono text-xs" data-copy-col="code">
                            <a href="{{ $itemShowUrl }}" onclick="event.stopPropagation()" class="text-blue-600 hover:underline" title="{{ $item->code }}">{{ $item->code }}</a>
                        </td>
                        <td class="max-w-[200px] px-3 py-2.5 text-xs text-gray-500" data-copy-col="description" x-show="showDescription">
                            <div class="truncate" title="{{ $desc }}">{{ $desc }}</div>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-right text-xs font-semibold tabular-nums text-gray-700" data-copy-col="price" data-copy-value="{{ format_copy_number($item->price) }}">{{ $idr($item->price) }}</td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono text-xs font-bold tabular-nums {{ $qty > 0 ? 'text-emerald-600' : 'text-gray-400' }}" data-copy-col="qty" data-copy-value="{{ format_copy_number($qty) }}">{{ format_amount($qty, 0) }}</td>
                        @if($hasJubelio)
                            @if(! $jubelioLinked)
                                <td colspan="4" class="px-3 py-2.5 text-right" data-copy-col="jb_on_hand">
                                    <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700" title="Item is not linked to Jubelio">
                                        Not linked
                                    </span>
                                </td>
                            @else
                                <td class="whitespace-nowrap px-3 py-2.5 text-right text-xs" data-copy-col="jb_on_hand" @if($jubelio['on_hand'] !== null) data-copy-value="{{ format_copy_number($jubelio['on_hand']) }}" @endif>{!! $jubelioQtyCell($jubelio, 'on_hand') !!}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right text-xs" data-copy-col="jb_on_order" @if($jubelio['on_order'] !== null) data-copy-value="{{ format_copy_number($jubelio['on_order']) }}" @endif>{!! $jubelioQtyCell($jubelio, 'on_order') !!}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right text-xs" data-copy-col="jb_reserved" @if($jubelio['reserved'] !== null) data-copy-value="{{ format_copy_number($jubelio['reserved']) }}" @endif>{!! $jubelioQtyCell($jubelio, 'reserved') !!}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right text-xs" data-copy-col="jb_available" @if($jubelio['available'] !== null) data-copy-value="{{ format_copy_number($jubelio['available']) }}" @endif>{!! $jubelioQtyCell($jubelio, 'available', true) !!}</td>
                            @endif
                        @endif
                        <td class="px-3 py-2.5 text-center">
                            <a href="{{ $itemEditUrl }}" onclick="event.stopPropagation()" class="inline-flex h-7 w-7 items-center justify-center rounded text-gray-500 hover:bg-blue-50 hover:text-blue-600">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $hasJubelio ? 12 : 8 }}" class="px-4 py-12 text-center text-sm italic text-gray-500">No items found in this warehouse.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        @include('partials.pagination', ['paginator' => $items, 'label' => 'items'])
    </div>
</div>

@push('scripts')
<script>
function warehouseItemsPage(filtersStorageKey, columnsStorageKey, hasJubelio) {
    return {
        showImage: false,
        showId: true,
        showName: true,
        showDescription: true,
        onlineName: false,
        filtersOpen: true,
        filtersStorageKey: filtersStorageKey,
        columnsStorageKey: columnsStorageKey,
        hasJubelio: hasJubelio,
        copyFeedback: false,
        copyFeedbackTimer: null,
        tableScrollWidth: 0,
        _scrollSyncLock: false,
        init() {
            const saved = localStorage.getItem(this.filtersStorageKey);
            this.filtersOpen = saved === null ? true : saved === '1';
            this.$watch('filtersOpen', (value) => {
                localStorage.setItem(this.filtersStorageKey, value ? '1' : '0');
            });

            try {
                const columns = JSON.parse(localStorage.getItem(this.columnsStorageKey) || '{}');
                if (typeof columns.showId === 'boolean') {
                    this.showId = columns.showId;
                }
                if (typeof columns.showName === 'boolean') {
                    this.showName = columns.showName;
                }
                if (typeof columns.showDescription === 'boolean') {
                    this.showDescription = columns.showDescription;
                }
                if (typeof columns.showImage === 'boolean') {
                    this.showImage = columns.showImage;
                }
            } catch (e) {}

            this.$watch('showId', () => this.persistColumns());
            this.$watch('showName', () => this.persistColumns());
            this.$watch('showDescription', () => this.persistColumns());
            this.$watch('showImage', () => this.persistColumns());

            this.$nextTick(() => {
                this.refreshTableScrollWidth();
            });
            window.addEventListener('resize', () => this.refreshTableScrollWidth());
        },
        persistColumns() {
            localStorage.setItem(this.columnsStorageKey, JSON.stringify({
                showId: this.showId,
                showName: this.showName,
                showDescription: this.showDescription,
                showImage: this.showImage,
            }));
            this.$nextTick(() => this.refreshTableScrollWidth());
        },
        refreshTableScrollWidth() {
            const table = this.$refs.itemsTable;
            this.tableScrollWidth = table ? table.scrollWidth : 0;
        },
        syncHorizontalScroll(source) {
            if (this._scrollSyncLock) {
                return;
            }
            this._scrollSyncLock = true;
            const top = this.$refs.scrollTop;
            const body = this.$refs.scrollBody;
            if (top && body) {
                if (source === 'top') {
                    body.scrollLeft = top.scrollLeft;
                } else {
                    top.scrollLeft = body.scrollLeft;
                }
            }
            this._scrollSyncLock = false;
        },
        showCopyFeedback() {
            this.copyFeedback = true;
            clearTimeout(this.copyFeedbackTimer);
            this.copyFeedbackTimer = setTimeout(() => {
                this.copyFeedback = false;
            }, 2000);
        },
        isCopyColumnVisible(col) {
            if (col === 'image') {
                return this.showImage;
            }
            if (col === 'name') {
                return this.showName;
            }
            if (col === 'id') {
                return this.showId;
            }
            if (col === 'description') {
                return this.showDescription;
            }
            if (col.startsWith('jb_')) {
                return this.hasJubelio;
            }

            return true;
        },
        async copyRowsTable() {
            if (await ariaCopyTable(this.$refs.itemsTable, (col) => this.isCopyColumnVisible(col))) {
                this.showCopyFeedback();
            }
        },
    };
}
</script>
@endpush
@endsection

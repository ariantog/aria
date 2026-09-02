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
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4" x-data="warehouseItemsPage(@js($filtersStorageKey))">
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
            @if($jubelioSync ?? null)
                <p class="mt-1 text-xs text-gray-500">
                    Jubelio location:
                    <span class="font-medium text-gray-700">{{ $jubelioSync->jubelio_location_name }}</span>
                    <span class="text-gray-400">· on-hand stock at mapped location</span>
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

    @if(($jubelioFetchFailed ?? false) && ($jubelioSync ?? null))
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
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-4">
            <span class="text-[10px] font-bold uppercase text-gray-500">Display Options:</span>
            <button type="button" @click="showImage = !showImage"
                    :class="showImage ? 'border-blue-500/20 bg-blue-50 text-blue-600' : 'border-gray-200 text-gray-500'"
                    class="inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-medium">
                <span x-text="showImage ? 'Hide Image' : 'Show Image'"></span>
            </button>
            <div class="flex rounded-lg border border-gray-200 bg-gray-50 p-1">
                <button type="button" @click="onlineName = false" :class="!onlineName ? 'bg-gray-800 text-white' : 'text-gray-500'" class="rounded-md px-3 py-1 text-[10px] font-bold uppercase">Normal Name</button>
                <button type="button" @click="onlineName = true" :class="onlineName ? 'bg-gray-800 text-white' : 'text-gray-500'" class="rounded-md px-3 py-1 text-[10px] font-bold uppercase">Online Name</button>
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
        <div class="overflow-x-auto">
        <table x-ref="itemsTable" class="w-full min-w-[1100px] text-left text-xs">
            <thead class="border-b border-gray-200 bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="w-14 px-2 py-2.5 font-bold" data-copy-col="id">
                        <a href="{{ $sortLink('id') }}" class="inline-flex items-center gap-1 hover:text-gray-900">ID @if($sortColumn === 'id')<span class="text-blue-600">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    <th class="w-16 px-2 py-2.5 font-bold" data-copy-col="image" x-show="showImage">Image</th>
                    <th class="whitespace-nowrap px-2 py-2.5 font-bold" data-copy-col="name">
                        <a href="{{ $sortLink('name') }}" class="inline-flex items-center gap-1 hover:text-gray-900">Item Name @if($sortColumn === 'name')<span class="text-blue-600">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    <th class="min-w-[7rem] px-2 py-2.5 font-bold" data-copy-col="code">
                        <a href="{{ $sortLink('code') }}" class="inline-flex items-center gap-1 hover:text-gray-900">Code @if($sortColumn === 'code')<span class="text-blue-600">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    <th class="min-w-[8rem] max-w-[14rem] px-2 py-2.5 font-bold" data-copy-col="description">Description</th>
                    <th class="whitespace-nowrap px-2 py-2.5 text-right font-bold" data-copy-col="price">
                        <a href="{{ $sortLink('price') }}" class="inline-flex items-center gap-1 hover:text-gray-900">Price @if($sortColumn === 'price')<span class="text-blue-600">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    <th class="whitespace-nowrap px-2 py-2.5 text-right font-bold" data-copy-col="qty">
                        <a href="{{ $sortLink('qty') }}" class="inline-flex items-center gap-1 hover:text-gray-900">Stock @if($sortColumn === 'qty')<span class="text-blue-600">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</a>
                    </th>
                    @if($jubelioSync ?? null)
                        <th class="whitespace-nowrap px-2 py-2.5 text-right font-bold" data-copy-col="jubelio">Jubelio</th>
                    @endif
                    <th class="w-14 px-2 py-2.5 text-center font-bold"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($items as $item)
                    @php
                        $g = $item->group;
                        $normalName = $g->description ?? $item->name;
                        $onlineNm = $g->description2 ?? $g->description ?? $item->name;
                        $desc = $item->description ?: ($g->description ?? '-');
                        $qty = (float) ($item->pivot->quantity ?? 0);
                        $jubelio = ($jubelioStocks ?? [])[$item->id] ?? null;
                        $itemShowUrl = $item->showUrl();
                        $itemEditUrl = $item->editUrl();
                    @endphp
                    <tr class="cursor-pointer align-top hover:bg-gray-50" onclick="window.location='{{ $itemShowUrl }}'">
                        <td class="px-2 py-2 font-mono" data-copy-col="id">
                            <a href="{{ $itemShowUrl }}" onclick="event.stopPropagation()" class="text-blue-600 hover:underline">#{{ $item->id }}</a>
                        </td>
                        <td class="px-2 py-2" data-copy-col="image" x-show="showImage">
                            <img src="{{ $item->image_url ?: '/images/default-item.png' }}" alt="{{ $item->name }}" onerror="this.src='/images/default-item.png'" class="h-10 w-10 rounded-md border border-gray-200 object-cover">
                        </td>
                        <td class="min-w-[10rem] max-w-[16rem] px-2 py-2" data-copy-col="name">
                            <a href="{{ $itemShowUrl }}" onclick="event.stopPropagation()" class="block truncate font-medium text-gray-800 hover:text-blue-600" title="{{ $onlineName ?? $normalName }}">
                                <span x-show="!onlineName">{{ $normalName }}</span>
                                <span x-show="onlineName" x-cloak>{{ $onlineNm }}</span>
                            </a>
                            <div class="truncate font-mono text-[10px] text-gray-400">{{ $item->name }}</div>
                        </td>
                        <td class="truncate px-2 py-2 font-mono" data-copy-col="code">
                            <a href="{{ $itemShowUrl }}" onclick="event.stopPropagation()" class="text-blue-600 hover:underline" title="{{ $item->code }}">{{ $item->code }}</a>
                        </td>
                        <td class="min-w-[8rem] max-w-[14rem] break-words px-2 py-2 text-gray-500 whitespace-pre-line" data-copy-col="description">{{ $desc }}</td>
                        <td class="whitespace-nowrap px-2 py-2 text-right font-semibold text-gray-700" data-copy-col="price">{{ $idr($item->price) }}</td>
                        <td class="whitespace-nowrap px-2 py-2 text-right font-mono font-bold {{ $qty > 0 ? 'text-emerald-600' : 'text-gray-400' }}" data-copy-col="qty">{{ format_amount($qty, 0) }}</td>
                        @if($jubelioSync ?? null)
                            <td class="whitespace-nowrap px-2 py-2 text-right" data-copy-col="jubelio">
                                @if(! $jubelio || ! ($jubelio['linked'] ?? false))
                                    <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700" title="Item is not linked to Jubelio">
                                        Not linked
                                    </span>
                                @elseif($jubelio['on_hand'] !== null)
                                    <span class="font-mono font-bold {{ ($jubelio['mismatch'] ?? false) ? 'text-red-600' : 'text-blue-600' }}" title="Jubelio on-hand at {{ $jubelioSync->jubelio_location_name }}">
                                        {{ format_amount($jubelio['on_hand'], 0) }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        @endif
                        <td class="px-2 py-2 text-center">
                            <a href="{{ $itemEditUrl }}" onclick="event.stopPropagation()" class="inline-flex h-8 w-8 items-center justify-center rounded text-gray-500 hover:bg-blue-50 hover:text-blue-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ ($jubelioSync ?? null) ? 9 : 8 }}" class="px-4 py-12 text-center text-sm italic text-gray-500">No items found in this warehouse.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        @include('partials.pagination', ['paginator' => $items, 'label' => 'items'])
    </div>
</div>

@push('scripts')
<script>
function warehouseItemsPage(filtersStorageKey) {
    return {
        showImage: false,
        onlineName: false,
        filtersOpen: true,
        filtersStorageKey: filtersStorageKey,
        copyFeedback: false,
        copyFeedbackTimer: null,
        init() {
            const saved = localStorage.getItem(this.filtersStorageKey);
            this.filtersOpen = saved === null ? true : saved === '1';
            this.$watch('filtersOpen', (value) => {
                localStorage.setItem(this.filtersStorageKey, value ? '1' : '0');
            });
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

            return true;
        },
        cellCopyValue(cell) {
            if (cell.dataset.copyCol === 'image') {
                const img = cell.querySelector('img');
                if (img) {
                    return (img.getAttribute('alt') || img.getAttribute('src') || '').trim();
                }
            }

            return cell.innerText.replace(/\s+/g, ' ').trim();
        },
        tableNodeToTsv(table) {
            const rows = [];

            table.querySelectorAll('thead tr, tbody tr').forEach((row) => {
                const values = [];

                row.querySelectorAll('[data-copy-col]').forEach((cell) => {
                    if (!this.isCopyColumnVisible(cell.dataset.copyCol)) {
                        return;
                    }

                    values.push(this.cellCopyValue(cell));
                });

                if (values.length) {
                    rows.push(values.join('\t'));
                }
            });

            return rows.join('\n');
        },
        async copyRowsTable() {
            const table = this.$refs.itemsTable;
            if (!table) {
                return;
            }

            const clone = table.cloneNode(true);
            clone.querySelectorAll('[data-copy-col]').forEach((cell) => {
                if (!this.isCopyColumnVisible(cell.dataset.copyCol)) {
                    cell.remove();
                }
            });

            const plain = this.tableNodeToTsv(clone);
            const html = clone.outerHTML;

            try {
                if (window.ClipboardItem && navigator.clipboard?.write) {
                    await navigator.clipboard.write([
                        new ClipboardItem({
                            'text/plain': new Blob([plain], { type: 'text/plain' }),
                            'text/html': new Blob([html], { type: 'text/html' }),
                        }),
                    ]);
                } else {
                    await navigator.clipboard.writeText(plain);
                }

                this.showCopyFeedback();
            } catch (e) {
                try {
                    await navigator.clipboard.writeText(plain);
                    this.showCopyFeedback();
                } catch (fallbackError) {
                    console.error('Failed to copy warehouse items table', fallbackError);
                }
            }
        },
    };
}
</script>
@endpush
@endsection

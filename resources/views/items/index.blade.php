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
@endphp

<div class="flex flex-col gap-3 p-3 sm:p-4" x-data="itemsIndex()" x-init="init()">
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

    {{-- Filters --}}
    <form @submit.prevent="applyFilters()" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-white p-3">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">SKU / Code</label>
            <input type="text" x-model="filters.code" placeholder="Code…" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Name</label>
            <input type="text" x-model="filters.name" placeholder="Name…" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Alias</label>
            <input type="text" x-model="filters.alias" placeholder="Alias…" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Description</label>
            <input type="text" x-model="filters.desc" placeholder="Description…" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
        </div>
        @unless($isAsset)
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Type</label>
            <select x-model="filters.item_type" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                <option value="">All</option>
                @foreach($typeTags as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Size</label>
            <select x-model="filters.size" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                <option value="">All</option>
                @foreach($sizeTags as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Warna</label>
            <select x-model="filters.warna" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                <option value="">All</option>
                @foreach($warnaTags as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Jahit</label>
            <select x-model="filters.jahit" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                <option value="">All</option>
                @foreach($jahitTags as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
            </select>
        </div>
        @endunless
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
            <button type="button" @click="resetFilters()" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</button>
        </div>
    </form>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div id="items-table"></div>
    </div>
</div>

@push('scripts')
<script>
const _CSRF = '{{ csrf_token() }}';
const _IS_ASSET = @json($isAsset);
const _BASE_URL = @json($baseUrl);
const _CAN = @json($can);

function itemsIndex() {
    return {
        table: null,
        showImage: true,
        filters: {
            code: '{{ $filters['code'] ?? '' }}',
            name: '{{ $filters['name'] ?? '' }}',
            alias: '{{ $filters['alias'] ?? '' }}',
            desc: '{{ $filters['desc'] ?? '' }}',
            item_type: '{{ $filters['item_type'] ?? '' }}',
            size: '{{ $filters['size'] ?? '' }}',
            warna: '{{ $filters['warna'] ?? '' }}',
            jahit: '{{ $filters['jahit'] ?? '' }}',
        },

        init() {
            this.$nextTick(() => this.buildTable());
            this.$watch('showImage', () => {
                if (this.table) {
                    const col = this.table.getColumn('image_url');
                    if (col) { this.showImage ? col.show() : col.hide(); }
                }
            });
        },

        buildTable() {
            const self = this;
            const currencyFmt = (v) => 'Rp ' + Number(v || 0).toLocaleString('id-ID');

            const columns = [
                {
                    title: 'Image', field: 'image_url', headerSort: false, width: 80, frozen: true,
                    formatter(cell) {
                        const url = cell.getValue();
                        if (url) return `<div class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-md border border-gray-200"><img src="${url}" class="h-full w-full object-cover"></div>`;
                        return `<div class="flex h-14 w-14 items-center justify-center rounded-md border border-gray-200 bg-gray-50 text-gray-300"><svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg></div>`;
                    }
                },
                {
                    title: 'Barcode', field: 'id', width: 90,
                    formatter(cell) {
                        const id = cell.getValue();
                        return `<a href="${_BASE_URL}/${id}" class="font-medium text-blue-600 hover:underline">${id}</a>`;
                    }
                },
            ];

            if (_IS_ASSET) {
                columns.push(
                    { title: 'Name', field: 'alias', headerSort: false, formatter: (c) => `<span class="italic text-gray-700">${c.getValue() || '-'}</span>` },
                    { title: 'SKU', field: 'code', width: 120, formatter: (c) => `<span class="text-gray-500">${c.getValue() || '-'}</span>` },
                );
            } else {
                columns.push(
                    { title: 'SKU', field: 'code', width: 130, sorter: 'string', formatter: (c) => `<span class="text-gray-500">${c.getValue() || '-'}</span>` },
                    { title: 'Kode Produksi', field: 'pcode', width: 130, sorter: 'string', formatter: (c) => `<span class="text-gray-500">${c.getValue() || '-'}</span>` },
                    { title: 'Alias', field: 'alias', headerSort: false, formatter: (c) => `<span class="italic text-gray-700">${c.getValue() || '-'}</span>` },
                );
            }

            columns.push(
                { title: 'Description', field: 'description', headerSort: false, widthGrow: 2, formatter: (c) => `<span class="text-gray-700 leading-tight">${c.getValue() || '-'}</span>` },
                { title: 'Price', field: 'price', sorter: 'number', hozAlign: 'right', width: 130, formatter: (c) => `<span class="font-bold tabular-nums">${currencyFmt(c.getValue())}</span>` },
                { title: 'NB', field: 'description2', headerSort: false, minWidth: 150, formatter: (c) => `<span class="text-gray-500 leading-tight">${c.getValue() || '--'}</span>` },
                { title: 'Qty', field: 'qty', sorter: 'number', hozAlign: 'right', width: 70, formatter: (c) => `<span class="font-bold text-emerald-600 tabular-nums">${Number(c.getValue() || 0).toLocaleString('id-ID')}</span>` },
                {
                    title: 'Jubelio', field: 'jubelio_item_id', headerSort: false, width: 90,
                    formatter(cell) {
                        const v = cell.getValue();
                        if (v) return `<span class="inline-flex rounded-full border border-blue-200 bg-blue-100 px-2 py-0.5 text-xs text-blue-700">${v}</span>`;
                        return `<span class="text-xs text-gray-400">no sync</span>`;
                    }
                },
                {
                    title: '', field: 'id', headerSort: false, hozAlign: 'center', width: 50, frozen: true,
                    formatter(cell) {
                        const id = cell.getValue();
                        return `<a href="${_BASE_URL}/${id}/edit" class="inline-flex h-8 w-8 items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-blue-600"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></a>`;
                    }
                },
            );

            this.table = new Tabulator('#items-table', {
                ajaxURL: _BASE_URL,
                ajaxConfig: {
                    method: 'GET',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': _CSRF },
                },
                ajaxParams: () => ({ ...self.getCleanFilters(), table: 1 }),
                ajaxResponse(url, params, response) {
                    return { data: response.data || [], last_page: response.last_page || 1, last_row: response.total || 0 };
                },
                pagination: true,
                paginationMode: 'remote',
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100],
                sortMode: 'remote',
                ajaxSorting: true,
                initialSort: [{ column: 'id', dir: 'desc' }],
                layout: 'fitDataStretch',
                height: 'calc(100vh - 320px)',
                minHeight: 300,
                placeholder: '<div style="padding:32px;text-align:center;color:#9ca3af;font-size:13px;">No items found matching your filters.</div>',
                columns,
            });
        },

        applyFilters() {
            if (this.table) this.table.setData(_BASE_URL, { ...this.getCleanFilters(), table: 1 });
        },

        resetFilters() {
            this.filters = { code:'', name:'', alias:'', desc:'', item_type:'', size:'', warna:'', jahit:'' };
            this.applyFilters();
        },

        getCleanFilters() {
            const p = {};
            Object.entries(this.filters).forEach(([k, v]) => { if (v !== '' && v !== null) p[k] = v; });
            return p;
        }
    };
}
</script>
@endpush
@endsection

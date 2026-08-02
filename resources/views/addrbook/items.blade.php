@extends('layouts.app')

@section('title', 'Items: ' . $addrbook->name)

@section('content')
@php
$baseUrl = '/' . $addrbook->type_slug . '/' . $addrbook->id . '/items';
$breadcrumbs = [
    ['title' => 'Address Book', 'href' => route('addrbook.index')],
    ['title' => $addrbook->name, 'href' => '/' . $addrbook->type_slug . '/' . $addrbook->id],
    ['title' => 'Items', 'href' => $baseUrl],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4" x-data="addrbookItems()" x-init="init()">
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
        </div>
    </div>

    @include('addrbook.partials.tabs', ['active' => 'items'])

    {{-- Filters --}}
    <div class="space-y-4 rounded-xl border border-gray-200 bg-white p-4">
        <form @submit.prevent="applyFilters()" class="flex flex-wrap items-end gap-2 border-b border-gray-100 pb-4">
            <div class="flex flex-1 min-w-[220px] flex-col gap-1">
                <label class="text-xs font-medium uppercase text-gray-500">Item Name / Code</label>
                <div class="relative">
                    <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" x-model="filters.name" placeholder="Search…" class="w-full rounded-md border border-gray-300 py-1.5 pl-9 pr-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium uppercase text-gray-500">Sort By</label>
                <select x-model="filters.sort" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="qtydesc">Quantity (High to Low)</option>
                    <option value="qtyasc">Quantity (Low to High)</option>
                    <option value="namedesc">Name (Z-A)</option>
                    <option value="nameasc">Name (A-Z)</option>
                    <option value="codedesc">Code (Z-A)</option>
                    <option value="codeasc">Code (A-Z)</option>
                </select>
            </div>
            <div class="flex items-center gap-2 py-2">
                <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-600">
                    <input type="checkbox" x-model="show0" @change="applyFilters()" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    Show Zero Stock
                </label>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Apply Filter</button>
                <button type="button" @click="resetFilters()" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</button>
            </div>
        </form>

        <div class="flex flex-wrap items-center gap-4">
            <span class="text-[10px] font-bold uppercase text-gray-500">Display Options:</span>
            <button type="button" @click="showImage = !showImage; rebuildColumns()"
                    :class="showImage ? 'border-blue-500/20 bg-blue-50 text-blue-600' : 'border-gray-200 text-gray-500'"
                    class="inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-medium">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span x-text="showImage ? 'Hide Image' : 'Show Image'"></span>
            </button>
            <div class="flex rounded-lg border border-gray-200 bg-gray-50 p-1">
                <button type="button" @click="onlineName = false; rebuildColumns()" :class="!onlineName ? 'bg-gray-800 text-white' : 'text-gray-500'" class="rounded-md px-3 py-1 text-[10px] font-bold uppercase">Normal Name</button>
                <button type="button" @click="onlineName = true; rebuildColumns()" :class="onlineName ? 'bg-gray-800 text-white' : 'text-gray-500'" class="rounded-md px-3 py-1 text-[10px] font-bold uppercase">Online Name</button>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div id="addrbook-items-table"></div>
    </div>
</div>

@push('scripts')
<script>
const _CSRF = '{{ csrf_token() }}';
const _BASE_URL = @json($baseUrl);

function fmtIDR(v) { return 'IDR ' + Number(v||0).toLocaleString('id-ID'); }
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

function addrbookItems() {
    return {
        table: null,
        showImage: false,
        onlineName: false,
        show0: @json(($filters['show0'] ?? '') === 'show'),
        filters: {
            name: @json($filters['name'] ?? ''),
            sort: @json($filters['sort'] ?? 'qtydesc'),
        },

        init() { this.$nextTick(() => this.buildTable()); },

        columns() {
            const self = this;
            const cols = [
                { title: 'ID', field: 'id', headerSort: false, width: 70,
                  formatter(cell) { return `<span class="font-mono text-xs text-gray-400">#${cell.getValue()}</span>`; } },
            ];
            if (this.showImage) {
                cols.push({ title: 'Image', field: 'image_url', headerSort: false, hozAlign: 'center', width: 80,
                    formatter(cell) {
                        const url = cell.getValue() || '/images/default-item.png';
                        return `<div class="inline-block rounded-lg border border-gray-200 bg-gray-50 p-1"><img src="${escapeHtml(url)}" class="h-10 w-10 rounded-md object-cover" onerror="this.src='/images/default-item.png'"></div>`;
                    } });
            }
            cols.push(
                { title: self.onlineName ? 'Online Product Name' : 'Item Name', field: 'name', headerSort: false, widthGrow: 2, minWidth: 200,
                  formatter(cell) {
                      const r = cell.getRow().getData();
                      const g = r.group || {};
                      const display = self.onlineName ? (g.description2 || g.description || r.name) : (g.description || r.name);
                      return `<div class="flex flex-col"><span class="text-sm font-medium text-gray-800">${escapeHtml(display)}</span><span class="font-mono text-[10px] text-gray-400">${escapeHtml(r.name)}</span></div>`;
                  } },
                { title: 'Code', field: 'code', headerSort: false, width: 120,
                  formatter(cell) { return `<span class="font-mono text-xs text-blue-600">${escapeHtml(cell.getValue()||'')}</span>`; } },
                { title: 'Description', field: 'description', headerSort: false, widthGrow: 2, minWidth: 180,
                  formatter(cell) {
                      const r = cell.getRow().getData();
                      const d = r.description || (r.group && r.group.description) || '-';
                      return `<p class="whitespace-normal text-xs text-gray-500">${escapeHtml(d)}</p>`;
                  } },
                { title: 'Price', field: 'price', headerSort: false, hozAlign: 'right', width: 130,
                  formatter(cell) { return `<span class="text-sm font-semibold text-gray-700">${fmtIDR(cell.getValue())}</span>`; } },
                { title: 'Stock', field: 'pivot', headerSort: false, hozAlign: 'right', width: 100,
                  formatter(cell) {
                      const p = cell.getValue() || {};
                      const q = Number(p.quantity || 0);
                      return `<span class="font-mono text-sm font-bold ${q > 0 ? 'text-emerald-600' : 'text-gray-400'}">${q.toLocaleString()}</span>`;
                  } },
                { title: 'Action', field: 'id', headerSort: false, hozAlign: 'center', width: 80,
                  formatter(cell) {
                      return `<a href="/items/${cell.getValue()}/edit" class="inline-flex h-8 w-8 items-center justify-center rounded text-gray-500 hover:bg-blue-50 hover:text-blue-600"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></a>`;
                  } },
            );
            return cols;
        },

        buildTable() {
            const self = this;
            this.table = new Tabulator('#addrbook-items-table', {
                ajaxURL: _BASE_URL,
                ajaxConfig: { method: 'GET', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': _CSRF } },
                ajaxParams: () => self.getCleanFilters(),
                ajaxResponse(url, params, response) {
                    return { data: response.data || [], last_page: response.last_page || 1, last_row: response.total || 0 };
                },
                pagination: true,
                paginationMode: 'remote',
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100],
                layout: 'fitDataStretch',
                height: 'calc(100vh - 380px)',
                minHeight: 300,
                placeholder: '<div style="padding:32px;text-align:center;color:#9ca3af;font-size:13px;">No items found in this warehouse.</div>',
                columns: self.columns(),
            });
        },

        rebuildColumns() { if (this.table) this.table.setColumns(this.columns()); },

        applyFilters() { if (this.table) this.table.setData(_BASE_URL, this.getCleanFilters()); },
        resetFilters() { this.filters = { name:'', sort:'qtydesc' }; this.show0 = false; this.applyFilters(); },
        getCleanFilters() {
            const p = {};
            if (this.filters.name) p.name = this.filters.name;
            if (this.filters.sort) p.sort = this.filters.sort;
            if (this.show0) p.show0 = 'show';
            return p;
        }
    };
}
</script>
@endpush

@endsection

@extends('layouts.app')

@section('title', $current_type ? ucfirst($current_type) . ' — Address Book' : 'Address Book')

@section('content')
@php
$baseUrl = $current_type ? '/' . $current_type : route('addrbook.index');
$breadcrumbs = [
    ['title' => 'Address Book', 'href' => route('addrbook.index')],
    ['title' => 'List', 'href' => $baseUrl],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4" x-data="addrbookIndex()" x-init="init()">
    {{-- Header --}}
    <div class="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Address Book</h2>
            <p class="mt-0.5 text-sm text-gray-500">Manage your customers and contacts efficiently.</p>
        </div>
        <div class="flex items-center gap-2">
            @if($can['create'])
            <a href="{{ $current_type ? '/' . $current_type . '/create' : route('addrbook.create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add New
            </a>
            @endif
        </div>
    </div>

    {{-- Filters --}}
    <form @submit.prevent="applyFilters()" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-white p-3">
        <div class="flex flex-1 min-w-[240px] flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Search Name / Contact / ID / Phone</label>
            <div class="relative">
                <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" x-model="filters.search" placeholder="Search…"
                       class="w-full rounded-md border border-gray-300 py-1.5 pl-9 pr-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Status</label>
            <select x-model="filters.trashed" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <option value="">Active Only</option>
                <option value="with">With Deleted</option>
                <option value="only">Only Deleted</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
            <button type="button" @click="resetFilters()" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</button>
        </div>
    </form>

    {{-- Table container --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div id="addrbook-table"></div>
    </div>
</div>

@push('scripts')
<script>
const _CSRF = '{{ csrf_token() }}';
const _CAN  = @json($can);
const _PPN_RATE = {{ $ppn_rate }};
const _BASE_URL = @json($baseUrl);

function addrbookIndex() {
    return {
        table: null,
        filters: {
            search: @json($filters['search'] ?? ''),
            trashed: @json($filters['trashed'] ?? ''),
        },

        init() {
            this.$nextTick(() => this.buildTable());
        },

        buildTable() {
            const self = this;
            this.table = new Tabulator('#addrbook-table', {
                ajaxURL: _BASE_URL,
                ajaxConfig: {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': _CSRF,
                    },
                },
                ajaxParams: () => self.getCleanFilters(),
                ajaxResponse(url, params, response) {
                    return {
                        data: response.data || [],
                        last_page: response.last_page || 1,
                        last_row: response.total || 0,
                    };
                },
                pagination: true,
                paginationMode: 'remote',
                paginationSize: 50,
                paginationSizeSelector: [10, 25, 50, 100],
                layout: 'fitDataStretch',
                responsiveLayout: false,
                height: 'calc(100vh - 300px)',
                minHeight: 300,
                placeholder: '<div style="padding:32px;text-align:center;color:#9ca3af;font-size:13px;">No address book entries found.</div>',
                columns: [
                    {
                        title: 'Name / ID', field: 'name', headerSort: false, widthGrow: 2, minWidth: 220,
                        formatter(cell) {
                            const r = cell.getRow().getData();
                            const deleted = r.deleted_at
                                ? `<span class="ml-1 inline-flex items-center rounded bg-rose-100 px-1 text-[10px] font-bold uppercase text-rose-700">Deleted</span>` : '';
                            const cp = r.contact_person
                                ? `<div class="mt-1 flex items-center gap-1 text-xs text-gray-500"><svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>${escapeHtml(r.contact_person)}</div>` : '';
                            return `<div class="flex flex-col py-1">
                                <a href="/${r.type_slug}/${r.id}" class="font-semibold text-blue-600 hover:text-blue-800">${escapeHtml(r.name || '')}</a>
                                <div class="flex items-center gap-2 text-xs text-gray-500">ID: ${r.id}${deleted}</div>
                                ${cp}
                            </div>`;
                        }
                    },
                    {
                        title: 'Contact Info', field: 'phone', headerSort: false, widthGrow: 2, minWidth: 200,
                        formatter(cell) {
                            const r = cell.getRow().getData();
                            let out = '<div class="flex flex-col gap-1 text-xs text-gray-500">';
                            if (r.phone) out += `<div class="flex items-center gap-2"><svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>${escapeHtml(r.phone)}</div>`;
                            if (r.email) out += `<div class="flex items-center gap-2"><svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>${escapeHtml(r.email)}</div>`;
                            if (r.address) out += `<div class="flex max-w-xs items-center gap-2 truncate" title="${escapeHtml(r.address)}"><svg class="h-3 w-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg><span class="truncate">${escapeHtml(r.address)}</span></div>`;
                            out += '</div>';
                            return out;
                        }
                    },
                    {
                        title: 'Connectivity', field: 'is_online', headerSort: false, hozAlign: 'right', width: 140,
                        formatter(cell) {
                            const r = cell.getRow().getData();
                            const online = r.is_online
                                ? `<span class="h-2 w-2 rounded-full bg-emerald-500"></span><span class="font-medium text-gray-700">Online</span>`
                                : `<span class="h-2 w-2 rounded-full bg-gray-300"></span><span class="font-medium text-gray-700">Offline</span>`;
                            const ppn = r.ppn
                                ? `<span class="rounded bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700">PPN ${_PPN_RATE}%</span>` : '';
                            return `<div class="flex flex-col items-end gap-1"><div class="flex items-center gap-2">${online}</div>${ppn}</div>`;
                        }
                    },
                    {
                        title: 'Balance', field: 'stat', headerSort: false, hozAlign: 'right', width: 140,
                        formatter(cell) {
                            const r = cell.getRow().getData();
                            let bal = (r.stat && r.stat.balance) ? Number(r.stat.balance) : 0;
                            if (_CAN.bank_hidden_balance && r.type_slug === 'bank') bal = 0;
                            return `<span class="font-medium text-gray-900">IDR ${bal.toLocaleString('id-ID')}</span>`;
                        }
                    },
                    {
                        title: 'Actions', field: 'id', headerSort: false, hozAlign: 'right', width: 110,
                        formatter(cell) {
                            const r = cell.getRow().getData();
                            let out = '<div class="flex justify-end gap-1">';
                            if (_CAN.edit) out += `<a href="/${r.type_slug}/${r.id}/edit" class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:bg-blue-50 hover:text-blue-500"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></a>`;
                            if (_CAN.delete) out += `<button onclick="deleteAddrbook(${r.id})" class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:bg-rose-50 hover:text-rose-500"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>`;
                            out += '</div>';
                            return out;
                        }
                    },
                ],
            });
        },

        applyFilters() {
            if (this.table) this.table.setData(_BASE_URL, this.getCleanFilters());
        },

        resetFilters() {
            this.filters = { search: '', trashed: '' };
            this.applyFilters();
        },

        getCleanFilters() {
            const p = {};
            Object.entries(this.filters).forEach(([k, v]) => { if (v !== '' && v !== null) p[k] = v; });
            return p;
        }
    };
}

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

async function deleteAddrbook(id) {
    if (!confirm('Apakah Anda yakin ingin menghapus data ini?')) return;
    const res = await fetch(`/addrbook/${id}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-TOKEN': _CSRF,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: '_method=DELETE',
    });
    if (res.redirected) { window.location.href = res.url; }
    else { window.location.reload(); }
}
</script>
@endpush

@endsection

@extends('layouts.app')

@section('title', 'Transactions: ' . $addrbook->name)

@section('content')
@php
$baseUrl = '/' . $addrbook->type_slug . '/' . $addrbook->id . '/transactions';
$breadcrumbs = [
    ['title' => 'Address Book', 'href' => route('addrbook.index')],
    ['title' => $addrbook->name, 'href' => '/' . $addrbook->type_slug . '/' . $addrbook->id],
    ['title' => 'Transactions', 'href' => $baseUrl],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4" x-data="addrbookTransactions()" x-init="init()">
    {{-- Header --}}
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
            <div class="mb-1 flex items-center gap-2">
                <a href="/{{ $addrbook->type_slug }}/{{ $addrbook->id }}" class="text-gray-400 hover:text-gray-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <span class="font-mono text-sm text-gray-400">#{{ $addrbook->id }}</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Transaction History</h1>
            <p class="text-sm text-gray-500">Full history for <span class="text-blue-600">{{ $addrbook->name }}</span></p>
        </div>
    </div>

    @include('addrbook.partials.tabs', ['active' => 'transactions'])

    {{-- Filters --}}
    <form @submit.prevent="applyFilters()" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-white p-3">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">From Date</label>
            <input type="date" x-model="filters.from" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">To Date</label>
            <input type="date" x-model="filters.to" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Type</label>
            <select x-model="filters.type" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <option value="">All Types</option>
                @foreach($transactionTypes as $t)
                    <option value="{{ $t['id'] }}">{{ $t['name'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Order By</label>
            <select x-model="filters.order_date" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <option value="date">Transaction Date</option>
                <option value="created_at">Created At</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Search</button>
            <button type="button" @click="resetFilters()" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</button>
        </div>
    </form>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div id="addrbook-transactions-table"></div>
    </div>
</div>

@push('scripts')
<script>
const _CSRF = '{{ csrf_token() }}';
const _CAN  = @json($can);
const _BASE_URL = @json($baseUrl);
const _ADDRBOOK_ID = {{ $addrbook->id }};
const _TX_TYPES = @json(collect($transactionTypes)->keyBy('id')->map->name);

function txTypeMeta(t) {
    const colors = {
        1: 'text-emerald-700 bg-emerald-50',
        2: 'text-blue-700 bg-blue-50',
        3: 'text-amber-700 bg-amber-50',
        15: 'text-purple-700 bg-purple-50',
        16: 'text-indigo-700 bg-indigo-50',
        17: 'text-rose-700 bg-rose-50',
    };
    return { label: _TX_TYPES[t] || 'Other', cls: colors[t] || 'text-gray-700 bg-gray-50' };
}

function fmtDate(d) {
    if (!d) return '-';
    const dt = new Date(d);
    return dt.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function addrbookTransactions() {
    return {
        table: null,
        filters: {
            from: @json($filters['from'] ?? ''),
            to: @json($filters['to'] ?? ''),
            type: @json($filters['type'] ?? ''),
            order_date: @json($filters['order_date'] ?? 'date'),
        },

        init() { this.$nextTick(() => this.buildTable()); },

        buildTable() {
            const self = this;
            this.table = new Tabulator('#addrbook-transactions-table', {
                ajaxURL: _BASE_URL,
                ajaxConfig: {
                    method: 'GET',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': _CSRF },
                },
                ajaxParams: () => self.getCleanFilters(),
                ajaxResponse(url, params, response) {
                    return { data: response.data || [], last_page: response.last_page || 1, last_row: response.total || 0 };
                },
                pagination: true,
                paginationMode: 'remote',
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100],
                layout: 'fitDataStretch',
                height: 'calc(100vh - 340px)',
                minHeight: 300,
                placeholder: '<div style="padding:32px;text-align:center;color:#9ca3af;font-size:13px;">No transactions found for this contact.</div>',
                columns: [
                    {
                        title: 'Date', field: 'date', headerSort: false, width: 130,
                        formatter(cell) {
                            const r = cell.getRow().getData();
                            const at = r.created_at ? new Date(r.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '';
                            return `<div class="flex flex-col"><span class="text-sm font-medium text-gray-800">${fmtDate(r.date)}</span><span class="text-[10px] text-gray-400">At: ${at}</span></div>`;
                        }
                    },
                    {
                        title: 'Type', field: 'type', headerSort: false, width: 110,
                        formatter(cell) {
                            const m = txTypeMeta(cell.getValue());
                            return `<span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${m.cls}">${m.label}</span>`;
                        }
                    },
                    {
                        title: 'Invoice', field: 'invoice_number', headerSort: false, width: 140,
                        formatter(cell) {
                            const id = cell.getRow().getData().id;
                            return `<a href="/transactions/${id}" class="font-mono text-sm text-blue-600 hover:underline break-all">${cell.getValue() || '-'}</a>`;
                        }
                    },
                    {
                        title: 'Items', field: 'total_items', headerSort: false, hozAlign: 'center', width: 70,
                        formatter(cell) { return `<span class="font-mono text-xs text-gray-500">${Number(cell.getValue()||0).toLocaleString()}</span>`; }
                    },
                    {
                        title: 'Sender', field: 'sender', headerSort: false, width: 150,
                        formatter(cell) {
                            const s = cell.getValue();
                            if (!s) return '<span class="text-gray-400">-</span>';
                            const bold = s.id === _ADDRBOOK_ID ? 'font-bold text-blue-600' : 'text-gray-600';
                            return `<a href="/${s.type_slug}/${s.id}" class="text-[11px] font-medium leading-tight ${bold} hover:underline">${escapeHtml(s.name)}</a>`;
                        }
                    },
                    {
                        title: 'Sender Bal', field: 'sender_balance', headerSort: false, hozAlign: 'right', width: 110,
                        formatter(cell) {
                            const r = cell.getRow().getData();
                            let v = Number(cell.getValue()||0);
                            if (_CAN.bank_hidden_balance && r.sender?.type_slug === 'bank') v = 0;
                            return `<span class="font-mono text-xs font-bold ${balColor(v)}">${v.toLocaleString('id-ID')}</span>`;
                        }
                    },
                    {
                        title: 'Receiver', field: 'receiver', headerSort: false, width: 150,
                        formatter(cell) {
                            const rc = cell.getValue();
                            if (!rc) return '<span class="text-gray-400">-</span>';
                            const bold = rc.id === _ADDRBOOK_ID ? 'font-bold text-blue-600' : 'text-gray-600';
                            return `<a href="/${rc.type_slug}/${rc.id}" class="text-[11px] font-medium leading-tight ${bold} hover:underline">${escapeHtml(rc.name)}</a>`;
                        }
                    },
                    {
                        title: 'Receiver Bal', field: 'receiver_balance', headerSort: false, hozAlign: 'right', width: 110,
                        formatter(cell) {
                            const r = cell.getRow().getData();
                            let v = Number(cell.getValue()||0);
                            if (_CAN.bank_hidden_balance && r.receiver?.type_slug === 'bank') v = 0;
                            return `<span class="font-mono text-xs font-bold ${balColor(v)}">${v.toLocaleString('id-ID')}</span>`;
                        }
                    },
                    {
                        title: 'Total', field: 'grand_total', headerSort: false, hozAlign: 'right', width: 130,
                        formatter(cell) { return `<span class="font-mono text-sm font-bold text-gray-800">IDR ${Number(cell.getValue()||0).toLocaleString('id-ID')}</span>`; }
                    },
                ],
            });
        },

        applyFilters() { if (this.table) this.table.setData(_BASE_URL, this.getCleanFilters()); },
        resetFilters() { this.filters = { from:'', to:'', type:'', order_date:'date' }; this.applyFilters(); },
        getCleanFilters() {
            const p = {};
            Object.entries(this.filters).forEach(([k, v]) => { if (v !== '' && v !== null) p[k] = v; });
            return p;
        }
    };
}

function balColor(v) { return v > 0 ? 'text-emerald-600' : (v < 0 ? 'text-rose-600' : 'text-gray-500'); }
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
</script>
@endpush

@endsection

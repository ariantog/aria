@extends('layouts.app')

@section('title', 'Transactions')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Transactions', 'href' => route('transactions.index')],
    ['title' => 'List', 'href' => route('transactions.index')],
];
@endphp

<div class="flex flex-col gap-3 p-3 sm:p-4" x-data="transactionsIndex()" x-init="init()">
    {{-- Header --}}
    <div class="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Transactions</h2>
            <p class="mt-0.5 text-sm text-gray-500">Manage and track your buy, sell, and transfer transactions.</p>
        </div>
        <div class="flex gap-2">
            @if($can['delete_transaction'])
            <a href="{{ route('transactions.deleted.index') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-white px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                View Deleted
            </a>
            @endif
        </div>
    </div>

    {{-- Filters --}}
    <form @submit.prevent="applyFilters()" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-white p-3">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">From</label>
            <input type="date" x-model="filters.from" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">To</label>
            <input type="date" x-model="filters.to" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Type</label>
            <select x-model="filters.type" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <option value="">All Types</option>
                <option value="1">Buy</option>
                <option value="2">Sell</option>
                <option value="3">Move</option>
                <option value="6">Transfer</option>
                <option value="7">Cash Out</option>
                <option value="8">Use</option>
                <option value="9">Cash In</option>
                <option value="12">Adjust</option>
                <option value="15">Return</option>
                <option value="16">Production</option>
                <option value="17">Ret. Supplier</option>
                <option value="18">Depreciation</option>
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Invoice</label>
            <input type="text" x-model="filters.invoice_number" placeholder="Search invoice…" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Min Total</label>
            <input type="number" x-model="filters.min_total" placeholder="0" class="w-28 rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Max Total</label>
            <input type="number" x-model="filters.max_total" placeholder="∞" class="w-28 rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
            <button type="button" @click="resetFilters()" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</button>
        </div>
    </form>

    {{-- Table container --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div id="transactions-table"></div>
    </div>
</div>

@push('scripts')
<script>

// Escape untrusted values before interpolating into Tabulator HTML formatters (stored-XSS guard)
function esc(v) {
    return String(v ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c]));
}
const _CSRF = '{{ csrf_token() }}';
const _CAN  = @json($can);

function transactionsIndex() {
    return {
        table: null,
        filters: {
            from: '{{ $filters['from'] ?? '' }}',
            to: '{{ $filters['to'] ?? '' }}',
            type: '{{ $filters['type'] ?? '' }}',
            invoice_number: '{{ $filters['invoice_number'] ?? '' }}',
            min_total: '{{ $filters['min_total'] ?? '' }}',
            max_total: '{{ $filters['max_total'] ?? '' }}',
        },

        init() {
            this.$nextTick(() => this.buildTable());
        },

        buildTable() {
            const self = this;

            this.table = new Tabulator('#transactions-table', {
                ajaxURL: '/transactions',
                ajaxConfig: {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': _CSRF,
                    },
                },
                ajaxParams: () => {
                    const p = {};
                    Object.entries(self.filters).forEach(([k, v]) => { if (v !== '' && v !== null) p[k] = v; });
                    return p;
                },
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
                paginationSizeSelector: [25, 50, 100],
                sortMode: 'remote',
                ajaxSorting: true,
                initialSort: [{ column: 'date', dir: 'desc' }],
                layout: 'fitDataStretch',
                responsiveLayout: false,
                height: 'calc(100vh - 300px)',
                minHeight: 300,
                placeholder: '<div style="padding:32px;text-align:center;color:#9ca3af;font-size:13px;">No transactions found.</div>',
                columns: [
                    {
                        title: 'Date', field: 'date', sorter: 'string', width: 90, frozen: true,
                        formatter(cell) {
                            const d = cell.getValue();
                            if (!d) return '-';
                            const dt = new Date(d);
                            return `${String(dt.getDate()).padStart(2,'0')}/${String(dt.getMonth()+1).padStart(2,'0')}/${String(dt.getFullYear()).slice(-2)}`;
                        }
                    },
                    {
                        title: 'Invoice', field: 'invoice_number', sorter: 'string', width: 130,
                        formatter(cell) {
                            const v = cell.getValue();
                            const id = cell.getRow().getData().id;
                            return `<a href="/transactions/${id}" class="font-mono text-blue-600 hover:underline break-all">${esc(v) || "-"}</a>`;
                        }
                    },
                    {
                        title: 'Type', field: 'type', sorter: 'number', width: 110,
                        formatter(cell) {
                            const t = cell.getValue();
                            const types = {
                                1:  ['Buy',           'text-emerald-700 bg-emerald-50', 'bg-emerald-500'],
                                2:  ['Sell',          'text-blue-700 bg-blue-50',       'bg-blue-500'],
                                3:  ['Move',          'text-amber-700 bg-amber-50',     'bg-amber-500'],
                                6:  ['Transfer',      'text-cyan-700 bg-cyan-50',       'bg-cyan-500'],
                                7:  ['Cash Out',      'text-rose-700 bg-rose-50',       'bg-rose-500'],
                                8:  ['Use',           'text-yellow-700 bg-yellow-50',   'bg-yellow-500'],
                                9:  ['Cash In',       'text-purple-700 bg-purple-50',   'bg-purple-500'],
                                12: ['Adjust',        'text-indigo-700 bg-indigo-50',   'bg-indigo-500'],
                                15: ['Return',        'text-rose-700 bg-rose-50',       'bg-rose-500'],
                                16: ['Production',    'text-slate-700 bg-slate-50',     'bg-slate-500'],
                                17: ['Ret. Supplier', 'text-orange-700 bg-orange-50',   'bg-orange-500'],
                                18: ['Depreciation',  'text-zinc-700 bg-zinc-50',       'bg-zinc-500'],
                            };
                            const [label, cls, dot] = types[t] || ['Unknown','text-gray-700 bg-gray-50','bg-gray-400'];
                            return `<span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${cls}"><span class="h-1.5 w-1.5 rounded-full flex-shrink-0 ${dot}"></span>${label}</span>`;
                        }
                    },
                    {
                        title: 'Description', field: 'description', sorter: false, widthGrow: 2,
                        formatter(cell) {
                            const row = cell.getRow().getData();
                            const v = row.description || row.notes || '-';
                            return `<span class="text-gray-500 text-xs leading-tight">${esc(v)}</span>`;
                        }
                    },
                    {
                        title: 'Grand Total', field: 'grand_total', sorter: 'number', hozAlign: 'right', width: 120,
                        formatter(cell) {
                            return `<span class="font-semibold tabular-nums">${Number(cell.getValue()||0).toLocaleString('id-ID')}</span>`;
                        }
                    },
                    {
                        title: 'Items', field: 'total_items', sorter: 'number', hozAlign: 'right', width: 65,
                        formatter(cell) {
                            return `<span class="tabular-nums text-gray-500">${Number(cell.getValue()||0).toLocaleString()}</span>`;
                        }
                    },
                    {
                        title: 'Sender', field: 'sender', sorter: false, width: 140,
                        formatter(cell) {
                            const s = cell.getValue();
                            if (!s) return '<span class="text-gray-400">-</span>';
                            return `<a href="/${esc(s.type_slug)}/${s.id}" class="text-blue-600 hover:underline leading-tight">${esc(s.name)}</a>`;
                        }
                    },
                    {
                        title: 'Sender Bal', field: 'sender_balance', hozAlign: 'right', sorter: false, width: 110,
                        formatter(cell) {
                            const row = cell.getRow().getData();
                            if (_CAN.bank_hidden_balance && row.sender?.type_slug === 'bank') return '0';
                            return `<span class="tabular-nums font-medium">${Number(cell.getValue()||0).toLocaleString('id-ID')}</span>`;
                        }
                    },
                    {
                        title: 'Receiver', field: 'receiver', sorter: false, width: 140,
                        formatter(cell) {
                            const r = cell.getValue();
                            if (!r) return '<span class="text-gray-400">-</span>';
                            return `<a href="/${esc(r.type_slug)}/${r.id}" class="text-blue-600 hover:underline leading-tight">${esc(r.name)}</a>`;
                        }
                    },
                    {
                        title: 'Recv Bal', field: 'receiver_balance', hozAlign: 'right', sorter: false, width: 110,
                        formatter(cell) {
                            const row = cell.getRow().getData();
                            if (_CAN.bank_hidden_balance && row.receiver?.type_slug === 'bank') return '0';
                            return `<span class="tabular-nums font-medium">${Number(cell.getValue()||0).toLocaleString('id-ID')}</span>`;
                        }
                    },
                    {
                        title: '', field: 'id', sorter: false, hozAlign: 'center', width: 50, frozen: true,
                        headerSort: false,
                        formatter(cell) {
                            const id = cell.getValue();
                            return `<div class="relative inline-block" x-data="{open:false}">
                                <button @click.stop="open=!open" class="flex h-6 w-6 items-center justify-center rounded text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z"/></svg>
                                </button>
                                <div x-show="open" @click.away="open=false" x-cloak
                                     class="absolute right-0 top-8 z-50 w-40 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    <a href="/transactions/${id}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        View
                                    </a>
                                    ${_CAN.delete_transaction ? `<div class="border-t border-gray-100 my-1"></div>
                                    <button onclick="deleteTransaction(${id})" class="flex w-full items-center gap-2 px-3 py-2 text-sm text-rose-600 hover:bg-rose-50">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        Delete
                                    </button>` : ''}
                                </div>
                            </div>`;
                        }
                    },
                ],
            });
        },

        applyFilters() {
            if (this.table) {
                this.table.setData('/transactions', this.getCleanFilters());
            }
        },

        resetFilters() {
            this.filters = { from:'', to:'', type:'', invoice_number:'', min_total:'', max_total:'' };
            this.applyFilters();
        },

        getCleanFilters() {
            const p = {};
            Object.entries(this.filters).forEach(([k, v]) => { if (v !== '' && v !== null) p[k] = v; });
            return p;
        }
    };
}

async function deleteTransaction(id) {
    if (!confirm('Delete this transaction? Stock and balance impact will be reversed.')) return;
    const res = await fetch(`/transactions/${id}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-TOKEN': _CSRF,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: '_method=DELETE',
    });
    if (res.redirected) {
        window.location.href = res.url;
    } else {
        window.location.reload();
    }
}
</script>
@endpush

@endsection

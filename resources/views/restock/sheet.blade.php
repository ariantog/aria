@extends('layouts.app')

@push('head-css')
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    .tabulator { font-size: 13px; border-radius: 0.5rem; overflow: hidden; }
    .tabulator .tabulator-header .tabulator-col.tabulator-col-group-restock { background: #dbeafe; }
    .tabulator .tabulator-header .tabulator-col.tabulator-col-group-production { background: #fde68a; }
    .tabulator .tabulator-header .tabulator-col.tabulator-col-group-shipped { background: #e5e7eb; }
    .tabulator-cell.tabulator-editing { border: 2px solid #2563eb !important; }
    .restock-urgent-cell { background-color: #fef2f2 !important; color: #b91c1c; font-weight: 600; }
</style>
@endpush

@section('title', $sheet->name.' — Restock')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Stuff', 'href' => '#'],
    ['title' => 'Restock', 'href' => route('restock.index')],
    ['title' => $sheet->typeTag->name, 'href' => route('restock.type.show', $sheet->typeTag)],
    ['title' => $sheet->name, 'href' => route('restock.sheets.show', $sheet)],
];
@endphp

<div class="flex flex-col gap-4 p-4" x-data="restockSheetPage()" x-init="init()">
    @include('restock.partials.type-tabs', [
        'typeTags' => $typeTags,
        'activeTypeTag' => $sheet->typeTag,
    ])

    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-4">
            <img src="{{ $sheet->image_url }}" alt="" class="h-16 w-16 rounded-lg border border-gray-200 object-cover">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $sheet->name }}</h1>
                <p class="text-sm text-gray-500">{{ count($grid['parents']) }} parent variant(s)</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @can('restock-edit')
            <button type="button" @click="save()" :disabled="saving || !canEdit"
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                <span x-text="saving ? 'Saving…' : 'Save sheet'"></span>
            </button>
            <form method="POST" action="{{ route('restock.sheets.sync', $sheet) }}">
                @csrf
                <button type="submit" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Sync SKUs
                </button>
            </form>
            @endcan
            <a href="{{ route('restock.type.show', $sheet->typeTag) }}"
               class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Back to list
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div x-show="flash" x-cloak class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" x-text="flash"></div>
    <div x-show="error" x-cloak class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="error"></div>

    <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
        Edit quantities in the grid, then click <strong>Save sheet</strong>. Hover a cell to see warehouse stock. Receive and move actions ship in a later PR.
    </div>

    @forelse($grid['parents'] as $parent)
        <section id="parent-{{ $parent['pcode'] }}" class="scroll-mt-4 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 bg-gray-50 px-4 py-3">
                <h2 class="font-semibold text-gray-900">{{ $parent['name'] }}</h2>
                <p class="font-mono text-xs text-gray-500">{{ $parent['pcode'] }}</p>
            </div>
            <div class="p-2" data-parent-grid="{{ $parent['pcode'] }}"></div>
        </section>
    @empty
        <div class="rounded-xl border border-gray-200 bg-white p-8 text-center text-gray-500">
            No cells seeded. Try Sync SKUs.
        </div>
    @endforelse
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
function restockSheetPage() {
    return {
        grid: @json($grid),
        canEdit: @json($canEdit),
        saveUrl: @json(route('restock.sheets.update', $sheet)),
        tables: {},
        saving: false,
        flash: '',
        error: '',

        init() {
            this.$nextTick(() => {
                for (const parent of this.grid.parents) {
                    const el = document.querySelector(`[data-parent-grid="${parent.pcode}"]`);
                    if (!el) continue;
                    this.tables[parent.pcode] = new Tabulator(el, {
                        data: parent.rows,
                        layout: 'fitDataStretch',
                        height: Math.max(120, (parent.rows.length + 1) * 38 + 20),
                        columnDefaults: { headerHozAlign: 'center', hozAlign: 'right' },
                        columns: this.buildColumns(parent.sizes),
                        rowFormatter: (row) => this.formatUrgentRow(row),
                    });
                }
            });
        },

        buildColumns(sizes) {
            const cols = [
                { title: 'Color', field: 'color_name', frozen: true, width: 130, hozAlign: 'left', headerHozAlign: 'left', editor: false },
            ];

            const stages = [
                { key: 'restock', title: 'Restock', groupClass: 'tabulator-col-group-restock' },
                { key: 'production', title: 'Production', groupClass: 'tabulator-col-group-production' },
                { key: 'shipped', title: 'Shipped', groupClass: 'tabulator-col-group-shipped' },
            ];

            for (const stage of stages) {
                const children = sizes.map((size) => {
                    const prefix = this.sizePrefix(size);
                    const field = prefix + stage.key;
                    return {
                        title: size,
                        field,
                        editor: this.canEdit ? 'number' : false,
                        minWidth: 58,
                        formatter: (cell) => {
                            const val = cell.getValue() ?? 0;
                            const row = cell.getRow().getData();
                            const stock = row[prefix + 'stock'];
                            if (stock != null) {
                                cell.getElement().setAttribute('title', `Warehouse stock: ${stock}`);
                            }
                            return val;
                        },
                    };
                });

                if (stage.key === 'restock' && sizes.length > 1) {
                    children.push({
                        title: 'Total',
                        field: 'restock_total',
                        editor: false,
                        minWidth: 64,
                        hozAlign: 'right',
                        formatter: (cell) => cell.getValue() ?? 0,
                    });
                }

                cols.push({ title: stage.title, cssClass: stage.groupClass, columns: children });
            }

            return cols;
        },

        sizePrefix(size) {
            if (size === '—') return '';
            return size.toLowerCase().replace(/[. ]/g, '_') + '_';
        },

        formatUrgentRow(row) {
            const data = row.getData();
            Object.entries(data._meta || {}).forEach(([prefix, meta]) => {
                if (!meta?.is_urgent) return;
                ['restock', 'production', 'shipped'].forEach((stage) => {
                    const cell = row.getCell(prefix + stage);
                    if (cell) cell.getElement().classList.add('restock-urgent-cell');
                });
            });
        },

        collectCells() {
            const cells = [];
            for (const parent of this.grid.parents) {
                const table = this.tables[parent.pcode];
                if (!table) continue;
                for (const row of table.getData()) {
                    for (const [prefix, meta] of Object.entries(row._meta || {})) {
                        if (!meta?.cell_id) continue;
                        cells.push({
                            id: meta.cell_id,
                            qty_restock: Number(row[prefix + 'restock'] ?? 0),
                            qty_production: Number(row[prefix + 'production'] ?? 0),
                            qty_shipped: Number(row[prefix + 'shipped'] ?? 0),
                        });
                    }
                }
            }
            return cells;
        },

        async save() {
            if (!this.canEdit) return;
            this.saving = true;
            this.flash = '';
            this.error = '';
            try {
                const res = await fetch(this.saveUrl, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ cells: this.collectCells() }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Save failed');
                this.flash = data.message || 'Saved.';
            } catch (e) {
                this.error = e.message || 'Save failed';
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
@endpush

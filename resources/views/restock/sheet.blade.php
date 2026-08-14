@extends('layouts.app')

@push('head-css')
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    .tabulator { font-size: 13px; border-radius: 0.375rem; overflow: hidden; width: 100%; }
    .tabulator .tabulator-header .tabulator-col.tabulator-col-group-restock { background: #dbeafe; }
    .tabulator .tabulator-header .tabulator-col.tabulator-col-group-production { background: #fde68a; }
    .tabulator .tabulator-header .tabulator-col.tabulator-col-group-shipped { background: #e5e7eb; }
    .tabulator .tabulator-header .tabulator-col.tabulator-col-group-stock { background: #d1fae5; }
    .tabulator-cell.tabulator-editing { border: 2px solid #2563eb !important; }
    .restock-urgent-cell { background-color: #fef2f2 !important; color: #b91c1c; font-weight: 600; }
    .restock-na-cell { background-color: #f3f4f6 !important; color: #9ca3af; }
    .restock-grid-scroll { overflow-x: auto; }
    .restock-section-row { background: #f9fafb !important; }
    .restock-section-row .tabulator-cell { border-bottom-color: #d1d5db !important; }
    .restock-spacer-row { background: #fff !important; pointer-events: none; }
    .restock-spacer-row .tabulator-cell { border: none !important; padding-top: 0; padding-bottom: 0; min-height: 10px; height: 10px; }
    .restock-sheet-actions {
        position: sticky;
        top: 0;
        z-index: 20;
    }
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

<div x-data="restockSheetPage()" x-init="init()">
    <div class="flex flex-col gap-4 p-4 pb-0">
        @include('restock.partials.type-tabs', [
            'typeTags' => $typeTags,
            'activeTypeTag' => $sheet->typeTag,
        ])

        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-4">
                <img src="{{ $sheet->image_url }}" alt="" class="h-16 w-16 rounded-lg border border-gray-200 object-cover"
                     onerror="this.onerror=null;this.src='{{ asset('images/default-item.svg') }}'">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ $sheet->name }}</h1>
                    <p class="text-sm text-gray-500">{{ count($grid['parents']) }} parent variant(s)</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            Select color row(s) with the checkboxes, then use move buttons to advance quantities through the pipeline, or <strong>Receive → Warehouse</strong> for shipped qty. Edit restock / production / shipped cells directly and click <strong>Save sheet</strong> for manual adjustments. <strong>Stock</strong> shows warehouse qty from settings ({{ $stockWarehouseLabel }}). Any receive shortfall is recorded on the <a href="{{ route('restock.type.missing', $sheet->typeTag) }}" class="font-medium underline">Missing SKUs</a> page.
            @unless($receiveReady)
                <span class="mt-1 block text-amber-800">Receive is disabled until defaults are configured in <a href="{{ route('restock.settings.edit') }}" class="font-medium underline">Restock settings</a>.</span>
            @endunless
        </div>
    </div>

    <div class="restock-sheet-actions border-b border-gray-200 bg-gray-50/95 px-4 py-2 shadow-sm backdrop-blur supports-[backdrop-filter]:bg-gray-50/90">
        <div class="flex flex-col gap-2">
            <div class="flex flex-wrap items-center gap-2">
                @can('restock-edit')
                <button type="button" @click="save()" :disabled="saving || !canEdit"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    <span x-text="saving ? 'Saving…' : 'Save sheet'"></span>
                </button>
                <button type="button" @click="move('to_production')" :disabled="moving || !canEdit || selectionCount === 0"
                        class="rounded-md border border-amber-300 bg-amber-50 px-3 py-1.5 text-sm font-medium text-amber-900 hover:bg-amber-100 disabled:opacity-50">
                    Restock → Production
                </button>
                <button type="button" @click="move('to_shipped')" :disabled="moving || !canEdit || selectionCount === 0"
                        class="rounded-md border border-gray-300 bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-800 hover:bg-gray-200 disabled:opacity-50">
                    Production → Shipped
                </button>
                <button type="button" @click="openReceiveModal()" :disabled="receiving || !canEdit || !receiveReady || selectionCount === 0"
                        title="{{ $receiveReady ? 'Receive shipped qty into warehouse (Buy transaction)' : 'Configure supplier and receiver in Restock settings' }}"
                        class="rounded-md border border-green-300 bg-green-50 px-3 py-1.5 text-sm font-medium text-green-900 hover:bg-green-100 disabled:opacity-50">
                    Receive → Warehouse
                </button>
                <form method="POST" action="{{ route('restock.sheets.sync', $sheet) }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Sync SKUs
                    </button>
                </form>
                @endcan
                <a href="{{ route('restock.sheets.export', $sheet) }}"
                   class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Export Excel
                </a>
                <a href="{{ route('restock.type.missing', $sheet->typeTag) }}"
                   class="rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm font-medium text-red-800 hover:bg-red-100">
                    Missing SKUs
                </a>
                <a href="{{ route('restock.type.show', $sheet->typeTag) }}"
                   class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Back to list
                </a>
            </div>

            @if(session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            <div x-show="flash" x-cloak class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" x-text="flash"></div>
            <div x-show="error" x-cloak class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="error"></div>
        </div>
    </div>

    <div class="flex flex-col gap-4 p-4 pt-3">
    @forelse($grid['blocks'] as $block)
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            @if(count($grid['blocks']) > 1)
                <div class="border-b border-gray-100 bg-gray-50 px-4 py-2">
                    <h2 class="text-sm font-semibold text-gray-700">{{ $block['title'] }}</h2>
                </div>
            @endif
            <div class="restock-grid-scroll p-2" data-block-grid="{{ $block['id'] }}"></div>
        </section>
    @empty
        <div class="rounded-xl border border-gray-200 bg-white p-8 text-center text-gray-500">
            No cells seeded. Try Sync SKUs.
        </div>
    @endforelse
    </div>

    <div x-show="receiveModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="receiveModalOpen = false">
        <div class="w-full max-w-2xl rounded-xl border border-gray-200 bg-white p-5 shadow-xl">
            <h3 class="text-lg font-semibold text-gray-900">Receive into warehouse</h3>
            <p class="mt-1 text-sm text-gray-500">Adjust received qty per SKU. Any shortfall vs shipped is recorded as missing.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="receive-date" class="block text-sm font-medium text-gray-700">Date</label>
                    <input id="receive-date" type="date" x-model="receiveForm.date"
                           class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="receive-invoice" class="block text-sm font-medium text-gray-700">Invoice (optional)</label>
                    <input id="receive-invoice" type="text" x-model="receiveForm.invoice" placeholder="Invoice number"
                           class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div class="mt-4 max-h-72 overflow-y-auto rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="sticky top-0 bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">SKU</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Shipped</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Receive</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Missing</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(line, idx) in receiveLines" :key="line.id">
                            <tr>
                                <td class="px-3 py-2 text-gray-900" x-text="line.label"></td>
                                <td class="px-3 py-2 text-right tabular-nums text-gray-600" x-text="line.shipped"></td>
                                <td class="px-3 py-2 text-right">
                                    <input type="number" min="0" :max="line.shipped" x-model.number="receiveLines[idx].qty"
                                           class="w-20 rounded border border-gray-300 px-2 py-1 text-right text-sm tabular-nums">
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums text-red-700" x-text="receiveShortfall(line)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <p x-show="receiveLines.length === 0" class="mt-4 text-sm text-gray-500">No shipped quantity on the selected row(s).</p>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" @click="receiveModalOpen = false"
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="button" @click="receive()" :disabled="receiving || receiveLines.length === 0 || !hasReceiveQty()"
                        class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">
                    <span x-text="receiving ? 'Receiving…' : 'Confirm receive'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
function restockSheetPage() {
    return {
        grid: @json($grid),
        canEdit: @json($canEdit),
        receiveReady: @json($receiveReady),
        saveUrl: @json(route('restock.sheets.update', $sheet)),
        moveUrl: @json(route('restock.sheets.move', $sheet)),
        receiveUrl: @json(route('restock.sheets.receive', $sheet)),
        defaultImageUrl: @json(asset('images/default-item.svg')),
        tables: {},
        selectionCount: 0,
        saving: false,
        moving: false,
        receiving: false,
        receiveModalOpen: false,
        receiveLines: [],
        receiveForm: {
            date: @json(now()->toDateString()),
            invoice: '',
        },
        flash: '',
        error: '',

        init() {
            this.$nextTick(() => {
                for (const block of this.grid.blocks ?? []) {
                    const el = document.querySelector(`[data-block-grid="${block.id}"]`);
                    if (!el) continue;

                    this.tables[block.id] = new Tabulator(el, {
                        data: block.rows,
                        layout: 'fitData',
                        height: Math.max(140, block.rows.length * 36 + 44),
                        selectableRows: this.canEdit,
                        selectableRowsCheck: (row) => row.getData()._type === 'data',
                        rowHeader: this.canEdit ? {
                            formatter: 'rowSelection',
                            titleFormatter: 'rowSelection',
                            headerSort: false,
                            frozen: true,
                            width: 40,
                        } : false,
                        columnDefaults: { headerHozAlign: 'center', hozAlign: 'right', widthGrow: 0 },
                        columns: block.kind === 'flat'
                            ? this.buildFlatColumns()
                            : this.buildMatrixColumns(block.sizes ?? []),
                        rowFormatter: (row) => this.formatRow(row),
                    });

                    this.tables[block.id].on('rowSelectionChanged', () => this.syncSelectionCount());
                }
            });
        },

        buildColorColumn() {
            return {
                title: 'Color',
                field: 'color_name',
                frozen: true,
                width: 180,
                widthGrow: 0,
                hozAlign: 'left',
                headerHozAlign: 'left',
                editor: false,
                formatter: (cell) => this.formatColorCell(cell),
            };
        },

        formatColorCell(cell) {
            const data = cell.getRow().getData();

            if (data._type === 'section') {
                const wrap = document.createElement('div');
                wrap.className = 'flex items-center gap-2 py-0.5';

                const img = document.createElement('img');
                img.src = data.image_url || this.defaultImageUrl;
                img.alt = '';
                img.className = 'h-10 w-10 rounded border border-gray-200 object-cover shrink-0';
                img.onerror = () => { img.onerror = null; img.src = this.defaultImageUrl; };

                const text = document.createElement('div');
                const name = document.createElement('div');
                name.className = 'font-semibold text-gray-900 leading-tight';
                name.textContent = data.name || data.pcode || '';

                const pcode = document.createElement('div');
                pcode.className = 'font-mono text-xs text-gray-500';
                pcode.textContent = data.pcode || '';

                text.append(name, pcode);
                wrap.append(img, text);

                return wrap;
            }

            if (data._type === 'spacer') {
                return document.createElement('span');
            }

            return document.createTextNode(data.color_name ?? '');
        },

        buildMatrixColumns(sizes) {
            const cols = [this.buildColorColumn()];

            const stages = [
                { key: 'restock', title: 'Restock', groupClass: 'tabulator-col-group-restock', editable: true },
                { key: 'production', title: 'Production', groupClass: 'tabulator-col-group-production', editable: true },
                { key: 'shipped', title: 'Shipped', groupClass: 'tabulator-col-group-shipped', editable: true },
                { key: 'stock', title: 'Stock', groupClass: 'tabulator-col-group-stock', editable: false },
            ];

            for (const stage of stages) {
                const children = sizes.map((size) => {
                    const prefix = this.sizePrefix(size);
                    const field = prefix + stage.key;

                    return {
                        title: size,
                        field,
                        width: 58,
                        widthGrow: 0,
                        editor: stage.editable && this.canEdit ? 'number' : false,
                        editable: (cell) => this.canEditMatrixCell(cell, size, stage.editable),
                        formatter: (cell) => this.formatQtyCell(cell, size),
                    };
                });

                if (sizes.length > 1) {
                    children.push({
                        title: 'Total',
                        field: stage.key + '_total',
                        width: 64,
                        widthGrow: 0,
                        editor: false,
                        hozAlign: 'right',
                        formatter: (cell) => this.formatQtyCell(cell, null),
                    });
                }

                cols.push({ title: stage.title, cssClass: stage.groupClass, columns: children });
            }

            return cols;
        },

        buildFlatColumns() {
            const cols = [this.buildColorColumn()];

            const stages = [
                { key: 'restock', title: 'Restock', groupClass: 'tabulator-col-group-restock', editable: true },
                { key: 'production', title: 'Production', groupClass: 'tabulator-col-group-production', editable: true },
                { key: 'shipped', title: 'Shipped', groupClass: 'tabulator-col-group-shipped', editable: true },
                { key: 'stock', title: 'Stock', groupClass: 'tabulator-col-group-stock', editable: false },
            ];

            for (const stage of stages) {
                cols.push({
                    title: stage.title,
                    field: stage.key,
                    cssClass: stage.groupClass,
                    width: 72,
                    widthGrow: 0,
                    editor: stage.editable && this.canEdit ? 'number' : false,
                    editable: (cell) => this.canEditFlatCell(cell, stage.editable),
                    formatter: (cell) => this.formatQtyCell(cell, '—'),
                });
            }

            return cols;
        },

        canEditMatrixCell(cell, size, stageEditable) {
            if (!this.canEdit || !stageEditable) return false;

            const row = cell.getRow().getData();
            if (row._type !== 'data') return false;

            return (row.parent_sizes ?? []).includes(size);
        },

        canEditFlatCell(cell, stageEditable) {
            if (!this.canEdit || !stageEditable) return false;

            return cell.getRow().getData()._type === 'data';
        },

        formatQtyCell(cell, size) {
            const row = cell.getRow().getData();

            if (row._type !== 'data') {
                return '';
            }

            if (size !== null && size !== '—' && !(row.parent_sizes ?? []).includes(size)) {
                cell.getElement().classList.add('restock-na-cell');

                return '—';
            }

            cell.getElement().classList.remove('restock-na-cell');

            return cell.getValue() ?? 0;
        },

        sizePrefix(size) {
            if (size === '—') return '';
            return size.toLowerCase().replace(/[. ]/g, '_') + '_';
        },

        formatRow(row) {
            const data = row.getData();
            const el = row.getElement();

            el.classList.remove('restock-section-row', 'restock-spacer-row');

            if (data._type === 'section') {
                el.classList.add('restock-section-row');
                return;
            }

            if (data._type === 'spacer') {
                el.classList.add('restock-spacer-row');
                return;
            }

            this.formatUrgentRow(row);
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

        collectCellsFromRows(rows) {
            const cells = [];
            for (const row of rows) {
                if (row._type !== 'data') continue;
                for (const meta of Object.values(row._meta || {})) {
                    if (meta?.cell_id) cells.push({ id: meta.cell_id });
                }
            }
            return cells;
        },

        selectedRows() {
            const rows = [];
            for (const block of this.grid.blocks ?? []) {
                const table = this.tables[block.id];
                if (!table) continue;
                rows.push(...table.getSelectedRows().map((row) => row.getData()));
            }
            return rows;
        },

        syncSelectionCount() {
            this.selectionCount = this.selectedRows().length;
        },

        hasSelection() {
            return this.selectionCount > 0;
        },

        applyGrid(grid) {
            this.grid = grid;
            for (const block of grid.blocks ?? []) {
                const table = this.tables[block.id];
                if (!table) continue;
                table.setData(block.rows);
                table.deselectRow();
                for (const row of table.getRows()) {
                    this.formatRow(row);
                }
            }
            this.syncSelectionCount();
        },

        async move(direction) {
            if (!this.canEdit) return;
            const rows = this.selectedRows();
            if (!rows.length) return;

            const cells = this.collectCellsFromRows(rows);
            if (!cells.length) return;

            this.moving = true;
            this.flash = '';
            this.error = '';
            try {
                const res = await fetch(this.moveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ direction, cells }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Move failed');
                if (data.grid) this.applyGrid(data.grid);
                this.flash = data.message || 'Moved.';
            } catch (e) {
                this.error = e.message || 'Move failed';
            } finally {
                this.moving = false;
            }
        },

        buildReceiveLines(rows) {
            const lines = [];
            for (const row of rows) {
                if (row._type !== 'data') continue;
                for (const [prefix, meta] of Object.entries(row._meta || {})) {
                    const shipped = Number(row[prefix + 'shipped'] ?? 0);
                    if (!meta?.cell_id || shipped <= 0) continue;
                    const code = meta.item_code || row.color_name;
                    const size = meta.size_label && meta.size_label !== '—' ? ` · ${meta.size_label}` : '';
                    lines.push({
                        id: meta.cell_id,
                        label: `${code}${size}`,
                        shipped,
                        qty: shipped,
                    });
                }
            }
            return lines;
        },

        receiveShortfall(line) {
            const shipped = Number(line.shipped ?? 0);
            const qty = Math.min(Math.max(0, Number(line.qty ?? 0)), shipped);
            return Math.max(0, shipped - qty);
        },

        hasReceiveQty() {
            return this.receiveLines.some((line) => Number(line.qty ?? 0) > 0);
        },

        openReceiveModal() {
            if (!this.canEdit || !this.receiveReady || !this.hasSelection()) return;
            this.receiveLines = this.buildReceiveLines(this.selectedRows());
            this.receiveModalOpen = true;
        },

        async receive() {
            if (!this.canEdit || !this.receiveReady) return;
            if (!this.receiveLines.length || !this.hasReceiveQty()) return;

            const cells = this.receiveLines
                .filter((line) => Number(line.qty ?? 0) > 0)
                .map((line) => ({
                    id: line.id,
                    qty: Math.min(Number(line.qty), Number(line.shipped)),
                }));

            this.receiving = true;
            this.flash = '';
            this.error = '';
            try {
                const res = await fetch(this.receiveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        date: this.receiveForm.date,
                        invoice: this.receiveForm.invoice || null,
                        cells,
                    }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Receive failed');
                if (data.grid) this.applyGrid(data.grid);
                this.receiveModalOpen = false;
                this.receiveLines = [];
                this.flash = data.transaction_url
                    ? `${data.message} Transaction #${data.transaction_id}.`
                    : (data.message || 'Received.');
            } catch (e) {
                this.error = e.message || 'Receive failed';
            } finally {
                this.receiving = false;
            }
        },

        collectCells() {
            const cells = [];
            for (const block of this.grid.blocks ?? []) {
                const table = this.tables[block.id];
                if (!table) continue;
                for (const row of table.getData()) {
                    if (row._type !== 'data') continue;
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

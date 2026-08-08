@extends('layouts.app')

@section('title', 'Warehouse Arrangement')

@push('head-css')
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    .arrangement-pcode-grid { width: 100%; }
    .arrangement-pcode-grid .tabulator {
        font-size: 12px;
        border-radius: 0.5rem;
        overflow: hidden;
        width: 100%;
        background: transparent;
    }
    .arrangement-pcode-grid .tabulator .tabulator-tableholder { overflow-x: hidden; }
    .arrangement-pcode-grid .tabulator-row .tabulator-cell {
        padding: 8px 6px;
        overflow: visible;
        vertical-align: top;
    }
    .arrangement-pcode-grid .tabulator .tabulator-header .tabulator-col {
        background: #f9fafb;
    }
    .dark .arrangement-pcode-grid .tabulator .tabulator-header .tabulator-col {
        background: #1f2937;
    }
    .arrangement-grid-scroll { overflow-x: auto; width: 100%; }
    .arrangement-actions { position: sticky; top: 0; z-index: 20; }
    .arr-cell {
        width: 100%;
        min-width: 0;
        padding: 2px 0;
        line-height: 1.35;
        box-sizing: border-box;
    }
    .arr-cell-selected {
        background: #eff6ff;
        border-radius: 0.375rem;
        outline: 1px solid #93c5fd;
        padding: 4px 6px;
    }
    .arr-cell-demand { font-size: 11px; color: #047857; font-weight: 600; }
    .arr-cell-meta { font-size: 11px; color: #6b7280; margin-top: 4px; }
    .arr-cell select.arr-cell-wh {
        display: block;
        width: 100%;
        min-width: 0;
        margin-top: 4px;
        font-size: 12px;
        line-height: 1.25;
        border-radius: 0.375rem;
        border: 1px solid #d1d5db;
        padding: 6px 8px;
        background-color: #ffffff;
        color: #111827;
        cursor: pointer;
    }
    .dark .arr-cell select.arr-cell-wh {
        background-color: #ffffff;
        color: #111827;
        border-color: #9ca3af;
    }
    .arr-cell-check { flex-shrink: 0; }
</style>
@endpush

@section('content')
@php
use App\Services\WarehouseArrangementService;

$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'Reports', 'href' => '#'],
    ['title' => 'Warehouse Arrangement', 'href' => route('reports.warehouse-arrangement')],
];
$queryParams = fn (array $extra = []) => array_filter(array_merge([
    'warehouse_id' => $selectedWarehouseId,
    'demand_days' => $demandDays,
    'mode' => $mode,
    'search' => $search ?: null,
    'page' => $page > 1 ? $page : null,
], $extra));
@endphp

<div x-data="arrangementGridPage()" x-init="init()">
    <div class="flex flex-col gap-4 p-4 pb-0">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Warehouse Arrangement</h1>
                <p class="text-gray-500">Pick source warehouses per size, then draft one move transaction per source warehouse.</p>
            </div>
            @if($selectedWarehouseId && $destinationName)
            <a href="{{ route('reports.warehouse-arrangement.export', $queryParams()) }}"
               class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Export Excel
            </a>
            @endif
        </div>

        @if(($flash['error'] ?? null))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $flash['error'] }}</div>
        @endif

        @if($destinations->isEmpty())
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800">
            No destination warehouses enabled. Turn on <strong>Arrangement destination</strong> on a warehouse contact.
        </div>
        @else
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('reports.warehouse-arrangement') }}" class="flex flex-wrap items-end gap-4">
                <input type="hidden" name="mode" value="{{ $mode }}">
                @if($search)
                <input type="hidden" name="search" value="{{ $search }}">
                @endif
                <div>
                    <label for="warehouse_id" class="mb-1 block text-xs font-medium uppercase text-gray-500">Destination</label>
                    <select id="warehouse_id" name="warehouse_id" class="min-w-[220px] rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        @foreach($destinations as $wh)
                        <option value="{{ $wh->id }}" @selected($wh->id === $selectedWarehouseId)>{{ $wh->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="demand_days" class="mb-1 block text-xs font-medium uppercase text-gray-500">Demand window</label>
                    <select id="demand_days" name="demand_days" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        @foreach([30, 90, 180, 365] as $days)
                        <option value="{{ $days }}" @selected($demandDays === $days)>Last {{ $days }} days</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
            </form>
        </div>
        @endif
    </div>

    @if($destinations->isNotEmpty())
    <div class="arrangement-actions border-b border-gray-200 bg-gray-50/95 px-4 py-3 shadow-sm backdrop-blur supports-[backdrop-filter]:bg-gray-50/90">
        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-medium uppercase text-gray-500">View:</span>
                <a href="{{ route('reports.warehouse-arrangement', $queryParams(['mode' => WarehouseArrangementService::MODE_DEMAND, 'page' => null])) }}"
                   class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $mode === WarehouseArrangementService::MODE_DEMAND ? 'bg-blue-600 text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">
                    Demand
                </a>
                <a href="{{ route('reports.warehouse-arrangement', $queryParams(['mode' => WarehouseArrangementService::MODE_FAMILY, 'page' => null])) }}"
                   class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $mode === WarehouseArrangementService::MODE_FAMILY ? 'bg-blue-600 text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">
                    Complete family
                </a>
                <span class="text-xs text-gray-500 ml-2">
                    @if($mode === WarehouseArrangementService::MODE_DEMAND)
                    Missing SKUs with sales here (stocked SKUs hidden).
                    @else
                    Families below {{ WarehouseArrangementService::FAMILY_COMPLETENESS_THRESHOLD }}% complete.
                    @endif
                </span>
            </div>

            <form method="GET" action="{{ route('reports.warehouse-arrangement') }}" class="flex flex-wrap items-end gap-2">
                <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">
                <input type="hidden" name="demand_days" value="{{ $demandDays }}">
                <input type="hidden" name="mode" value="{{ $mode }}">
                <div>
                    <label for="search" class="mb-1 block text-xs font-medium uppercase text-gray-500">Search pcode</label>
                    <input id="search" name="search" type="search" value="{{ $search }}" placeholder="CX90028-02"
                           class="min-w-[160px] rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                </div>
                <button type="submit" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Search</button>
            </form>

            <div class="flex flex-wrap items-center gap-2">
                <template x-for="batch in draftBatches()" :key="batch.from_warehouse_id">
                    <button type="button"
                            @click="draftWarehouse(batch.from_warehouse_id)"
                            class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                        Draft <span x-text="batch.name"></span> (<span x-text="batch.count"></span>)
                    </button>
                </template>
                <span class="text-xs text-gray-500" x-show="selectedCount() > 0" x-cloak>
                    <span x-text="selectedCount()"></span> cell(s) selected
                </span>
            </div>

            <div x-show="error" x-cloak class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" x-text="error"></div>

            @if($totalPcodes > 0)
            <p class="text-xs text-gray-500">
                Page {{ $page }} of {{ $lastPage }} · {{ $totalPcodes }} color pcode(s)
                @if($truncated) · export for full list @endif
            </p>
            @endif
        </div>
    </div>

    <form id="arrangement-draft-form" method="POST" action="{{ route('reports.warehouse-arrangement.draft-move') }}" class="hidden">
        @csrf
        <template x-for="(draft, idx) in draftPayload" :key="`${draft.item_id}-${idx}`">
            <input type="hidden" name="items[][item_id]" :value="draft.item_id">
            <input type="hidden" name="items[][quantity]" :value="draft.quantity">
            <input type="hidden" name="items[][from_warehouse_id]" :value="draft.from_warehouse_id">
            <input type="hidden" name="items[][to_warehouse_id]" :value="draft.to_warehouse_id">
        </template>
    </form>

    <div class="flex flex-col gap-4 p-4 pt-3">
        @forelse($grid['parents'] as $parent)
        <section id="pcode-{{ $parent['pcode'] }}" class="scroll-mt-32 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 bg-gray-50 px-4 py-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="font-mono font-semibold text-gray-900">{{ $parent['pcode'] }}</span>
                        <span class="text-gray-600">{{ $parent['name'] }}</span>
                        @if($parent['warna'] && $parent['warna'] !== '—')
                        <span class="text-gray-500">· {{ $parent['warna'] }}</span>
                        @endif
                        <span class="rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                            demand {{ number_format($parent['family_demand_score'], 0, ',', '.') }}
                        </span>
                        <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                            {{ $parent['present_count'] }}/{{ $parent['total_count'] }} sizes ({{ $parent['completeness_pct'] }}%)
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="selectAllInPcode('{{ $parent['pcode'] }}')"
                                class="rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                            Select all
                        </button>
                        <button type="button" @click="applyWarehouseToPcode('{{ $parent['pcode'] }}')"
                                class="rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                            Same WH as first selected
                        </button>
                    </div>
                </div>
            </div>
            <div class="arrangement-grid-scroll p-2 arrangement-pcode-grid" data-pcode-grid="{{ $parent['pcode'] }}"></div>
        </section>
        @empty
        <div class="rounded-xl border border-gray-200 bg-white p-8 text-center text-sm text-gray-500">
            @if($search)
            No pcode matches “{{ $search }}” for this view.
            @else
            Nothing to move — stock looks OK here, or return after drafting earlier batches.
            @endif
        </div>
        @endforelse

        @if($lastPage > 1)
        <div class="flex flex-wrap items-center justify-center gap-2">
            @if($page > 1)
            <a href="{{ route('reports.warehouse-arrangement', $queryParams(['page' => $page - 1])) }}"
               class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
            @endif
            <span class="text-sm text-gray-500">Page {{ $page }} / {{ $lastPage }}</span>
            @if($page < $lastPage)
            <a href="{{ route('reports.warehouse-arrangement', $queryParams(['page' => $page + 1])) }}"
               class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
            @endif
        </div>
        @endif
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
function arrangementGridPage() {
    return {
        grid: @json($grid),
        cells: {},
        tables: {},
        draftPayload: [],
        error: '',

        init() {
            this.hydrateCells();
            this.$nextTick(() => this.initTablesStaggered());
        },

        initTablesStaggered() {
            const parents = this.grid.parents || [];
            parents.forEach((parent, index) => {
                const delay = index * 40;
                setTimeout(() => this.initTable(parent), delay);
            });
        },

        initTable(parent) {
            const el = document.querySelector(`[data-pcode-grid="${parent.pcode}"]`);
            if (!el || this.tables[parent.pcode]) return;

            const table = new Tabulator(el, {
                        data: parent.rows,
                        layout: 'fitColumns',
                        rowHeight: 104,
                        columnDefaults: {
                            headerHozAlign: 'center',
                            hozAlign: 'left',
                            minWidth: 140,
                            widthGrow: 1,
                            resizable: false,
                        },
                        columns: this.buildColumns(parent.sizes, parent.pcode),
            });

            this.tables[parent.pcode] = table;
            el.addEventListener('change', (e) => this.onCellChange(e, parent.pcode));
        },

        hydrateCells() {
            for (const parent of this.grid.parents) {
                for (const row of parent.rows) {
                    for (const [prefix, cell] of Object.entries(row._cells || {})) {
                        if (cell.inactive) continue;
                        this.cells[cell.item_id] = { ...cell, pcode: parent.pcode, prefix };
                    }
                }
            }
        },

        buildColumns(sizes, pcode) {
            const cols = [];
            for (const size of sizes) {
                const prefix = this.sizePrefix(size);
                cols.push({
                    title: size,
                    field: prefix || 'cell',
                    minWidth: 140,
                    widthGrow: 1,
                    formatter: (cell) => {
                        const data = cell.getRow().getData();
                        const cellData = data._cells?.[prefix];
                        if (!cellData) {
                            const empty = document.createElement('div');
                            empty.className = 'text-center text-gray-400 text-xs';
                            empty.textContent = '—';
                            return empty;
                        }
                        if (cellData.inactive) {
                            const inactive = document.createElement('div');
                            inactive.className = 'text-center text-xs text-gray-500 py-6';
                            const stock = Number(cellData.dest_stock ?? 0);
                            inactive.textContent = stock > 0 ? `OK · Stock ${stock}` : 'No move';
                            return inactive;
                        }
                        const state = this.cells[cellData.item_id];
                        if (!state) {
                            const empty = document.createElement('div');
                            empty.className = 'text-center text-gray-400 text-xs';
                            empty.textContent = '—';
                            return empty;
                        }
                        const wrapper = document.createElement('div');
                        wrapper.innerHTML = this.renderCellHtml(state);
                        return wrapper;
                    },
                });
            }
            return cols;
        },

        renderCellHtml(state) {
            const selected = state.selected ? 'arr-cell-selected' : '';
            const options = (state.sources || []).map((src, idx) => {
                const label = `${src.from_warehouse_name} (${src.source_stock})`;
                const sel = idx === state.chosen_source_index ? 'selected' : '';
                return `<option value="${idx}" ${sel}>${this.escapeHtml(label)}</option>`;
            }).join('');

            const src = this.chosenSource(state);
            const stock = src?.source_stock ?? 0;
            const qty = src?.suggested_qty ?? 0;
            const demand = Number(state.demand ?? 0);

            return `
                <div class="arr-cell ${selected}" data-item-id="${state.item_id}">
                    <label class="flex items-center gap-1 text-xs">
                        <input type="checkbox" class="arr-cell-check rounded border-gray-300" data-item-id="${state.item_id}" ${state.selected ? 'checked' : ''}>
                        <span class="arr-cell-demand">D ${demand.toLocaleString('id-ID')}</span>
                    </label>
                    <select class="arr-cell-wh" data-item-id="${state.item_id}">${options}</select>
                    <div class="arr-cell-meta">Stock ${stock} · Move ${qty}</div>
                </div>`;
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        sizePrefix(size) {
            if (size === '—') return '';
            return size.toLowerCase().replace(/[. ]/g, '_') + '_';
        },

        chosenSource(state) {
            const idx = state.chosen_source_index ?? 0;
            return state.sources?.[idx] ?? state.sources?.[0] ?? null;
        },

        onCellChange(e, pcode) {
            const itemId = Number(e.target.dataset.itemId);
            const state = this.cells[itemId];
            if (!state) return;

            if (e.target.matches('.arr-cell-check')) {
                state.selected = e.target.checked;
            }
            if (e.target.matches('.arr-cell-wh')) {
                state.chosen_source_index = Number(e.target.value);
            }

            const table = this.tables[pcode];
            if (table) table.redraw(true);
        },

        cellsForPcode(pcode) {
            return Object.values(this.cells).filter((c) => c.pcode === pcode);
        },

        selectedCells() {
            return Object.values(this.cells).filter((c) => c.selected);
        },

        selectedCount() {
            return this.selectedCells().length;
        },

        selectAllInPcode(pcode) {
            for (const cell of this.cellsForPcode(pcode)) {
                cell.selected = true;
            }
            this.tables[pcode]?.redraw(true);
        },

        applyWarehouseToPcode(pcode) {
            const cells = this.cellsForPcode(pcode);
            const first = cells.find((c) => c.selected) ?? cells[0];
            if (!first) return;
            const whIdx = first.chosen_source_index ?? 0;
            for (const cell of cells) {
                if ((cell.sources || []).length > whIdx) {
                    cell.chosen_source_index = whIdx;
                }
            }
            this.tables[pcode]?.redraw(true);
        },

        draftBatches() {
            const map = new Map();
            for (const cell of this.selectedCells()) {
                const src = this.chosenSource(cell);
                if (!src) continue;
                const id = src.from_warehouse_id;
                if (!map.has(id)) {
                    map.set(id, { from_warehouse_id: id, name: src.from_warehouse_name, count: 0 });
                }
                map.get(id).count++;
            }
            return [...map.values()];
        },

        draftWarehouse(fromWarehouseId) {
            this.error = '';
            const items = this.selectedCells()
                .filter((cell) => this.chosenSource(cell)?.from_warehouse_id === fromWarehouseId)
                .map((cell) => {
                    const src = this.chosenSource(cell);
                    return {
                        item_id: cell.item_id,
                        quantity: src?.suggested_qty ?? 1,
                        from_warehouse_id: src?.from_warehouse_id,
                        to_warehouse_id: cell.to_warehouse_id,
                    };
                });

            if (!items.length) {
                this.error = 'No selected cells for this warehouse.';
                return;
            }

            this.draftPayload = items;
            this.$nextTick(() => document.getElementById('arrangement-draft-form').submit());
        },
    };
}
</script>
@endpush

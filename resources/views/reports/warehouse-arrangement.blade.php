@extends('layouts.app')

@section('title', 'Warehouse Arrangement')

@push('head-css')
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    .tabulator { font-size: 13px; border-radius: 0.5rem; overflow: hidden; width: max-content; max-width: 100%; }
    .tabulator .tabulator-header .tabulator-col.tabulator-col-group-demand { background: #d1fae5; }
    .tabulator .tabulator-header .tabulator-col.tabulator-col-group-source { background: #dbeafe; }
    .tabulator .tabulator-header .tabulator-col.tabulator-col-group-move { background: #e0e7ff; }
    .arrangement-demand-cell { color: #047857; font-weight: 600; }
    .arrangement-grid-scroll { overflow-x: auto; }
    .arrangement-actions {
        position: sticky;
        top: 0;
        z-index: 20;
    }
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
$fmtNum = fn ($v) => number_format((float) $v, 0, ',', '.');
$queryBase = array_filter([
    'warehouse_id' => $selectedWarehouseId,
    'demand_days' => $demandDays,
    'mode' => $mode,
    'layout' => $layout,
    'min_demand' => $mode === WarehouseArrangementService::MODE_STRONG_DEMAND ? $minDemand : null,
]);
$modeLabels = [
    WarehouseArrangementService::MODE_HIGH_DEMAND => 'High demand',
    WarehouseArrangementService::MODE_COMPLETE_FAMILY => 'Complete family',
    WarehouseArrangementService::MODE_STRONG_DEMAND => 'Strong demand',
    WarehouseArrangementService::MODE_INCOMPLETE => 'Incomplete families',
];
$modeDescriptions = [
    WarehouseArrangementService::MODE_HIGH_DEMAND => 'SKUs with sales at this warehouse in the window.',
    WarehouseArrangementService::MODE_COMPLETE_FAMILY => 'Every missing SKU with stock elsewhere, including zero-demand sizes/colors.',
    WarehouseArrangementService::MODE_STRONG_DEMAND => 'SKUs whose demand meets the minimum threshold.',
    WarehouseArrangementService::MODE_INCOMPLETE => 'Missing SKUs in families that are not 100% stocked here.',
];
@endphp

<div x-data="arrangementGridPage()" x-init="init()">
    <div class="flex flex-col gap-4 p-4 pb-0">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Warehouse Arrangement</h1>
                <p class="text-gray-500">Suggest moves to complete pcode families at high-demand destination warehouses.</p>
            </div>
            @if($selectedWarehouseId && $destinationName)
            <a href="{{ route('reports.warehouse-arrangement.export', $queryBase) }}"
               class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export Excel
            </a>
            @endif
        </div>

        @if(($flash['error'] ?? null))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $flash['error'] }}</div>
        @endif

        @if($destinations->isEmpty())
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800">
            No destination warehouses are enabled for arrangement. Edit a warehouse contact and turn on <strong>Arrangement destination</strong>.
        </div>
        @else
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('reports.warehouse-arrangement') }}" class="flex flex-wrap items-end gap-4">
                <input type="hidden" name="mode" value="{{ $mode }}">
                <input type="hidden" name="layout" value="{{ $layout }}">
                @if($mode === WarehouseArrangementService::MODE_STRONG_DEMAND)
                <input type="hidden" name="min_demand" value="{{ $minDemand }}">
                @endif
                <div>
                    <label for="warehouse_id" class="mb-1 block text-xs font-medium uppercase text-gray-500">Destination warehouse</label>
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
            <p class="mt-3 text-xs text-gray-500">Statistics use stored monthly sell/return aggregates. Run <code class="rounded bg-gray-100 px-1">php artisan app:recalculate-warehouse-item-stats</code> after bulk imports.</p>
        </div>

        @if($families !== [])
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-sm font-medium text-gray-600">Destination</p>
                <p class="mt-1 text-lg font-bold text-gray-900">{{ $destinationName }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-sm font-medium text-gray-600">High-demand families</p>
                <p class="mt-1 text-lg font-bold text-emerald-600">{{ count($families) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-sm font-medium text-gray-600">Move suggestions</p>
                <p class="mt-1 text-lg font-bold text-blue-600">{{ count($suggestions) }}</p>
                @if($truncated ?? false)
                <p class="mt-1 text-xs text-amber-600">Showing top {{ count($suggestions) }} of {{ $totalSuggestionCount }} — export for more.</p>
                @endif
            </div>
        </div>
        @endif
    </div>

    @if($destinations->isNotEmpty() && $families !== [])
    <div class="arrangement-actions border-b border-gray-200 bg-gray-50/95 px-4 py-2 shadow-sm backdrop-blur supports-[backdrop-filter]:bg-gray-50/90">
        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Suggested moves</h2>
                    <p class="text-xs text-gray-500">{{ $modeDescriptions[$mode] ?? '' }} · Grouped by master pcode, colors as rows, sizes as columns (like Restock).</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs text-gray-500" x-show="selectionCount > 0" x-cloak>
                        <span x-text="selectionCount"></span> color row(s) selected
                    </span>
                    <button type="button"
                            @click="draftSelected()"
                            :disabled="!canDraftSelected()"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-500">
                        Draft Move (selected)
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-medium uppercase text-gray-500">Show:</span>
                @foreach($modeLabels as $modeKey => $modeLabel)
                <a href="{{ route('reports.warehouse-arrangement', array_filter([
                    'warehouse_id' => $selectedWarehouseId,
                    'demand_days' => $demandDays,
                    'mode' => $modeKey,
                    'layout' => $layout,
                    'min_demand' => $modeKey === WarehouseArrangementService::MODE_STRONG_DEMAND ? $minDemand : null,
                ])) }}"
                   class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $mode === $modeKey ? 'bg-blue-600 text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">
                    {{ $modeLabel }}
                </a>
                @endforeach
            </div>

            @if($mode === WarehouseArrangementService::MODE_STRONG_DEMAND)
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-medium uppercase text-gray-500">Min demand:</span>
                @foreach([2, 3, 5, 10] as $threshold)
                <a href="{{ route('reports.warehouse-arrangement', [
                    'warehouse_id' => $selectedWarehouseId,
                    'demand_days' => $demandDays,
                    'mode' => $mode,
                    'layout' => $layout,
                    'min_demand' => $threshold,
                ]) }}"
                   class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $minDemand === $threshold ? 'bg-emerald-600 text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">
                    ≥ {{ $threshold }}
                </a>
                @endforeach
            </div>
            @endif

            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-medium uppercase text-gray-500">Sections:</span>
                <a href="{{ route('reports.warehouse-arrangement', array_filter([
                    'warehouse_id' => $selectedWarehouseId,
                    'demand_days' => $demandDays,
                    'mode' => $mode,
                    'layout' => WarehouseArrangementService::LAYOUT_FLAT,
                    'min_demand' => $mode === WarehouseArrangementService::MODE_STRONG_DEMAND ? $minDemand : null,
                ])) }}"
                   class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $layout === WarehouseArrangementService::LAYOUT_FLAT ? 'bg-gray-800 text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">
                    All expanded
                </a>
                <a href="{{ route('reports.warehouse-arrangement', array_filter([
                    'warehouse_id' => $selectedWarehouseId,
                    'demand_days' => $demandDays,
                    'mode' => $mode,
                    'layout' => WarehouseArrangementService::LAYOUT_FAMILY,
                    'min_demand' => $mode === WarehouseArrangementService::MODE_STRONG_DEMAND ? $minDemand : null,
                ])) }}"
                   class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $layout === WarehouseArrangementService::LAYOUT_FAMILY ? 'bg-gray-800 text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">
                    Collapsed
                </a>
            </div>

            <div x-show="error" x-cloak class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="error"></div>
            <p class="text-xs text-amber-600" x-show="selectionCount > 0 && hasMixedSources()" x-cloak>
                Selected rows use different source warehouses. Use <strong>Complete pcode</strong> on a section when one warehouse stocks every missing SKU, or pick the same From warehouse per color row.
            </p>
        </div>
    </div>

    <form id="arrangement-draft-form" method="POST" action="{{ route('reports.warehouse-arrangement.draft-move') }}" class="hidden">
        @csrf
        <template x-for="(draft, idx) in draftPayload" :key="`${draft.item_id}-${draft.from_warehouse_id}`">
            <input type="hidden" name="items[][item_id]" :value="draft.item_id">
            <input type="hidden" name="items[][quantity]" :value="draft.quantity">
            <input type="hidden" name="items[][from_warehouse_id]" :value="draft.from_warehouse_id">
            <input type="hidden" name="items[][to_warehouse_id]" :value="draft.to_warehouse_id">
        </template>
    </form>

    <div class="flex flex-col gap-4 p-4 pt-3">
        @forelse($grid['parents'] as $parent)
        <section id="parent-{{ $parent['pcode'] }}" class="scroll-mt-32 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 bg-gray-50 px-4 py-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-100"
                                @click="toggleSection('{{ $parent['pcode'] }}')">
                            <span x-text="isSectionOpen('{{ $parent['pcode'] }}') ? '▼' : '▶'"></span>
                        </button>
                        <div>
                            <h2 class="font-semibold text-gray-900">{{ $parent['name'] }}</h2>
                            <p class="font-mono text-xs text-gray-500">{{ $parent['pcode'] }}</p>
                        </div>
                        <span class="text-xs text-gray-500">
                            demand <span class="font-mono font-semibold text-emerald-600">{{ $fmtNum($parent['family_demand_score']) }}</span>
                        </span>
                        <span class="text-xs text-gray-500">
                            completeness <span class="font-mono">{{ $parent['completeness_pct'] }}%</span>
                        </span>
                        <span class="text-xs text-gray-400">→ {{ $parent['to_warehouse_name'] }}</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <template x-if="parentSourceOptions('{{ $parent['pcode'] }}').length > 1">
                            <select class="rounded border border-gray-300 px-2 py-1 text-xs"
                                    @change="setParentSource('{{ $parent['pcode'] }}', Number($event.target.value))">
                                <template x-for="opt in parentSourceOptions('{{ $parent['pcode'] }}')" :key="opt.id">
                                    <option :value="opt.id" :selected="opt.id === parentSourceId('{{ $parent['pcode'] }}')" x-text="opt.name"></option>
                                </template>
                            </select>
                        </template>
                        <button type="button"
                                @click="draftParent('{{ $parent['pcode'] }}')"
                                :disabled="!canDraftParent('{{ $parent['pcode'] }}')"
                                class="rounded-lg border border-blue-600 px-3 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50 disabled:cursor-not-allowed disabled:border-gray-300 disabled:text-gray-400">
                            Complete pcode
                        </button>
                    </div>
                </div>
                <p class="mt-1 text-xs text-amber-600" x-show="!canDraftParent('{{ $parent['pcode'] }}')" x-cloak>
                    No single warehouse stocks every missing SKU under this pcode.
                </p>
            </div>
            <div class="arrangement-grid-scroll p-2" x-show="isSectionOpen('{{ $parent['pcode'] }}')">
                <div data-parent-grid="{{ $parent['pcode'] }}"></div>
            </div>
        </section>
        @empty
        <div class="rounded-xl border border-gray-200 bg-white p-8 text-center text-sm text-gray-500">
            No moves for this view — try another mode or demand window.
        </div>
        @endforelse
    </div>
    @elseif($destinations->isNotEmpty())
    <div class="p-4">
        <div class="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-600">
            No high-demand pcode families found for this warehouse in the selected window. Try a longer demand window or ensure monthly stats are recalculated.
        </div>
    </div>
    @endif
    @endif
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
function arrangementGridPage() {
    return {
        grid: @json($grid),
        layout: @json($layout),
        parentSources: {},
        openSections: new Set(@json($layout !== WarehouseArrangementService::LAYOUT_FAMILY ? collect($grid['parents'])->pluck('pcode')->all() : [])),
        tables: {},
        selectionCount: 0,
        draftPayload: [],
        error: '',

        isSectionOpen(pcode) {
            return this.openSections.has(pcode);
        },

        toggleSection(pcode) {
            if (this.openSections.has(pcode)) {
                this.openSections.delete(pcode);
            } else {
                this.openSections.add(pcode);
            }
        },

        init() {
            this.$nextTick(() => {
                for (const parent of this.grid.parents) {
                    const el = document.querySelector(`[data-parent-grid="${parent.pcode}"]`);
                    if (!el) continue;

                    const common = this.findCommonSourceForParent(parent);
                    if (common) {
                        this.parentSources[parent.pcode] = common;
                    }

                    this.tables[parent.pcode] = new Tabulator(el, {
                        data: parent.rows,
                        layout: 'fitData',
                        height: Math.max(100, (parent.rows.length + 1) * 38 + 20),
                        selectableRows: true,
                        rowHeader: {
                            formatter: 'rowSelection',
                            titleFormatter: 'rowSelection',
                            headerSort: false,
                            frozen: true,
                            width: 40,
                        },
                        columnDefaults: { headerHozAlign: 'center', hozAlign: 'right', widthGrow: 0 },
                        columns: this.buildColumns(parent.sizes, parent.pcode),
                        rowFormatter: (row) => this.formatDemandRow(row),
                    });

                    this.tables[parent.pcode].on('rowSelectionChanged', () => this.syncSelectionCount());
                }
            });
        },

        buildColumns(sizes, pcode) {
            const cols = [
                { title: 'Color', field: 'color_name', frozen: true, width: 120, widthGrow: 0, hozAlign: 'left', headerHozAlign: 'left' },
            ];

            const demandGroup = {
                key: 'demand',
                title: 'Demand',
                groupClass: 'tabulator-col-group-demand',
                formatter: (cell) => cell.getValue() ?? 0,
            };

            const sourceGroup = {
                key: 'source_stock',
                title: 'Source stock',
                groupClass: 'tabulator-col-group-source',
                formatter: (cell) => {
                    const row = cell.getRow().getData();
                    const field = cell.getField();
                    if (field === 'source_stock_total') {
                        return Object.values(row._meta || {}).reduce((sum, meta) => {
                            const src = this.chosenSource(meta, pcode);
                            return sum + (src?.source_stock ?? 0);
                        }, 0);
                    }
                    const prefix = field.replace('source_stock', '');
                    const meta = row._meta?.[prefix];
                    if (!meta) return cell.getValue() ?? 0;
                    const src = this.chosenSource(meta, pcode);
                    return src?.source_stock ?? 0;
                },
            };

            const moveGroup = {
                key: 'move_qty',
                title: 'Move qty',
                groupClass: 'tabulator-col-group-move',
                formatter: (cell) => {
                    const row = cell.getRow().getData();
                    const field = cell.getField();
                    if (field === 'move_qty_total') {
                        return Object.values(row._meta || {}).reduce((sum, meta) => {
                            const src = this.chosenSource(meta, pcode);
                            return sum + (src?.suggested_qty ?? 0);
                        }, 0);
                    }
                    const prefix = field.replace('move_qty', '');
                    const meta = row._meta?.[prefix];
                    if (!meta) return cell.getValue() ?? 0;
                    const src = this.chosenSource(meta, pcode);
                    return src?.suggested_qty ?? 0;
                },
            };

            for (const group of [demandGroup, sourceGroup, moveGroup]) {
                const children = sizes.map((size) => {
                    const prefix = this.sizePrefix(size);
                    const field = prefix + group.key;
                    return {
                        title: size,
                        field,
                        width: 58,
                        widthGrow: 0,
                        formatter: group.formatter,
                    };
                });

                if (sizes.length > 1) {
                    children.push({
                        title: 'Total',
                        field: group.key + '_total',
                        width: 64,
                        widthGrow: 0,
                        formatter: group.formatter,
                    });
                }

                cols.push({ title: group.title, cssClass: group.groupClass, columns: children });
            }

            return cols;
        },

        sizePrefix(size) {
            if (size === '—') return '';
            return size.toLowerCase().replace(/[. ]/g, '_') + '_';
        },

        formatDemandRow(row) {
            const data = row.getData();
            Object.entries(data._meta || {}).forEach(([prefix, meta]) => {
                if (!meta?.item_id) return;
                const cell = row.getCell(prefix + 'demand');
                if (cell && Number(data[prefix + 'demand'] ?? 0) > 0) {
                    cell.getElement().classList.add('arrangement-demand-cell');
                }
            });
        },

        parentByPcode(pcode) {
            return this.grid.parents.find((p) => p.pcode === pcode);
        },

        metaEntriesFromRows(rows) {
            const entries = [];
            for (const row of rows) {
                for (const [prefix, meta] of Object.entries(row._meta || {})) {
                    if (meta?.item_id) entries.push({ prefix, meta, row });
                }
            }
            return entries;
        },

        chosenSource(meta, pcode) {
            const parentWh = this.parentSources[pcode];
            if (parentWh) {
                const idx = (meta.sources || []).findIndex((s) => s.from_warehouse_id === parentWh);
                if (idx >= 0) return meta.sources[idx];
            }
            const idx = meta.chosen_source_index ?? 0;
            return meta.sources?.[idx] ?? meta.sources?.[0] ?? null;
        },

        findCommonSourceForParent(parent) {
            const entries = this.metaEntriesFromRows(parent.rows);
            if (!entries.length) return null;

            const candidates = entries[0].meta.sources?.map((s) => s.from_warehouse_id) ?? [];
            for (const whId of candidates) {
                if (entries.every((e) => (e.meta.sources || []).some((s) => s.from_warehouse_id === whId))) {
                    return whId;
                }
            }
            return null;
        },

        parentSourceId(pcode) {
            return this.parentSources[pcode] ?? this.findCommonSourceForParent(this.parentByPcode(pcode));
        },

        parentSourceOptions(pcode) {
            const parent = this.parentByPcode(pcode);
            if (!parent) return [];

            const map = new Map();
            for (const row of parent.rows) {
                for (const meta of Object.values(row._meta || {})) {
                    for (const src of meta.sources || []) {
                        map.set(src.from_warehouse_id, src.from_warehouse_name);
                    }
                }
            }

            return [...map.entries()].map(([id, name]) => ({ id, name }));
        },

        setParentSource(pcode, whId) {
            this.parentSources[pcode] = whId;
            const table = this.tables[pcode];
            if (table) {
                table.redraw(true);
                for (const row of table.getRows()) {
                    this.formatDemandRow(row);
                }
            }
        },

        canDraftParent(pcode) {
            return this.findCommonSourceForParent(this.parentByPcode(pcode)) !== null;
        },

        selectedRows() {
            const rows = [];
            for (const parent of this.grid.parents) {
                const table = this.tables[parent.pcode];
                if (!table) continue;
                rows.push(...table.getSelectedRows().map((row) => ({
                    pcode: parent.pcode,
                    data: row.getData(),
                })));
            }
            return rows;
        },

        syncSelectionCount() {
            this.selectionCount = this.selectedRows().length;
        },

        buildDraftFromEntries(entries, pcode) {
            return entries.map((e) => {
                const src = this.chosenSource(e.meta, pcode);
                return {
                    item_id: e.meta.item_id,
                    quantity: src?.suggested_qty ?? 1,
                    from_warehouse_id: src?.from_warehouse_id,
                    to_warehouse_id: e.meta.to_warehouse_id,
                };
            }).filter((d) => d.from_warehouse_id && d.to_warehouse_id);
        },

        hasMixedSources() {
            const items = this.buildDraftFromSelected();
            if (items.length <= 1) return false;
            const from = items[0].from_warehouse_id;
            return items.some((item) => item.from_warehouse_id !== from);
        },

        buildDraftFromSelected() {
            const items = [];
            for (const { pcode, data } of this.selectedRows()) {
                items.push(...this.buildDraftFromEntries(this.metaEntriesFromRows([data]), pcode));
            }
            return items;
        },

        canDraftSelected() {
            const items = this.buildDraftFromSelected();
            if (!items.length) return false;
            const from = items[0].from_warehouse_id;
            return items.every((item) => item.from_warehouse_id === from);
        },

        draftSelected() {
            this.error = '';
            const items = this.buildDraftFromSelected();
            if (!items.length) return;
            if (!this.canDraftSelected()) {
                this.error = 'Selected color rows use different source warehouses.';
                return;
            }
            this.submitDraft(items);
        },

        draftParent(pcode) {
            this.error = '';
            const parent = this.parentByPcode(pcode);
            if (!parent) return;

            const common = this.findCommonSourceForParent(parent);
            if (!common) {
                this.error = `Pcode ${pcode}: no single warehouse stocks all missing SKUs.`;
                return;
            }

            this.parentSources[pcode] = common;
            const entries = this.metaEntriesFromRows(parent.rows);
            const items = this.buildDraftFromEntries(entries, pcode);
            this.submitDraft(items);
        },

        submitDraft(items) {
            this.draftPayload = items;
            this.$nextTick(() => {
                document.getElementById('arrangement-draft-form').submit();
            });
        },
    };
}
</script>
@endpush

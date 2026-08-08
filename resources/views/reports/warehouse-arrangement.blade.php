@extends('layouts.app')

@section('title', 'Warehouse Arrangement')

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

<div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Warehouse Arrangement</h1>
            <p class="text-gray-500">Suggest moves to complete pcode families (all colors &amp; sizes) at high-demand destination warehouses.</p>
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

    @if($layout === WarehouseArrangementService::LAYOUT_FLAT && $families !== [])
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-4 py-3">
            <h2 class="text-sm font-semibold text-gray-900">Family demand (master pcode)</h2>
            <p class="text-xs text-gray-500">Sorted by net sells at this warehouse in the selected window.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <th class="px-3 py-2">Master</th>
                        <th class="px-3 py-2">Product</th>
                        <th class="px-3 py-2 text-center">Demand</th>
                        <th class="px-3 py-2 text-center">Completeness</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($families as $family)
                    <tr class="border-b">
                        <td class="px-3 py-2 font-mono font-medium">{{ $family['master'] }}</td>
                        <td class="px-3 py-2">{{ $family['name'] }}</td>
                        <td class="px-3 py-2 text-center font-mono font-semibold text-emerald-600">{{ $fmtNum($family['demand_score']) }}</td>
                        <td class="px-3 py-2 text-center">
                            {{ $family['present_count'] }}/{{ $family['total_count'] }}
                            <span class="text-gray-400">({{ $family['completeness_pct'] }}%)</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm"
         x-data="arrangementSelection(@js($suggestions), @js($layout), @js($mode))"
         id="arrangement-panel">
        <div class="border-b border-gray-100 px-4 py-3 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Suggested moves</h2>
                    <p class="text-xs text-gray-500">{{ $modeDescriptions[$mode] ?? '' }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs text-gray-500" x-show="selectedCount > 0" x-cloak>
                        <span x-text="selectedCount"></span> selected
                    </span>
                    <button type="button"
                            @click="draftMove()"
                            :disabled="!canDraft()"
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
                <span class="text-xs font-medium uppercase text-gray-500">Layout:</span>
                <a href="{{ route('reports.warehouse-arrangement', array_filter([
                    'warehouse_id' => $selectedWarehouseId,
                    'demand_days' => $demandDays,
                    'mode' => $mode,
                    'layout' => WarehouseArrangementService::LAYOUT_FLAT,
                    'min_demand' => $mode === WarehouseArrangementService::MODE_STRONG_DEMAND ? $minDemand : null,
                ])) }}"
                   class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $layout === WarehouseArrangementService::LAYOUT_FLAT ? 'bg-gray-800 text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">
                    Flat list
                </a>
                <a href="{{ route('reports.warehouse-arrangement', array_filter([
                    'warehouse_id' => $selectedWarehouseId,
                    'demand_days' => $demandDays,
                    'mode' => $mode,
                    'layout' => WarehouseArrangementService::LAYOUT_FAMILY,
                    'min_demand' => $mode === WarehouseArrangementService::MODE_STRONG_DEMAND ? $minDemand : null,
                ])) }}"
                   class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $layout === WarehouseArrangementService::LAYOUT_FAMILY ? 'bg-gray-800 text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">
                    By family
                </a>
            </div>
        </div>

        <form id="arrangement-draft-form" method="POST" action="{{ route('reports.warehouse-arrangement.draft-move') }}" class="hidden">
            @csrf
            <template x-for="(draft, idx) in draftItems()" :key="`${draft.item_id}-${draft.from_warehouse_id}`">
                <input type="hidden" name="items[][item_id]" :value="draft.item_id">
                <input type="hidden" name="items[][quantity]" :value="draft.suggested_qty">
                <input type="hidden" name="items[][from_warehouse_id]" :value="draft.from_warehouse_id">
                <input type="hidden" name="items[][to_warehouse_id]" :value="draft.to_warehouse_id">
            </template>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <th class="px-3 py-2 w-10">
                            <input type="checkbox" class="rounded border-gray-300"
                                   :checked="allSelected"
                                   @change="toggleAll($event.target.checked)"
                                   :disabled="rows.length === 0"
                                   x-show="layout === 'flat'">
                        </th>
                        <th class="px-3 py-2">Master</th>
                        <th class="px-3 py-2">SKU</th>
                        <th class="px-3 py-2">Color</th>
                        <th class="px-3 py-2">Size</th>
                        <th class="px-3 py-2 text-center">Demand</th>
                        <th class="px-3 py-2 min-w-[200px]">From warehouse</th>
                        <th class="px-3 py-2 text-center">Stock</th>
                        <th class="px-3 py-2">To</th>
                        <th class="px-3 py-2 text-center">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="rows.length === 0">
                        <tr>
                            <td colspan="10" class="px-3 py-8 text-center text-gray-500">No moves for this view — try another mode or demand window.</td>
                        </tr>
                    </template>

                    {{-- Flat layout --}}
                    <template x-if="layout === 'flat'">
                        <template x-for="row in rows" :key="row.item_id">
                            <tr class="border-b hover:bg-gray-50" :class="isSelected(row.item_id) ? 'bg-blue-50/40' : ''">
                                <td class="px-3 py-2">
                                    <input type="checkbox" class="rounded border-gray-300"
                                           :checked="isSelected(row.item_id)"
                                           @change="toggle(row.item_id, $event.target.checked)">
                                </td>
                                <td class="px-3 py-2 font-mono text-xs" x-text="row.master"></td>
                                <td class="px-3 py-2">
                                    <div class="font-medium" x-text="row.item_name"></div>
                                    <div class="font-mono text-xs text-gray-500" x-text="row.item_code"></div>
                                </td>
                                <td class="px-3 py-2" x-text="row.warna"></td>
                                <td class="px-3 py-2" x-text="row.size"></td>
                                <td class="px-3 py-2 text-center font-mono" :class="row.item_demand > 0 ? 'font-semibold text-emerald-600' : 'text-gray-400'" x-text="Number(row.item_demand).toLocaleString('id-ID')"></td>
                                <td class="px-3 py-2">
                                    <template x-if="row.sources.length === 1">
                                        <span class="text-sm" x-text="row.sources[0].from_warehouse_name"></span>
                                    </template>
                                    <template x-if="row.sources.length > 1">
                                        <select class="w-full min-w-[180px] rounded border border-gray-300 px-2 py-1.5 text-sm"
                                                x-model.number="row.chosenSourceIndex">
                                            <template x-for="(src, srcIdx) in row.sources" :key="src.from_warehouse_id">
                                                <option :value="srcIdx" x-text="`${src.from_warehouse_name} (${src.source_stock} pcs)`"></option>
                                            </template>
                                        </select>
                                    </template>
                                </td>
                                <td class="px-3 py-2 text-center font-mono text-gray-700"
                                    x-text="chosenSource(row)?.source_stock ?? '—'"></td>
                                <td class="px-3 py-2 font-medium" x-text="row.to_warehouse_name"></td>
                                <td class="px-3 py-2 text-center font-mono font-bold text-blue-600"
                                    x-text="chosenSource(row)?.suggested_qty ?? '—'"></td>
                            </tr>
                        </template>
                    </template>

                    {{-- Family layout --}}
                    <template x-if="layout === 'family'">
                        <template x-for="group in groupedFamilies" :key="group.master">
                            <tr class="border-b bg-gray-50/80">
                                <td class="px-3 py-2" colspan="10">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div class="flex flex-wrap items-center gap-3">
                                            <button type="button"
                                                    class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-100"
                                                    @click="toggleFamily(group.master)">
                                                <span x-text="isFamilyExpanded(group.master) ? '▼' : '▶'"></span>
                                            </button>
                                            <span class="font-mono font-semibold text-gray-900" x-text="group.master"></span>
                                            <span class="text-sm text-gray-600" x-text="group.master_name"></span>
                                            <span class="text-xs text-gray-500">
                                                demand <span class="font-mono font-semibold text-emerald-600" x-text="Number(group.family_demand_score).toLocaleString('id-ID')"></span>
                                            </span>
                                            <span class="text-xs text-gray-500">
                                                completeness <span class="font-mono" x-text="`${group.completeness_pct}%`"></span>
                                            </span>
                                            <span class="text-xs text-gray-400" x-text="`${group.rows.length} SKU(s)`"></span>
                                        </div>
                                        <button type="button"
                                                @click="draftFamily(group.master)"
                                                :disabled="!canDraftFamily(group.master)"
                                                class="rounded-lg border border-blue-600 px-3 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50 disabled:cursor-not-allowed disabled:border-gray-300 disabled:text-gray-400">
                                            Complete family
                                        </button>
                                    </div>
                                    <p class="mt-1 text-xs text-amber-600" x-show="!canDraftFamily(group.master)" x-cloak>
                                        No single warehouse stocks every missing SKU in this family.
                                    </p>
                                </td>
                            </tr>
                            <template x-if="isFamilyExpanded(group.master)">
                                <template x-for="row in group.rows" :key="row.item_id">
                                    <tr class="border-b hover:bg-gray-50" :class="isSelected(row.item_id) ? 'bg-blue-50/40' : ''">
                                        <td class="px-3 py-2">
                                            <input type="checkbox" class="rounded border-gray-300"
                                                   :checked="isSelected(row.item_id)"
                                                   @change="toggle(row.item_id, $event.target.checked)">
                                        </td>
                                        <td class="px-3 py-2 font-mono text-xs text-gray-400" x-text="row.master"></td>
                                        <td class="px-3 py-2">
                                            <div class="font-medium" x-text="row.item_name"></div>
                                            <div class="font-mono text-xs text-gray-500" x-text="row.item_code"></div>
                                        </td>
                                        <td class="px-3 py-2" x-text="row.warna"></td>
                                        <td class="px-3 py-2" x-text="row.size"></td>
                                        <td class="px-3 py-2 text-center font-mono" :class="row.item_demand > 0 ? 'font-semibold text-emerald-600' : 'text-gray-400'" x-text="Number(row.item_demand).toLocaleString('id-ID')"></td>
                                        <td class="px-3 py-2">
                                            <template x-if="row.sources.length === 1">
                                                <span class="text-sm" x-text="row.sources[0].from_warehouse_name"></span>
                                            </template>
                                            <template x-if="row.sources.length > 1">
                                                <select class="w-full min-w-[180px] rounded border border-gray-300 px-2 py-1.5 text-sm"
                                                        x-model.number="row.chosenSourceIndex">
                                                    <template x-for="(src, srcIdx) in row.sources" :key="src.from_warehouse_id">
                                                        <option :value="srcIdx" x-text="`${src.from_warehouse_name} (${src.source_stock} pcs)`"></option>
                                                    </template>
                                                </select>
                                            </template>
                                        </td>
                                        <td class="px-3 py-2 text-center font-mono text-gray-700"
                                            x-text="chosenSource(row)?.source_stock ?? '—'"></td>
                                        <td class="px-3 py-2 font-medium" x-text="row.to_warehouse_name"></td>
                                        <td class="px-3 py-2 text-center font-mono font-bold text-blue-600"
                                            x-text="chosenSource(row)?.suggested_qty ?? '—'"></td>
                                    </tr>
                                </template>
                            </template>
                        </template>
                    </template>
                </tbody>
            </table>
        </div>
        <p class="border-t border-gray-100 px-4 py-2 text-xs text-amber-600" x-show="selectedCount > 0 && hasMixedSources()" x-cloak>
            Selected rows use different source warehouses. Pick the same From warehouse on every selected row, or use Complete family when one warehouse stocks all SKUs.
        </p>
        <p class="border-t border-gray-100 px-4 py-2 text-xs text-red-600" x-show="familyDraftError" x-text="familyDraftError" x-cloak></p>
    </div>
    @else
    <div class="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-600">
        No high-demand pcode families found for this warehouse in the selected window. Try a longer demand window or ensure monthly stats are recalculated.
    </div>
    @endif
    @endif
</div>

@push('scripts')
<script>
function arrangementSelection(suggestions, layout, mode) {
    return {
        layout,
        mode,
        rows: suggestions.map(row => ({ ...row, chosenSourceIndex: 0 })),
        selected: new Set(),
        expandedFamilies: new Set(suggestions.map(r => r.master)),
        familyDraftError: '',
        get selectedCount() { return this.selected.size; },
        get allSelected() {
            return this.rows.length > 0 && this.selected.size === this.rows.length;
        },
        get groupedFamilies() {
            const map = new Map();
            this.rows.forEach(row => {
                if (!map.has(row.master)) {
                    map.set(row.master, {
                        master: row.master,
                        master_name: row.master_name,
                        family_demand_score: row.family_demand_score,
                        completeness_pct: row.completeness_pct,
                        rows: [],
                    });
                }
                map.get(row.master).rows.push(row);
            });
            return [...map.values()];
        },
        chosenSource(row) {
            return row.sources?.[row.chosenSourceIndex] ?? row.sources?.[0] ?? null;
        },
        isSelected(itemId) { return this.selected.has(itemId); },
        toggle(itemId, checked) {
            if (checked) this.selected.add(itemId);
            else this.selected.delete(itemId);
        },
        toggleAll(checked) {
            this.selected = checked
                ? new Set(this.rows.map(r => r.item_id))
                : new Set();
        },
        isFamilyExpanded(master) {
            return this.expandedFamilies.has(master);
        },
        toggleFamily(master) {
            if (this.expandedFamilies.has(master)) {
                this.expandedFamilies.delete(master);
            } else {
                this.expandedFamilies.add(master);
            }
        },
        findCommonSourceWarehouse(rows) {
            if (!rows.length) return null;
            const candidates = rows[0].sources.map(s => s.from_warehouse_id);
            for (const whId of candidates) {
                if (rows.every(row => row.sources.some(s => s.from_warehouse_id === whId))) {
                    return whId;
                }
            }
            return null;
        },
        canDraftFamily(master) {
            const group = this.groupedFamilies.find(g => g.master === master);
            if (!group) return false;
            return this.findCommonSourceWarehouse(group.rows) !== null;
        },
        draftFamily(master) {
            this.familyDraftError = '';
            const group = this.groupedFamilies.find(g => g.master === master);
            if (!group) return;

            const commonWh = this.findCommonSourceWarehouse(group.rows);
            if (!commonWh) {
                this.familyDraftError = `Family ${master}: no single warehouse stocks all missing SKUs. Pick sources manually or split into multiple moves.`;
                return;
            }

            group.rows.forEach(row => {
                const idx = row.sources.findIndex(s => s.from_warehouse_id === commonWh);
                if (idx >= 0) row.chosenSourceIndex = idx;
            });
            this.selected = new Set(group.rows.map(r => r.item_id));
            this.draftMove();
        },
        draftItems() {
            return [...this.selected].map(itemId => {
                const row = this.rows.find(r => r.item_id === itemId);
                const src = this.chosenSource(row);
                return {
                    item_id: row.item_id,
                    suggested_qty: src?.suggested_qty ?? 1,
                    from_warehouse_id: src?.from_warehouse_id,
                    to_warehouse_id: row.to_warehouse_id,
                };
            });
        },
        hasMixedSources() {
            const items = this.draftItems();
            if (items.length <= 1) return false;
            const from = items[0].from_warehouse_id;
            return items.some(item => item.from_warehouse_id !== from);
        },
        canDraft() {
            const items = this.draftItems();
            if (items.length === 0) return false;
            if (this.hasMixedSources()) return false;
            return items.every(item => item.from_warehouse_id && item.to_warehouse_id);
        },
        draftMove() {
            if (!this.canDraft()) return;
            document.getElementById('arrangement-draft-form').submit();
        },
    };
}
</script>
@endpush
@endsection

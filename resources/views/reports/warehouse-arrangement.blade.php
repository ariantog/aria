@extends('layouts.app')

@section('title', 'Warehouse Arrangement')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'Reports', 'href' => '#'],
    ['title' => 'Warehouse Arrangement', 'href' => route('reports.warehouse-arrangement')],
];
$fmtNum = fn ($v) => number_format((float) $v, 0, ',', '.');
$queryBase = array_filter([
    'warehouse_id' => $selectedWarehouseId,
    'demand_days' => $demandDays,
]);
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

    @if($families !== [])
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
         x-data="arrangementSelection(@js($suggestions))">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Suggested moves</h2>
                <p class="text-xs text-gray-500">Select rows with the same source warehouse, then draft one move with all items prefilled. Sources are physical warehouses only.</p>
            </div>
            <div class="flex items-center gap-2">
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
        <form id="arrangement-draft-form" method="POST" action="{{ route('reports.warehouse-arrangement.draft-move') }}" class="hidden">
            @csrf
            <template x-for="(row, idx) in selectedRows()" :key="row.item_id">
                <input type="hidden" name="items[][item_id]" :value="row.item_id">
                <input type="hidden" name="items[][quantity]" :value="row.suggested_qty">
                <input type="hidden" name="items[][from_warehouse_id]" :value="row.from_warehouse_id">
                <input type="hidden" name="items[][to_warehouse_id]" :value="row.to_warehouse_id">
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
                                   :disabled="suggestions.length === 0">
                        </th>
                        <th class="px-3 py-2">Master</th>
                        <th class="px-3 py-2">SKU</th>
                        <th class="px-3 py-2">Color</th>
                        <th class="px-3 py-2">Size</th>
                        <th class="px-3 py-2 text-center">Demand</th>
                        <th class="px-3 py-2">From</th>
                        <th class="px-3 py-2">To</th>
                        <th class="px-3 py-2 text-center">Src stock</th>
                        <th class="px-3 py-2 text-center">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($suggestions as $idx => $s)
                    <tr class="border-b hover:bg-gray-50" :class="isSelected({{ $idx }}) ? 'bg-blue-50/40' : ''">
                        <td class="px-3 py-2">
                            <input type="checkbox" class="rounded border-gray-300"
                                   :checked="isSelected({{ $idx }})"
                                   @change="toggle({{ $idx }}, $event.target.checked)">
                        </td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $s['master'] }}</td>
                        <td class="px-3 py-2">
                            <div class="font-medium">{{ $s['item_name'] }}</div>
                            <div class="font-mono text-xs text-gray-500">{{ $s['item_code'] }}</div>
                        </td>
                        <td class="px-3 py-2">{{ $s['warna'] }}</td>
                        <td class="px-3 py-2">{{ $s['size'] }}</td>
                        <td class="px-3 py-2 text-center font-mono">{{ $fmtNum($s['item_demand']) }}</td>
                        <td class="px-3 py-2">{{ $s['from_warehouse_name'] }}</td>
                        <td class="px-3 py-2 font-medium">{{ $s['to_warehouse_name'] }}</td>
                        <td class="px-3 py-2 text-center font-mono">{{ $s['source_stock'] }}</td>
                        <td class="px-3 py-2 text-center font-mono font-bold text-blue-600">{{ $s['suggested_qty'] }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-3 py-8 text-center text-gray-500">No moves suggested — destination already has all SKUs with stock, or no source stock available.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="border-t border-gray-100 px-4 py-2 text-xs text-gray-500" x-show="selectedCount > 0 && !canDraft()" x-cloak>
            Selected items must share the same source and destination warehouses.
        </p>
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
function arrangementSelection(suggestions) {
    return {
        suggestions,
        selected: new Set(),
        get selectedCount() { return this.selected.size; },
        get allSelected() {
            return this.suggestions.length > 0 && this.selected.size === this.suggestions.length;
        },
        isSelected(idx) { return this.selected.has(idx); },
        toggle(idx, checked) {
            if (checked) this.selected.add(idx);
            else this.selected.delete(idx);
        },
        toggleAll(checked) {
            this.selected = checked
                ? new Set(this.suggestions.map((_, i) => i))
                : new Set();
        },
        selectedRows() {
            return [...this.selected].sort((a, b) => a - b).map(i => this.suggestions[i]);
        },
        canDraft() {
            const rows = this.selectedRows();
            if (rows.length === 0) return false;
            const from = rows[0].from_warehouse_id;
            const to = rows[0].to_warehouse_id;
            return rows.every(r => r.from_warehouse_id === from && r.to_warehouse_id === to);
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

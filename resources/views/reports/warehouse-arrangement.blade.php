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
$queryParams = fn (array $extra = []) => array_filter(array_merge([
    'warehouse_id' => $selectedWarehouseId,
    'demand_days' => $demandDays,
    'mode' => $mode,
    'search' => $search ?: null,
    'page' => $page > 1 ? $page : null,
], $extra));
@endphp

<div x-data="arrangementPage()" x-init="init()">
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
            @if($syncedAt)
            <p class="mt-3 text-xs text-gray-500">
                Cached data synced {{ $syncedAt->diffForHumans() }}.
                @if($stale)
                <span class="text-amber-700">May be stale — run <code class="text-xs">app:sync-warehouse-arrangement</code> or wait for the daily cron.</span>
                @endif
            </p>
            @else
            <p class="mt-3 text-xs text-amber-700">No cached data yet. Run <code class="text-xs">php artisan app:sync-warehouse-arrangement</code> after configuring source warehouses.</p>
            @endif
        </div>
        @endif
    </div>

    @if($destinations->isNotEmpty())
    <div class="sticky top-0 z-20 border-b border-gray-200 bg-gray-50/95 px-4 py-3 shadow-sm backdrop-blur supports-[backdrop-filter]:bg-gray-50/90">
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
                <span class="ml-2 text-xs text-gray-500">
                    @if($mode === WarehouseArrangementService::MODE_DEMAND)
                    Missing SKUs with sales in the selected window.
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
            <p class="text-xs text-gray-500">Page {{ $page }} of {{ $lastPage }} · {{ $totalPcodes }} color pcode(s)</p>
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
        @forelse($sections as $section)
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 bg-gray-50 px-4 py-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="font-mono font-semibold text-gray-900">{{ $section['pcode'] }}</span>
                        <span class="text-gray-600">{{ $section['name'] }}</span>
                        @if($section['warna'] && $section['warna'] !== '—')
                        <span class="text-gray-500">· {{ $section['warna'] }}</span>
                        @endif
                        <span class="rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                            demand {{ number_format($section['family_demand_score'], 0, ',', '.') }}
                        </span>
                        <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                            {{ $section['present_count'] }}/{{ $section['total_count'] }} sizes ({{ $section['completeness_pct'] }}%)
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="selectAllInPcode('{{ $section['pcode'] }}')"
                                class="rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                            Select all
                        </button>
                        <button type="button" @click="applyWarehouseToPcode('{{ $section['pcode'] }}')"
                                class="rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                            Same WH as first selected
                        </button>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto p-3">
                <table class="min-w-full text-xs">
                    <thead>
                        <tr class="text-center text-gray-500">
                            @foreach($section['sizes'] as $size)
                            <th class="px-2 py-2 font-medium">{{ $size }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            @foreach($section['sizes'] as $size)
                            @php $cell = $section['cells'][$size] ?? null; @endphp
                            <td class="align-top px-2 py-2 min-w-[140px]">
                                @if($cell && ($cell['moveable'] ?? false))
                                <div class="rounded-lg border border-gray-200 p-2"
                                     :class="isSelected({{ $cell['item_id'] }}) ? 'border-blue-300 bg-blue-50' : 'bg-white'">
                                    <label class="flex items-center gap-1">
                                        <input type="checkbox"
                                               class="rounded border-gray-300"
                                               data-item-id="{{ $cell['item_id'] }}"
                                               @change="toggle({{ $cell['item_id'] }}, $event.target.checked)">
                                        <span class="font-semibold text-emerald-700">D {{ number_format($cell['demand'], 0, ',', '.') }}</span>
                                    </label>
                                    <select class="mt-2 w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-gray-900"
                                            data-item-id="{{ $cell['item_id'] }}"
                                            @change="setSourceIndex({{ $cell['item_id'] }}, $event.target.value)">
                                        @foreach($cell['sources'] as $idx => $src)
                                        <option value="{{ $idx }}">{{ $src['from_warehouse_name'] }} ({{ $src['source_stock'] }})</option>
                                        @endforeach
                                    </select>
                                    @php $firstSrc = $cell['sources'][0] ?? null; @endphp
                                    <div class="mt-1 text-gray-500">
                                        Stock {{ $firstSrc['source_stock'] ?? 0 }} · Move {{ $firstSrc['suggested_qty'] ?? 0 }}
                                    </div>
                                </div>
                                @elseif($cell)
                                <div class="py-6 text-center text-gray-500">
                                    @if(($cell['dest_stock'] ?? 0) > 0)
                                    OK · Stock {{ $cell['dest_stock'] }}
                                    @else
                                    No move
                                    @endif
                                </div>
                                @else
                                <div class="py-6 text-center text-gray-400">—</div>
                                @endif
                            </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        @empty
        <div class="rounded-xl border border-gray-200 bg-white p-8 text-center text-sm text-gray-500">
            @if($search)
            No pcode matches “{{ $search }}” for this view.
            @else
            Nothing to move — stock looks OK here, configure source warehouses and run sync, or return after drafting earlier batches.
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
<script>
function arrangementPage() {
    const sections = @json($sections);
    const destinationId = {{ (int) $selectedWarehouseId }};

    const cells = {};
    for (const section of sections) {
        for (const [size, cell] of Object.entries(section.cells || {})) {
            if (!cell.moveable) continue;
            cells[cell.item_id] = {
                item_id: cell.item_id,
                pcode: section.pcode,
                selected: false,
                chosen_source_index: 0,
                sources: cell.sources || [],
                to_warehouse_id: destinationId,
            };
        }
    }

    return {
        cells,
        draftPayload: [],
        error: '',

        init() {},

        isSelected(itemId) {
            return this.cells[itemId]?.selected ?? false;
        },

        toggle(itemId, selected) {
            if (this.cells[itemId]) this.cells[itemId].selected = selected;
        },

        setSourceIndex(itemId, index) {
            if (this.cells[itemId]) this.cells[itemId].chosen_source_index = Number(index);
        },

        chosenSource(cell) {
            const idx = cell.chosen_source_index ?? 0;
            return cell.sources?.[idx] ?? cell.sources?.[0] ?? null;
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
            for (const cell of this.cellsForPcode(pcode)) cell.selected = true;
        },

        applyWarehouseToPcode(pcode) {
            const list = this.cellsForPcode(pcode);
            const first = list.find((c) => c.selected) ?? list[0];
            if (!first) return;
            const whIdx = first.chosen_source_index ?? 0;
            for (const cell of list) {
                if ((cell.sources || []).length > whIdx) cell.chosen_source_index = whIdx;
            }
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

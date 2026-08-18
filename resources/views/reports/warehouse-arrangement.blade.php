@extends('layouts.app')

@section('title', 'Warehouse Arrangement')

@push('head-css')
<style>
    .arrangement-size-table {
        table-layout: fixed;
        width: 100%;
    }
    .arrangement-size-table th,
    .arrangement-size-table td {
        vertical-align: middle;
        border-right: 1px solid #e5e7eb;
    }
    .arrangement-size-table th:last-child,
    .arrangement-size-table td:last-child {
        border-right: none;
    }
    .arrangement-size-table .arrangement-col-dest {
        background: #d1fae5;
    }
    .arrangement-size-table .arrangement-col-wh1 {
        background: #dbeafe;
    }
    .arrangement-size-table .arrangement-col-wh2 {
        background: #e0e7ff;
    }
    .arrangement-size-table .arrangement-row-label {
        width: 7rem;
        min-width: 7rem;
        font-weight: 600;
        color: #4b5563;
        background: #f9fafb;
    }
    .arrangement-size-table .arrangement-size-cell {
        width: 1%;
        text-align: center;
        padding: 0.5rem 0.35rem;
    }
    .dark .arrangement-size-table th,
    .dark .arrangement-size-table td {
        border-right-color: #4b5563;
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
$queryParams = fn (array $extra = []) => array_filter(array_merge([
    'warehouse_id' => $selectedWarehouseId,
    'demand_days' => $demandDays,
    'mode' => $mode,
    'search' => $search ?: null,
    'source_wh2_id' => $selectedSourceWarehouse2Id ?? null,
    'page' => $page > 1 ? $page : null,
], $extra));
@endphp

<div x-data="arrangementPage()">
    <div class="flex flex-col gap-4 p-4 pb-0">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Warehouse Arrangement</h1>
                <p class="text-gray-500">Compare destination stock with two source warehouses, then draft move transactions like Restock.</p>
            </div>
            @if($selectedWarehouseId && $destinationName)
            <div class="flex flex-wrap items-center gap-2">
                <form method="POST" action="{{ route('reports.warehouse-arrangement.refresh') }}" class="inline">
                    @csrf
                    <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">
                    <input type="hidden" name="demand_days" value="{{ $demandDays }}">
                    <input type="hidden" name="mode" value="{{ $mode }}">
                    @if($selectedSourceWarehouse2Id)
                    <input type="hidden" name="source_wh2_id" value="{{ $selectedSourceWarehouse2Id }}">
                    @endif
                    <button type="submit"
                            @disabled($activeRefreshJob)
                            class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium {{ $activeRefreshJob ? 'cursor-not-allowed border-gray-200 bg-gray-100 text-gray-400' : 'border-amber-300 bg-amber-50 text-amber-900 hover:bg-amber-100' }}">
                        @if($activeRefreshJob)
                        Rebuild in progress…
                        @else
                        Rebuild stats &amp; refresh
                        @endif
                    </button>
                </form>
                <a href="{{ route('reports.warehouse-arrangement.export', $queryParams()) }}"
                   class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Export Excel
                </a>
            </div>
            @endif
        </div>

        @if(($flash['success'] ?? null))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ $flash['success'] }}</div>
        @endif

        @if(($flash['error'] ?? null))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $flash['error'] }}</div>
        @endif

        @if($activeRefreshJob)
        <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900"
             x-data="refreshProgress(@js([
                'tickUrl' => route('reports.warehouse-arrangement.tick-refresh'),
                'warehouseId' => $selectedWarehouseId,
                'destinationName' => $destinationName,
                'initiatedBy' => $activeRefreshJob->initiatedByLabel(),
                'status' => $activeRefreshJob->status,
                'phase' => $activeRefreshJob->phase,
                'itemCursor' => $activeRefreshJob->item_cursor,
                'totalItems' => $activeRefreshJob->total_items,
                'progressPercent' => $activeRefreshJob->progressPercent(),
             ]))">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-semibold">Rebuild running for {{ $destinationName ?? 'this warehouse' }}</p>
                    <p class="mt-1 text-blue-800" x-text="progressLine()"></p>
                    <p class="mt-1 text-xs text-blue-700" x-show="!busy" x-cloak>
                        This page drives the rebuild while it stays open — about 300 SKUs every 15 seconds.
                    </p>
                    <p class="mt-1 text-xs text-amber-800" x-show="busy" x-cloak>Another batch is already running…</p>
                    <p class="mt-1 text-xs text-red-700" x-show="errorMessage" x-text="errorMessage" x-cloak></p>
                </div>
                <form method="POST" action="{{ route('reports.warehouse-arrangement.cancel-refresh') }}" class="shrink-0"
                      onsubmit="return confirm('Cancel this rebuild?');">
                    @csrf
                    <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">
                    <input type="hidden" name="demand_days" value="{{ $demandDays }}">
                    <input type="hidden" name="mode" value="{{ $mode }}">
                    <button type="submit"
                            class="rounded-lg border border-red-300 bg-white px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">
                        Cancel rebuild
                    </button>
                </form>
            </div>
        </div>
        @elseif($lastRefreshJob)
        <div class="rounded-lg border px-4 py-3 text-sm {{ $lastRefreshJob->status === 'completed' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-red-200 bg-red-50 text-red-900' }}">
            <p class="font-semibold">
                Last rebuild {{ $lastRefreshJob->status === 'completed' ? 'completed' : 'failed' }}
                @if($lastRefreshJob->completed_at)
                · {{ $lastRefreshJob->completed_at->diffForHumans() }}
                @endif
            </p>
            <p class="mt-1">
                Run by {{ $lastRefreshJob->initiatedByLabel() }}.
                @if($lastRefreshJob->status === 'completed')
                {{ $lastRefreshJob->result_message }}
                @else
                {{ $lastRefreshJob->error_message }}
                @endif
            </p>
        </div>
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
                <span class="text-amber-700">May be stale — use <strong>Rebuild stats &amp; refresh</strong> or wait for the daily cron.</span>
                @endif
            </p>
            @else
            <p class="mt-3 text-xs text-amber-700">No cached data yet. Tick <strong>Source warehouses</strong> on this destination, then click <strong>Rebuild stats &amp; refresh</strong> (may take several minutes).</p>
            @endif
            @if($cacheDiagnostics)
            <p class="mt-2 text-xs text-gray-500">
                Cache: {{ $cacheDiagnostics['monthly_stat_rows'] }} monthly stat rows ·
                {{ $cacheDiagnostics['source_warehouses'] }} source warehouse(s) ·
                {{ $cacheDiagnostics['candidates'] }} candidate SKU(s) ·
                {{ $cacheDiagnostics['candidates_with_sources'] }} with source stock ·
                {{ $cacheDiagnostics['snapshots'] }} pcode snapshot(s)
            </p>
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
                @if($search)
                <input type="hidden" name="search" value="{{ $search }}">
                @endif
                <div>
                    <label for="source_wh2_id" class="mb-1 block text-xs font-medium uppercase text-gray-500">Warehouse 2</label>
                    <select id="source_wh2_id" name="source_wh2_id" class="min-w-[180px] rounded-lg border border-gray-300 px-3 py-1.5 text-sm" onchange="this.form.submit()">
                        <option value="">—</option>
                        @foreach($sourceWarehouses as $wh)
                        @if(($sourceWarehouse1['id'] ?? null) !== $wh['id'])
                        <option value="{{ $wh['id'] }}" @selected($selectedSourceWarehouse2Id === $wh['id'])>{{ $wh['name'] }}</option>
                        @endif
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="search" class="mb-1 block text-xs font-medium uppercase text-gray-500">Search pcode</label>
                    <input id="search" name="search" type="search" value="{{ $search }}" placeholder="CX90028-02"
                           class="min-w-[160px] rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                </div>
                <button type="submit" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Search</button>
            </form>

            <div class="flex flex-wrap items-center gap-2">
                @if($sourceWarehouse1 && $destinationName)
                <button type="button"
                        @click="draftFromWarehouse(1)"
                        :disabled="selectedCountWh1() === 0"
                        class="rounded-lg border border-blue-300 bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-900 hover:bg-blue-100 disabled:opacity-50">
                    {{ $sourceWarehouse1['name'] }} → {{ $destinationName }}
                    <span x-show="selectedCountWh1() > 0" x-cloak>(<span x-text="selectedCountWh1()"></span>)</span>
                </button>
                @endif
                @if($sourceWarehouse2 && $destinationName)
                <button type="button"
                        @click="draftFromWarehouse(2)"
                        :disabled="selectedCountWh2() === 0"
                        class="rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-sm font-medium text-indigo-900 hover:bg-indigo-100 disabled:opacity-50">
                    {{ $sourceWarehouse2['name'] }} → {{ $destinationName }}
                    <span x-show="selectedCountWh2() > 0" x-cloak>(<span x-text="selectedCountWh2()"></span>)</span>
                </button>
                @endif
                <span class="text-xs text-gray-500" x-show="selectedCount() > 0" x-cloak>
                    <span x-text="selectedCount()"></span> cell(s) selected
                </span>
                <button type="button" x-show="selectedCount() > 0" x-cloak
                        @click="clearSelection()"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                    Clear selection
                </button>
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
            <div>
                <input type="hidden" :name="`items[${idx}][item_id]`" :value="draft.item_id">
                <input type="hidden" :name="`items[${idx}][quantity]`" :value="draft.quantity">
                <input type="hidden" :name="`items[${idx}][from_warehouse_id]`" :value="draft.from_warehouse_id">
                <input type="hidden" :name="`items[${idx}][to_warehouse_id]`" :value="draft.to_warehouse_id">
            </div>
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
                            demand {{ number_format($section['family_demand_score'], 0, ',', '.') }} <span class="font-normal text-emerald-600">(365d)</span>
                        </span>
                        <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                            {{ $section['present_count'] }}/{{ $section['total_count'] }} sizes ({{ $section['completeness_pct'] }}%)
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="toggleAllInPcode('{{ $section['pcode'] }}')"
                                class="rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                            <span x-text="allSelectedInPcode('{{ $section['pcode'] }}') ? 'Clear all' : 'Select all'"></span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto p-3">
                <table class="arrangement-size-table text-xs">
                    <thead>
                        <tr class="text-center text-gray-700">
                            <th class="arrangement-row-label px-2 py-2"></th>
                            <th colspan="{{ count($section['sizes']) }}" class="arrangement-col-dest px-2 py-2 font-semibold">{{ $destinationName ?? 'Destination' }}</th>
                            @if($sourceWarehouse1)
                            <th colspan="{{ count($section['sizes']) }}" class="arrangement-col-wh1 px-2 py-2 font-semibold">{{ $sourceWarehouse1['name'] }}</th>
                            @endif
                            @if($sourceWarehouse2)
                            <th colspan="{{ count($section['sizes']) }}" class="arrangement-col-wh2 px-2 py-2 font-semibold">{{ $sourceWarehouse2['name'] }}</th>
                            @endif
                        </tr>
                        <tr class="text-center text-gray-500">
                            <th class="arrangement-row-label px-2 py-1"></th>
                            @foreach($section['sizes'] as $size)
                            <th class="arrangement-size-cell arrangement-col-dest font-medium">{{ $size }}</th>
                            @endforeach
                            @if($sourceWarehouse1)
                            @foreach($section['sizes'] as $size)
                            <th class="arrangement-size-cell arrangement-col-wh1 font-medium">{{ $size }}</th>
                            @endforeach
                            @endif
                            @if($sourceWarehouse2)
                            @foreach($section['sizes'] as $size)
                            <th class="arrangement-size-cell arrangement-col-wh2 font-medium">{{ $size }}</th>
                            @endforeach
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="arrangement-row-label px-2 py-2 text-right">Demand</td>
                            @foreach($section['sizes'] as $size)
                            @php $cell = $section['cells'][$size] ?? null; @endphp
                            <td class="arrangement-size-cell arrangement-col-dest text-emerald-700">
                                @if($cell && ($cell['demand'] ?? 0) > 0)
                                {{ number_format($cell['demand'], 0, ',', '.') }}
                                @else
                                —
                                @endif
                            </td>
                            @endforeach
                            @if($sourceWarehouse1)
                            @foreach($section['sizes'] as $size)
                            <td class="arrangement-size-cell arrangement-col-wh1">—</td>
                            @endforeach
                            @endif
                            @if($sourceWarehouse2)
                            @foreach($section['sizes'] as $size)
                            <td class="arrangement-size-cell arrangement-col-wh2">—</td>
                            @endforeach
                            @endif
                        </tr>
                        <tr>
                            <td class="arrangement-row-label px-2 py-2 text-right">Stock</td>
                            @foreach($section['sizes'] as $size)
                            @php $cell = $section['cells'][$size] ?? null; @endphp
                            <td class="arrangement-size-cell arrangement-col-dest font-medium text-gray-800">
                                @if($cell)
                                {{ (int) ($cell['dest_stock'] ?? 0) }}
                                @else
                                —
                                @endif
                            </td>
                            @endforeach
                            @if($sourceWarehouse1)
                            @foreach($section['sizes'] as $size)
                            @php $cell = $section['cells'][$size] ?? null; @endphp
                            <td class="arrangement-size-cell arrangement-col-wh1">
                                @if($cell && ($cell['moveable_wh1'] ?? false))
                                <label class="flex cursor-pointer items-center justify-center gap-1">
                                    <input type="checkbox" class="rounded border-gray-300"
                                           x-model="cells[{{ $cell['item_id'] }}].selected_wh1">
                                    <span class="font-semibold">{{ $cell['wh1_stock'] }}</span>
                                </label>
                                <div class="mt-1" x-show="cells[{{ $cell['item_id'] }}]?.selected_wh1" x-cloak>
                                    <input type="number" min="1" :max="{{ $cell['wh1_stock'] }}"
                                           class="mx-auto w-14 rounded border border-gray-300 px-1 py-0.5 text-center"
                                           x-model.number="cells[{{ $cell['item_id'] }}].qty_wh1">
                                </div>
                                @elseif($cell)
                                {{ (int) ($cell['wh1_stock'] ?? 0) }}
                                @else
                                —
                                @endif
                            </td>
                            @endforeach
                            @endif
                            @if($sourceWarehouse2)
                            @foreach($section['sizes'] as $size)
                            @php $cell = $section['cells'][$size] ?? null; @endphp
                            <td class="arrangement-size-cell arrangement-col-wh2">
                                @if($cell && ($cell['moveable_wh2'] ?? false))
                                <label class="flex cursor-pointer items-center justify-center gap-1">
                                    <input type="checkbox" class="rounded border-gray-300"
                                           x-model="cells[{{ $cell['item_id'] }}].selected_wh2">
                                    <span class="font-semibold">{{ $cell['wh2_stock'] }}</span>
                                </label>
                                <div class="mt-1" x-show="cells[{{ $cell['item_id'] }}]?.selected_wh2" x-cloak>
                                    <input type="number" min="1" :max="{{ $cell['wh2_stock'] }}"
                                           class="mx-auto w-14 rounded border border-gray-300 px-1 py-0.5 text-center"
                                           x-model.number="cells[{{ $cell['item_id'] }}].qty_wh2">
                                </div>
                                @elseif($cell)
                                {{ (int) ($cell['wh2_stock'] ?? 0) }}
                                @else
                                —
                                @endif
                            </td>
                            @endforeach
                            @endif
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
            <p>Nothing to show for this view.</p>
            @if($cacheDiagnostics)
            <ul class="mt-4 space-y-1 text-left text-xs text-gray-600">
                @if($cacheDiagnostics['monthly_stat_rows'] === 0)
                <li>· No monthly sell stats for this warehouse — click <strong>Rebuild stats &amp; refresh</strong>.</li>
                @endif
                @if($cacheDiagnostics['source_warehouses'] === 0)
                <li>· No source warehouses configured — edit this destination and tick <strong>Source warehouses</strong>.</li>
                @endif
                @if($cacheDiagnostics['candidates'] === 0 && $cacheDiagnostics['monthly_stat_rows'] > 0)
                <li>· Stats exist but no missing SKUs with demand (destination may already be stocked, or no sales in the last 365 days).</li>
                @endif
                @if($cacheDiagnostics['candidates'] > 0 && $cacheDiagnostics['candidates_with_sources'] === 0)
                <li>· {{ $cacheDiagnostics['candidates'] }} missing SKU(s) found but none have stock at configured source warehouses.</li>
                @endif
                @if($mode === WarehouseArrangementService::MODE_DEMAND && $cacheDiagnostics['candidates_with_sources'] > 0)
                <li>· Try another demand window (30/90/180 days) if sales are older than {{ $demandDays }} days.</li>
                @endif
            </ul>
            @endif
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
function refreshProgress(config) {
    return {
        tickUrl: config.tickUrl,
        warehouseId: config.warehouseId,
        initiatedBy: config.initiatedBy,
        status: config.status,
        phase: config.phase,
        itemCursor: config.itemCursor,
        totalItems: config.totalItems,
        progressPercent: config.progressPercent,
        busy: false,
        polling: false,
        errorMessage: '',

        init() {
            this.runTick();
            setInterval(() => this.runTick(), 15000);
        },

        phaseLabel() {
            return this.phase === 'sync' ? 'refreshing arrangement cache' : 'rebuilding monthly stats';
        },

        progressLine() {
            let line = `Started by ${this.initiatedBy} · Phase: ${this.phaseLabel()}`;
            if (this.phase === 'stats' && this.totalItems > 0) {
                line += ` · ${Number(this.itemCursor).toLocaleString('id-ID')}/${Number(this.totalItems).toLocaleString('id-ID')} SKU(s) · ${this.progressPercent}%`;
            }
            return line;
        },

        async runTick() {
            if (this.polling) return;
            this.polling = true;
            this.errorMessage = '';

            try {
                const response = await fetch(this.tickUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ warehouse_id: this.warehouseId }),
                });

                const data = await response.json();
                this.busy = !!data.busy;

                if (!data.busy) {
                    this.status = data.status ?? this.status;
                    this.phase = data.phase ?? this.phase;
                    this.itemCursor = data.item_cursor ?? this.itemCursor;
                    this.totalItems = data.total_items ?? this.totalItems;
                    this.progressPercent = data.progress_percent ?? this.progressPercent;
                }

                if (data.failed) {
                    this.errorMessage = data.error_message ?? 'Rebuild failed.';
                }

                if (data.done || data.failed) {
                    window.location.reload();
                }
            } catch (error) {
                this.errorMessage = 'Could not reach the server to continue the rebuild.';
            } finally {
                this.polling = false;
            }
        },
    };
}

function arrangementPage() {
    const sections = @json($sections);
    const destinationId = {{ (int) $selectedWarehouseId }};
    const sourceWarehouse1Id = @json($sourceWarehouse1['id'] ?? null);
    const sourceWarehouse2Id = @json($sourceWarehouse2['id'] ?? null);

    const cells = {};
    for (const section of sections) {
        for (const cell of Object.values(section.cells || {})) {
            if (!cell.moveable_wh1 && !cell.moveable_wh2) continue;
            cells[cell.item_id] = {
                item_id: cell.item_id,
                pcode: section.pcode,
                selected_wh1: false,
                selected_wh2: false,
                qty_wh1: cell.suggested_qty_wh1 ?? 1,
                qty_wh2: cell.suggested_qty_wh2 ?? 1,
                wh1_stock: cell.wh1_stock ?? 0,
                wh2_stock: cell.wh2_stock ?? 0,
                to_warehouse_id: destinationId,
            };
        }
    }

    return {
        cells,
        draftPayload: [],
        error: '',

        cellsForPcode(pcode) {
            return Object.values(this.cells).filter((c) => c.pcode === pcode);
        },

        selectedCellsWh1() {
            return Object.values(this.cells).filter((c) => c.selected_wh1);
        },

        selectedCellsWh2() {
            return Object.values(this.cells).filter((c) => c.selected_wh2);
        },

        selectedCountWh1() {
            return this.selectedCellsWh1().length;
        },

        selectedCountWh2() {
            return this.selectedCellsWh2().length;
        },

        selectedCount() {
            return this.selectedCountWh1() + this.selectedCountWh2();
        },

        allSelectedInPcode(pcode) {
            const list = this.cellsForPcode(pcode).filter((c) => c.wh1_stock > 0 || c.wh2_stock > 0);
            return list.length > 0 && list.every((c) => c.selected_wh1 || c.selected_wh2);
        },

        toggleAllInPcode(pcode) {
            const list = this.cellsForPcode(pcode);
            const target = !this.allSelectedInPcode(pcode);
            for (const cell of list) {
                if (cell.wh1_stock > 0) cell.selected_wh1 = target;
                if (cell.wh2_stock > 0) cell.selected_wh2 = target;
            }
        },

        clearSelection() {
            for (const cell of Object.values(this.cells)) {
                cell.selected_wh1 = false;
                cell.selected_wh2 = false;
            }
        },

        draftFromWarehouse(slot) {
            this.error = '';
            const fromWarehouseId = slot === 1 ? sourceWarehouse1Id : sourceWarehouse2Id;
            if (!fromWarehouseId) {
                this.error = 'Source warehouse is not configured.';
                return;
            }

            const selected = slot === 1 ? this.selectedCellsWh1() : this.selectedCellsWh2();
            const items = selected.map((cell) => {
                const maxQty = slot === 1 ? cell.wh1_stock : cell.wh2_stock;
                const qty = slot === 1 ? cell.qty_wh1 : cell.qty_wh2;
                return {
                    item_id: cell.item_id,
                    quantity: Math.max(1, Math.min(maxQty, Number(qty) || 1)),
                    from_warehouse_id: fromWarehouseId,
                    to_warehouse_id: cell.to_warehouse_id,
                };
            });

            if (!items.length) {
                this.error = 'No cells selected for this warehouse.';
                return;
            }

            this.draftPayload = items;
            this.$nextTick(() => document.getElementById('arrangement-draft-form').submit());
        },
    };
}
</script>
@endpush

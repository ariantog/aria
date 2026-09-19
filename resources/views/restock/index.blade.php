@extends('layouts.app')

@section('title', 'Restock')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Stuff', 'href' => '#'],
    ['title' => 'Restock', 'href' => route('restock.index')],
];
$qtyRestockTotal = $sheets->sum('qty_restock');
$qtyProductionTotal = $sheets->sum('qty_production');
$qtyShippedTotal = $sheets->sum('qty_shipped');
$stageLabels = [
    'restock' => 'Restock',
    'production' => 'Production',
    'shipped' => 'Shipping',
    'stock' => 'Stock',
];
@endphp

<div class="flex flex-col gap-4 p-4" x-data="restockIndexExport(@js([
    'sheetIds' => $sheets->pluck('id')->values()->all(),
    'stages' => $exportStages ?? ['restock', 'production', 'shipped', 'stock'],
    'canExport' => $canExport ?? false,
]))">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Restock</h1>
            <p class="text-sm text-gray-500">Pipeline totals by restock sheet.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('restock.recommendations') }}"
               class="inline-flex items-center justify-center rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-100"
               data-testid="restock-recommendations-link">
                Recommendations
            </a>
            <a href="{{ route('restock.settings.edit') }}"
               class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Settings
            </a>
            <a href="{{ route('restock.missing.index') }}"
               class="inline-flex items-center justify-center rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-800 hover:bg-red-100">
                Missing SKUs
                @if(($missingCount ?? 0) > 0)
                    <span class="ml-2 inline-flex rounded-full bg-red-200 px-2 py-0.5 text-xs font-semibold text-red-900">{{ $missingCount }}</span>
                @endif
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    @if($typeTags->isEmpty())
        <div class="rounded-xl border border-gray-200 bg-white p-8 text-center text-gray-500">
            No asset lancar TYPE tags found. Create tags with type = Type and item type = Asset Lancar under Stuff → Tags.
        </div>
    @else
        @include('restock.partials.type-tabs', [
            'typeTags' => $typeTags,
            'activeTypeTag' => null,
            'activeOverview' => true,
        ])

        @if($sheets->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-gray-500"
                 data-testid="restock-sheets-empty">
                No restock sheets yet. Choose a product type to start tracking.
            </div>
        @else
            @if($canExport ?? false)
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" data-testid="restock-bulk-export-panel">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Export to Excel</h2>
                        <p class="mt-1 text-xs text-gray-500">Select sheets and pipeline columns. Unit cost column follows Restock settings.</p>
                    </div>
                    <form method="POST" action="{{ route('restock.export') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        @csrf
                        <template x-for="id in selectedSheetIds" :key="'sheet-'+id">
                            <input type="hidden" name="sheet_ids[]" :value="id">
                        </template>
                        <template x-for="stage in selectedStages" :key="'stage-'+stage">
                            <input type="hidden" name="stages[]" :value="stage">
                        </template>
                        <button type="submit"
                                :disabled="!canSubmitExport()"
                                data-testid="restock-bulk-export-submit"
                                class="inline-flex items-center justify-center rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">
                            Export selected
                        </button>
                    </form>
                </div>
                <div class="mt-4 flex flex-wrap gap-4 border-t border-gray-100 pt-4">
                    <div class="space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pipeline columns</p>
                        <div class="flex flex-wrap gap-3">
                            @foreach($stageLabels as $stageKey => $label)
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" value="{{ $stageKey }}"
                                       x-model="selectedStages"
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                {{ $label }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Sheets</p>
                        <button type="button" @click="toggleAllSheets()"
                                class="text-xs font-medium text-blue-600 hover:text-blue-800"
                                x-text="allSheetsSelected() ? 'Clear all' : 'Select all'"></button>
                    </div>
                </div>
            </div>
            @endif

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm" data-testid="restock-sheets-table">
                    <thead class="bg-gray-50">
                        <tr>
                            @if($canExport ?? false)
                            <th class="w-10 px-3 py-3 text-left font-medium text-gray-600">
                                <span class="sr-only">Select</span>
                            </th>
                            @endif
                            <th class="px-4 py-3 text-left font-medium text-gray-600">Sheet</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">Restock</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">Production</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">Shipping</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($sheets as $sheet)
                            <tr class="hover:bg-gray-50" data-testid="restock-sheet-row-{{ $sheet['id'] }}">
                                @if($canExport ?? false)
                                <td class="px-3 py-3">
                                    <input type="checkbox"
                                           value="{{ $sheet['id'] }}"
                                           x-model.number="selectedSheetIds"
                                           data-testid="restock-sheet-select-{{ $sheet['id'] }}"
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </td>
                                @endif
                                <td class="px-4 py-3">
                                    <a href="{{ route('restock.sheets.show', $sheet['id']) }}"
                                       class="font-medium text-blue-600 hover:text-blue-800">
                                        {{ $sheet['name'] }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-900"
                                    data-qty-restock="{{ $sheet['qty_restock'] }}">
                                    {{ number_format($sheet['qty_restock']) }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-900"
                                    data-qty-production="{{ $sheet['qty_production'] }}">
                                    {{ number_format($sheet['qty_production']) }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-900"
                                    data-qty-shipping="{{ $sheet['qty_shipped'] }}">
                                    {{ number_format($sheet['qty_shipped']) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <th @if($canExport ?? false) colspan="2" @endif class="px-4 py-3 text-left font-medium text-gray-700">Total</th>
                            <th class="px-4 py-3 text-right tabular-nums font-medium text-gray-700"
                                data-qty-restock="{{ $qtyRestockTotal }}">
                                {{ number_format($qtyRestockTotal) }}
                            </th>
                            <th class="px-4 py-3 text-right tabular-nums font-medium text-gray-700"
                                data-qty-production="{{ $qtyProductionTotal }}">
                                {{ number_format($qtyProductionTotal) }}
                            </th>
                            <th class="px-4 py-3 text-right tabular-nums font-medium text-gray-700"
                                data-qty-shipping="{{ $qtyShippedTotal }}">
                                {{ number_format($qtyShippedTotal) }}
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    @endif
</div>
@endsection

@push('scripts')
<script>
function restockIndexExport(config) {
    return {
        allSheetIds: config.sheetIds || [],
        selectedSheetIds: (config.sheetIds || []).slice(),
        selectedStages: (config.stages || []).slice(),
        canExport: config.canExport === true,
        allSheetsSelected() {
            return this.allSheetIds.length > 0
                && this.selectedSheetIds.length === this.allSheetIds.length;
        },
        toggleAllSheets() {
            if (this.allSheetsSelected()) {
                this.selectedSheetIds = [];
            } else {
                this.selectedSheetIds = this.allSheetIds.slice();
            }
        },
        canSubmitExport() {
            return this.canExport
                && this.selectedSheetIds.length > 0
                && this.selectedStages.length > 0;
        },
    };
}
</script>
@endpush

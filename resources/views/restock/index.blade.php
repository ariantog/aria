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

<div class="flex flex-col gap-4 p-4" x-data="restockIndexPage(@js([
    'sheetIds' => $sheets->pluck('id')->values()->all(),
    'stages' => $exportStages ?? ['restock', 'production', 'shipped', 'stock'],
    'canExport' => $canExport ?? false,
    'canEdit' => $canEdit ?? false,
    'flatExportRestockUrl' => route('restock.export-flat', ['source' => 'restock']),
    'flatExportProductionUrl' => route('restock.export-flat', ['source' => 'production']),
    'flatPreviewUrl' => route('restock.import-flat.preview'),
    'flatApplyUrl' => route('restock.import-flat.apply'),
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
            @can('restock-history')
            <a href="{{ route('restock.history.index') }}"
               class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
               data-testid="restock-history-link">
                Change history
            </a>
            @endcan
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

            @if(($canExport ?? false) || ($canEdit ?? false))
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" data-testid="restock-flat-pipeline-panel">
                <h2 class="text-sm font-semibold text-gray-900">Flat pipeline (SKU list)</h2>
                <p class="mt-1 text-xs text-gray-500">
                    Export one file with every sheet: columns <strong>sku</strong> and <strong>quantity</strong>.
                    After supplier confirmation, remove rejected SKUs and re-import to move quantities.
                </p>

                @if($canExport ?? false)
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('restock.export-flat', ['source' => 'restock']) }}"
                       data-testid="restock-flat-export-restock"
                       class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Download flat (restock qty)
                    </a>
                    <a href="{{ route('restock.export-flat', ['source' => 'production']) }}"
                       data-testid="restock-flat-export-production"
                       class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Download flat (production qty)
                    </a>
                </div>
                @endif

                @if($canEdit ?? false)
                <div class="mt-4 space-y-3 border-t border-gray-100 pt-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700" for="restock-flat-direction">Move</label>
                            <select id="restock-flat-direction" x-model="flatDirection"
                                    data-testid="restock-flat-direction"
                                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="to_production">Restock → Production</option>
                                <option value="to_shipped">Production → Shipping</option>
                            </select>
                        </div>
                        <div class="min-w-[12rem] flex-1">
                            <label class="mb-1 block text-xs font-medium text-gray-700" for="restock-flat-file">File (CSV or Excel)</label>
                            <input type="file" id="restock-flat-file" accept=".csv,.txt,.xlsx,.xls"
                                   @click="$event.target.value = ''"
                                   @change="flatFile = $event.target.files[0] || null; flatPreviewData = null; flatPreviewLines = []; flatCanApply = false; flatError = ''; flatFlash = '';"
                                   data-testid="restock-flat-file"
                                   class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-blue-800 hover:file:bg-blue-100">
                        </div>
                        <button type="button" @click="flatPreview()" :disabled="flatBusy || !flatFile"
                                data-testid="restock-flat-preview"
                                class="inline-flex items-center justify-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900 disabled:cursor-not-allowed disabled:opacity-50">
                            Preview
                        </button>
                        <button type="button" @click="flatApply()" :disabled="flatBusy || !flatCanSubmitApply() || !flatFile"
                                data-testid="restock-flat-apply"
                                class="inline-flex items-center justify-center rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">
                            Apply move
                        </button>
                    </div>

                    <template x-if="flatError">
                        <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" x-text="flatError"></div>
                    </template>
                    <template x-if="flatFlash">
                        <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800" x-text="flatFlash"></div>
                    </template>

                    <template x-if="flatPreviewData">
                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <p class="border-b border-gray-100 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                                <span x-text="flatPreviewData.direction_label"></span>
                                — moving <span class="font-semibold" x-text="flatPreviewData.summary.moving"></span> unit(s),
                                <span x-text="flatPreviewData.summary.skipped"></span> skipped,
                                <span x-text="flatPreviewData.summary.errors"></span> error(s).
                            </p>
                            <div class="flex flex-col gap-2 border-b border-gray-100 bg-gray-50 px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                                <label class="inline-flex items-center gap-2 text-xs text-gray-700">
                                    <span class="font-medium">Apply</span>
                                    <select x-model="flatApplyMode"
                                            data-testid="restock-flat-apply-mode"
                                            class="rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-800">
                                        <option value="checked">checked rows only</option>
                                        <option value="unchecked">unchecked rows only</option>
                                    </select>
                                </label>
                                <p class="text-xs text-gray-600" data-testid="restock-flat-apply-selection-summary">
                                    Selected for move:
                                    <span class="font-semibold tabular-nums" x-text="flatSelectedApplySummary().units"></span> unit(s),
                                    <span class="font-semibold tabular-nums" x-text="flatSelectedApplySummary().lines"></span> line(s).
                                </p>
                            </div>
                            <template x-if="!flatCanSubmitApply() && flatPreviewData && flatOkLines().length > 0">
                                <p class="border-b border-amber-100 bg-amber-50 px-3 py-2 text-xs text-amber-900"
                                   data-testid="restock-flat-apply-blocked-hint">
                                    Select at least one OK row to move (use checkboxes). Error rows are never applied.
                                    Match the Move direction to the file you exported (restock qty → Restock → Production; production qty → Production → Shipping).
                                </p>
                            </template>
                            <template x-if="flatPreviewData && flatOkLines().length === 0">
                                <p class="border-b border-amber-100 bg-amber-50 px-3 py-2 text-xs text-amber-900"
                                   data-testid="restock-flat-apply-no-ok-hint">
                                    No OK lines to move. Fix errors or upload a different file.
                                </p>
                            </template>
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="w-10 px-3 py-2 text-left font-medium text-gray-600">
                                            <span class="sr-only">Include in move</span>
                                            <input type="checkbox"
                                                   data-testid="restock-flat-select-all-ok"
                                                   :checked="flatAllOkLinesSelected()"
                                                   @change="flatToggleAllOkLines($event.target.checked)"
                                                   :disabled="flatOkLines().length === 0"
                                                   class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-40"
                                                   aria-label="Toggle all OK rows">
                                        </th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600">Line</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600">SKU</th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-600">Qty</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600">Status</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600">Detail</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="(line, idx) in flatPreviewLines" :key="'flat-'+line.line+'-'+line.sku">
                                        <tr :class="line.status === 'error' ? 'bg-red-50' : (line.status === 'ok' ? '' : 'bg-gray-50')">
                                            <td class="px-3 py-2">
                                                <input type="checkbox"
                                                       x-model="flatPreviewLines[idx].apply_checked"
                                                       :disabled="flatLineCheckboxDisabled(line)"
                                                       :data-testid="'restock-flat-line-check-'+line.line"
                                                       class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-40"
                                                       :aria-label="'Include line '+line.line">
                                            </td>
                                            <td class="px-3 py-2 tabular-nums text-gray-500" x-text="line.line"></td>
                                            <td class="px-3 py-2 font-mono text-xs" x-text="line.sku"></td>
                                            <td class="px-3 py-2 text-right tabular-nums" x-text="line.qty"></td>
                                            <td class="px-3 py-2 capitalize" x-text="line.status"></td>
                                            <td class="px-3 py-2 text-gray-600" x-text="line.message"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
                @endif
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
function restockIndexPage(config) {
    return {
        allSheetIds: config.sheetIds || [],
        selectedSheetIds: (config.sheetIds || []).slice(),
        selectedStages: (config.stages || []).slice(),
        canExport: config.canExport === true,
        flatDirection: 'to_production',
        flatFile: null,
        flatBusy: false,
        flatError: '',
        flatFlash: '',
        flatPreviewData: null,
        flatPreviewLines: [],
        flatCanApply: false,
        flatApplyMode: 'checked',
        flatPreviewUrl: config.flatPreviewUrl,
        flatApplyUrl: config.flatApplyUrl,
        init() {
            this.$watch('flatDirection', () => {
                this.flatPreviewData = null;
                this.flatPreviewLines = [];
                this.flatCanApply = false;
                this.flatError = '';
                this.flatFlash = '';
            });
        },
        flatLineIsOk(line) {
            return String(line?.status ?? '').toLowerCase() === 'ok';
        },
        flatLineCheckboxDisabled(line) {
            return !this.flatLineIsOk(line);
        },
        flatOkLines() {
            return this.flatPreviewLines.filter((line) => this.flatLineIsOk(line));
        },
        flatLineIncludedForApply(line) {
            if (!this.flatLineIsOk(line)) {
                return false;
            }

            const checked = line.apply_checked === true;

            return this.flatApplyMode === 'checked' ? checked : !checked;
        },
        flatAllOkLinesSelected() {
            const okLines = this.flatOkLines();

            return okLines.length > 0 && okLines.every((line) => line.apply_checked === true);
        },
        flatToggleAllOkLines(checked) {
            this.flatPreviewLines.forEach((line, idx) => {
                if (this.flatLineIsOk(line)) {
                    this.flatPreviewLines[idx].apply_checked = checked;
                }
            });
        },
        flatSelectedApplySummary() {
            const selected = this.flatPreviewLines.filter((line) => this.flatLineIncludedForApply(line));

            return {
                lines: selected.length,
                units: selected.reduce((sum, line) => sum + (parseInt(line.qty, 10) || 0), 0),
            };
        },
        flatSelectedLineNumbers() {
            return this.flatPreviewLines
                .filter((line) => this.flatLineIncludedForApply(line))
                .map((line) => line.line);
        },
        flatCanSubmitApply() {
            if (!this.flatFile || !this.flatPreviewData) {
                return false;
            }

            return this.flatSelectedLineNumbers().length > 0;
        },
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
        flatFormData() {
            const fd = new FormData();
            fd.append('direction', this.flatDirection);
            fd.append('file', this.flatFile);
            return fd;
        },
        async flatPreview() {
            if (!this.flatFile) return;
            this.flatBusy = true;
            this.flatError = '';
            this.flatFlash = '';
            this.flatPreviewData = null;
            this.flatPreviewLines = [];
            this.flatCanApply = false;
            try {
                const res = await fetch(this.flatPreviewUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: this.flatFormData(),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Preview failed');
                this.flatPreviewData = data;
                this.flatPreviewLines = (data.lines || []).map((line) => ({
                    ...line,
                    apply_checked: String(line.status ?? '').toLowerCase() === 'ok',
                }));
                this.flatCanApply = Boolean(data.can_apply);
                this.flatApplyMode = 'checked';
            } catch (e) {
                this.flatError = e.message || 'Preview failed';
            } finally {
                this.flatBusy = false;
            }
        },
        flatApplyFormData() {
            const fd = this.flatFormData();
            this.flatSelectedLineNumbers().forEach((lineNo) => {
                fd.append('lines[]', lineNo);
            });

            return fd;
        },
        async flatApply() {
            if (!this.flatFile || !this.flatCanSubmitApply()) return;
            this.flatBusy = true;
            this.flatError = '';
            this.flatFlash = '';
            try {
                const res = await fetch(this.flatApplyUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: this.flatApplyFormData(),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Apply failed');
                this.flatFlash = data.message || 'Applied.';
                this.flatPreviewData = null;
                this.flatPreviewLines = [];
                this.flatCanApply = false;
            } catch (e) {
                this.flatError = e.message || 'Apply failed';
            } finally {
                this.flatBusy = false;
            }
        },
    };
}
</script>
@endpush

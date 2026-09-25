@php
    $toolbarTestId = $toolbarTestId ?? 'item-catalog-scope-toolbar';
@endphp
<div class="rounded-lg border border-gray-200 bg-gray-50/80 p-4" data-testid="{{ $toolbarTestId }}">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-sm font-medium text-gray-900">Quick scope for amounts</p>
            <p class="mt-0.5 text-xs text-gray-600">Sets every pricing row below to the same save level. Catalog text follows the tab you are on.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">All pricing →</span>
            <button type="button"
                    @click="setAllPricingScopes('size')"
                    data-testid="item-scope-all-size"
                    class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100">
                This SKU
            </button>
            <button type="button"
                    @click="setAllPricingScopes('colorway')"
                    data-testid="item-scope-all-colorway"
                    class="rounded-md border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-800 hover:bg-indigo-100">
                Colorway
            </button>
            <button type="button"
                    @click="setAllPricingScopes('group')"
                    data-testid="item-scope-all-group"
                    class="rounded-md border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-900 hover:bg-amber-100">
                Whole group
            </button>
        </div>
    </div>
    <label class="mt-3 flex cursor-pointer items-start gap-3 rounded-md border border-indigo-100 bg-white px-3 py-2">
        <input type="checkbox"
               x-model="colorwayScopeSwitch"
               @change="onColorwayScopeSwitch()"
               data-testid="item-scope-colorway-switch"
               class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
        <span class="text-sm text-gray-800">
            <span class="font-medium">Use colorway for all amounts</span>
            <span class="mt-0.5 block text-xs font-normal text-gray-600">When on, every pricing scope radio moves to “This colorway” (typical default for new batches).</span>
        </span>
    </label>
</div>

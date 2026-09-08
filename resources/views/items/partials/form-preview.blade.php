@php
    $isCreate = ! isset($item);
@endphp
<div class="rounded-xl border border-gray-200 bg-white shadow-sm" data-testid="item-form-preview">
    <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-4">
        <div class="rounded-lg bg-green-500/10 p-2">
            <svg class="h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        </div>
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Item Summary Preview</h3>
            <p class="text-sm text-gray-500">
                @if($isCreate)
                    Summary of SKUs to create. Expand a row to set per-SKU price, descriptions, or cost.
                @else
                    Summary of items to be generated
                @endif
            </p>
        </div>
    </div>
    <div class="p-5">
        <template x-if="previewItems.length > 0">
            <div class="space-y-3">
                <div class="hidden gap-4 px-3 py-2 text-xs font-bold uppercase tracking-wider text-gray-500 md:grid md:grid-cols-5">
                    <div class="col-span-2">SKU / Code</div>
                    <div class="col-span-3">Generated Name</div>
                </div>
                <div class="max-h-[520px] space-y-3 overflow-y-auto pr-2">
                    <template x-for="(row, idx) in previewItems" :key="row.sku + '-' + idx">
                        <div class="rounded-lg border border-gray-100 bg-gray-50/50 p-3">
                            <div class="grid grid-cols-1 gap-2 md:grid-cols-5 md:items-center md:gap-4">
                                <div class="col-span-2 break-all font-mono text-sm font-semibold text-blue-600" x-text="row.sku"></div>
                                <div class="col-span-3 text-sm font-medium text-gray-900" x-text="row.name"></div>
                            </div>
                            @if($isCreate)
                            <details class="mt-3 rounded-lg border border-gray-200 bg-white" data-testid="item-form-sku-override"
                                     :open="previewOverrideOpen(row.sku)">
                                <summary class="cursor-pointer select-none px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-600 hover:bg-gray-50">
                                    Per-SKU values (optional)
                                </summary>
                                <div class="grid grid-cols-1 gap-4 border-t border-gray-100 p-3 md:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-700">Selling price</label>
                                        <input type="number" step="any" min="0"
                                               :name="`sku_overrides[${row.sku}][price]`"
                                               :value="skuOverrideValue(row.sku, 'price')"
                                               :placeholder="defaultPrice ? `Default: ${defaultPrice}` : 'Use default price'"
                                               data-testid="item-form-sku-override-price"
                                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                    </div>
                                    @if($isAsset ?? false)
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-700">Cost price</label>
                                        <input type="number" step="any" min="0"
                                               :name="`sku_overrides[${row.sku}][cost]`"
                                               :value="skuOverrideValue(row.sku, 'cost')"
                                               :placeholder="defaultCost ? `Default: ${defaultCost}` : 'Use default cost'"
                                               data-testid="item-form-sku-override-cost"
                                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-700">Reseller price</label>
                                        <input type="number" step="any" min="0"
                                               :name="`sku_overrides[${row.sku}][reseller_price]`"
                                               :value="skuOverrideValue(row.sku, 'reseller_price')"
                                               :placeholder="defaultResellerPrice ? `Default: ${defaultResellerPrice}` : 'Use group default'"
                                               data-testid="item-form-sku-override-reseller-price"
                                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                    </div>
                                    @endif
                                    <div class="md:col-span-2">
                                        <label class="mb-1 block text-xs font-medium text-gray-700">Description (this SKU)</label>
                                        <textarea rows="2"
                                                  :name="`sku_overrides[${row.sku}][description]`"
                                                  x-init="$el.value = skuOverrideValue(row.sku, 'description') || ''"
                                                  placeholder="Override shared description for this SKU only"
                                                  data-testid="item-form-sku-override-description"
                                                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></textarea>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="mb-1 block text-xs font-medium text-gray-700">Notes (NB) (this SKU)</label>
                                        <textarea rows="2"
                                                  :name="`sku_overrides[${row.sku}][description2]`"
                                                  x-init="$el.value = skuOverrideValue(row.sku, 'description2') || ''"
                                                  placeholder="Override shared notes for this SKU only"
                                                  data-testid="item-form-sku-override-description2"
                                                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></textarea>
                                    </div>
                                </div>
                            </details>
                            @endif
                        </div>
                    </template>
                </div>
                <div class="border-t border-gray-100 pt-4">
                    <p class="text-xs text-gray-500">Total Items to Create: <span class="font-bold text-gray-900" x-text="previewItems.length"></span></p>
                </div>
            </div>
        </template>
        <template x-if="previewItems.length === 0">
            <div class="flex flex-col items-center justify-center py-8 text-center text-gray-500">
                <svg class="mb-2 h-10 w-10 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                <p class="text-sm">Enter PCode and select Warna/Size to see preview.</p>
            </div>
        </template>
    </div>
</div>

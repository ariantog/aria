@php
    $matrixPricingFields = [
        'price' => ['label' => 'Price', 'decimals' => 0, 'effective_key' => 'effective_price', 'testid' => 'colorway-mass-price'],
        'reseller_price' => ['label' => 'Reseller', 'decimals' => 0, 'effective_key' => 'effective_reseller_price', 'testid' => 'colorway-mass-reseller'],
    ];
    if ($isAsset) {
        $matrixPricingFields['cost'] = ['label' => 'Cost', 'decimals' => 0, 'effective_key' => 'effective_cost', 'testid' => 'colorway-mass-cost'];
        $matrixPricingFields['cost_cnh'] = ['label' => 'Cost CNY', 'decimals' => 2, 'effective_key' => 'effective_cost_cnh', 'testid' => 'colorway-mass-cost-cnh'];
    }
@endphp
<div class="rounded-xl border border-gray-200 bg-white shadow-sm" data-testid="colorway-size-matrix">
    <div class="border-b border-gray-100 px-5 py-4">
        <h3 class="text-lg font-semibold text-gray-900">SKU matrix</h3>
        <p class="mt-1 text-sm text-gray-500">
            One row per size (including <span class="font-mono">AS</span> all-size). Parent description/NB are reference only —
            each SKU stores its own text. Numeric <strong>Apply</strong> overwrites; Description/NB <strong>Apply</strong> fills blank cells only.
        </p>
        @if(($parentDescription ?? '') !== '' || ($parentDescription2 ?? '') !== '')
        <p class="mt-2 text-xs text-gray-600">
            Parent defaults:
            @if(($parentDescription ?? '') !== '')
                <span class="whitespace-pre-wrap">{{ $parentDescription }}</span>
            @endif
            @if(($parentDescription2 ?? '') !== '')
                <span class="ml-2 text-gray-500">NB: {{ $parentDescription2 }}</span>
            @endif
        </p>
        @endif
    </div>
    <div class="overflow-x-auto p-4">
        <table class="min-w-[1100px] w-full text-sm">
            <thead class="border-b border-gray-200 text-xs text-gray-500">
                <tr class="align-bottom">
                    <th class="px-2 py-2 text-left font-semibold uppercase">Size</th>
                    <th class="px-2 py-2 text-left font-semibold uppercase">SKU</th>
                    @foreach($matrixPricingFields as $fieldKey => $fieldMeta)
                    <th class="px-2 py-2 text-right font-semibold">
                        <div class="flex flex-col items-end gap-1 normal-case">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $fieldMeta['label'] }}</span>
                            <div class="flex items-center gap-1">
                                <input type="number" step="any" min="0"
                                       x-model="mass.{{ $fieldKey }}"
                                       data-testid="{{ $fieldMeta['testid'] }}"
                                       placeholder="All"
                                       class="w-24 rounded-lg border border-gray-300 px-2 py-1 text-right text-sm font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                <button type="button"
                                        @click="applyMassPricing('{{ $fieldKey }}')"
                                        data-testid="{{ $fieldMeta['testid'] }}-apply"
                                        class="shrink-0 rounded-md border border-gray-300 bg-gray-50 px-2 py-1 text-xs font-semibold uppercase text-gray-700 hover:bg-gray-100">
                                    Apply
                                </button>
                            </div>
                        </div>
                    </th>
                    @endforeach
                    <th class="px-2 py-2 text-right font-semibold">
                        <div class="flex flex-col items-end gap-1 normal-case">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Ongkir</span>
                            <div class="flex items-center gap-1">
                                <input type="number" step="any" min="0" x-model="mass.ongkir" data-testid="colorway-mass-ongkir"
                                       placeholder="All"
                                       class="w-24 rounded-lg border border-gray-300 px-2 py-1 text-right text-sm font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                <button type="button" @click="applyMassPricing('ongkir')" data-testid="colorway-mass-ongkir-apply"
                                        class="shrink-0 rounded-md border border-gray-300 bg-gray-50 px-2 py-1 text-xs font-semibold uppercase text-gray-700 hover:bg-gray-100">
                                    Apply
                                </button>
                            </div>
                        </div>
                    </th>
                    <th class="px-2 py-2 text-right font-semibold uppercase">Restock</th>
                    <th class="px-2 py-2 text-left font-semibold">
                        <div class="flex flex-col gap-1 normal-case">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Description</span>
                            <div class="flex items-center gap-1">
                                <input type="text" x-model="mass.description" data-testid="colorway-mass-description"
                                       placeholder="Fill blanks"
                                       class="min-w-[8rem] flex-1 rounded-lg border border-gray-300 px-2 py-1 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                <button type="button" @click="applyMassFillBlank('description')" data-testid="colorway-mass-description-apply"
                                        class="shrink-0 rounded-md border border-gray-300 bg-gray-50 px-2 py-1 text-xs font-semibold uppercase text-gray-700 hover:bg-gray-100">
                                    Apply
                                </button>
                            </div>
                        </div>
                    </th>
                    <th class="px-2 py-2 text-left font-semibold">
                        <div class="flex flex-col gap-1 normal-case">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">NB</span>
                            <div class="flex items-center gap-1">
                                <input type="text" x-model="mass.description2" data-testid="colorway-mass-description2"
                                       placeholder="Fill blanks"
                                       class="min-w-[6rem] flex-1 rounded-lg border border-gray-300 px-2 py-1 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                <button type="button" @click="applyMassFillBlank('description2')" data-testid="colorway-mass-description2-apply"
                                        class="shrink-0 rounded-md border border-gray-300 bg-gray-50 px-2 py-1 text-xs font-semibold uppercase text-gray-700 hover:bg-gray-100">
                                    Apply
                                </button>
                            </div>
                        </div>
                    </th>
                    <th class="px-2 py-2 text-center font-semibold uppercase w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($sizeRows as $row)
                <tr data-testid="colorway-matrix-row-{{ $row['id'] }}">
                    <td class="px-2 py-2 align-top">
                        <select name="items[{{ $row['id'] }}][size_tag_id]" required
                                data-testid="colorway-size-{{ $row['id'] }}"
                                class="w-full min-w-[5rem] rounded-lg border border-gray-300 px-2 py-1.5 text-sm font-semibold focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            @foreach($sizeTags as $tag)
                            <option value="{{ $tag->id }}" @selected((int) ($row['size_tag_id'] ?? 0) === (int) $tag->id)>
                                {{ $tag->code }}@if($tag->code === 'AS') (all size)@endif
                            </option>
                            @endforeach
                        </select>
                    </td>
                    <td class="px-2 py-2 align-top">
                        <span class="font-mono text-xs text-blue-600">{{ $row['code'] }}</span>
                    </td>
                    @foreach($matrixPricingFields as $fieldKey => $fieldMeta)
                    @php
                        $storedScope = $row['pricing'][$fieldKey]['scope'] ?? '';
                        $storedValue = (float) ($row['pricing'][$fieldKey]['value'] ?? 0);
                        $effectiveValue = (float) ($row[$fieldMeta['effective_key']] ?? 0);
                        $defaultValue = $storedScope === 'size' && $storedValue > 0
                            ? $storedValue
                            : ($effectiveValue > 0 ? $effectiveValue : '');
                        $inputValue = old('items.'.$row['id'].'.pricing.'.$fieldKey.'.value', $defaultValue);
                    @endphp
                    <td class="px-2 py-2 text-right align-top">
                        <input type="hidden" name="items[{{ $row['id'] }}][pricing][{{ $fieldKey }}][scope]" value="size">
                        <input type="number" step="any" min="0"
                               name="items[{{ $row['id'] }}][pricing][{{ $fieldKey }}][value]"
                               value="{{ $inputValue }}"
                               data-mass-pricing="{{ $fieldKey }}"
                               placeholder="—"
                               class="w-24 rounded-lg border border-gray-300 px-2 py-1.5 text-right text-sm font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </td>
                    @endforeach
                    <td class="px-2 py-2 text-right align-top">
                        <input type="number" step="any" min="0" name="items[{{ $row['id'] }}][ongkir]"
                               value="{{ old('items.'.$row['id'].'.ongkir', $row['ongkir']) }}"
                               data-mass-pricing="ongkir"
                               class="w-24 rounded-lg border border-gray-300 px-2 py-1.5 text-right text-sm font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </td>
                    <td class="px-2 py-2 text-right align-top">
                        <input type="number" min="1" step="1" name="items[{{ $row['id'] }}][restock_urgent_threshold]"
                               value="{{ (int) ($row['restock_urgent_threshold'] ?? 0) > 0 ? $row['restock_urgent_threshold'] : '' }}"
                               data-mass-pricing="restock_urgent_threshold"
                               placeholder="—"
                               class="w-20 rounded-lg border border-gray-300 px-2 py-1.5 text-right text-sm font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </td>
                    <td class="px-2 py-2 align-top">
                        <textarea name="items[{{ $row['id'] }}][description]" rows="2"
                                  data-mass-text="description"
                                  data-testid="colorway-desc-{{ $row['id'] }}"
                                  placeholder="SKU description"
                                  class="min-w-[12rem] w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">{{ $row['description'] }}</textarea>
                    </td>
                    <td class="px-2 py-2 align-top">
                        <textarea name="items[{{ $row['id'] }}][description2]" rows="2"
                                  data-mass-text="description2"
                                  data-testid="colorway-nb-{{ $row['id'] }}"
                                  placeholder="SKU NB"
                                  class="min-w-[8rem] w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">{{ $row['description2'] }}</textarea>
                    </td>
                    <td class="px-2 py-2 align-top text-center text-xs text-gray-400">—</td>
                </tr>
                @endforeach
                <template x-for="(draft, idx) in draftRows" :key="draft.key">
                    <tr class="bg-amber-50/40" :data-testid="'colorway-draft-row-' + draft.key">
                        <td class="px-2 py-2 align-top">
                            <select :name="'new_items[' + draft.key + '][size_tag_id]'" required
                                    x-model="draft.size_tag_id"
                                    class="w-full min-w-[5rem] rounded-lg border border-amber-200 px-2 py-1.5 text-sm font-semibold focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                <option value="">Pick size</option>
                                <template x-for="tag in sizeTags" :key="tag.id">
                                    <option :value="tag.id" x-text="tag.code === allSizeCode ? tag.code + ' (all size)' : tag.code"></option>
                                </template>
                            </select>
                        </td>
                        <td class="px-2 py-2 align-top text-xs text-gray-500 italic">New (save to create)</td>
                        @foreach($matrixPricingFields as $fieldKey => $fieldMeta)
                        <td class="px-2 py-2 text-right align-top">
                            <input type="hidden" :name="'new_items[' + draft.key + '][pricing][' + '{{ $fieldKey }}' + '][scope]'" value="size">
                            <input type="number" step="any" min="0"
                                   :name="'new_items[' + draft.key + '][pricing][' + '{{ $fieldKey }}' + '][value]'"
                                   x-model="draft.pricing.{{ $fieldKey }}"
                                   data-mass-pricing="{{ $fieldKey }}"
                                   class="w-24 rounded-lg border border-gray-300 px-2 py-1.5 text-right text-sm font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        </td>
                        @endforeach
                        <td class="px-2 py-2 text-right align-top">
                            <input type="number" step="any" min="0"
                                   :name="'new_items[' + draft.key + '][ongkir]'"
                                   x-model="draft.ongkir"
                                   data-mass-pricing="ongkir"
                                   class="w-24 rounded-lg border border-gray-300 px-2 py-1.5 text-right text-sm font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        </td>
                        <td class="px-2 py-2 text-right align-top">
                            <input type="number" min="1" step="1"
                                   :name="'new_items[' + draft.key + '][restock_urgent_threshold]'"
                                   x-model="draft.restock"
                                   data-mass-pricing="restock_urgent_threshold"
                                   class="w-20 rounded-lg border border-gray-300 px-2 py-1.5 text-right text-sm font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        </td>
                        <td class="px-2 py-2 align-top">
                            <textarea rows="2" :name="'new_items[' + draft.key + '][description]'"
                                      x-model="draft.description"
                                      data-mass-text="description"
                                      class="min-w-[12rem] w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></textarea>
                        </td>
                        <td class="px-2 py-2 align-top">
                            <textarea rows="2" :name="'new_items[' + draft.key + '][description2]'"
                                      x-model="draft.description2"
                                      data-mass-text="description2"
                                      class="min-w-[8rem] w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></textarea>
                        </td>
                        <td class="px-2 py-2 align-top text-center">
                            <button type="button" @click="removeDraftRow(idx)"
                                    class="rounded-md border border-red-200 px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-50">
                                Remove
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
    <div class="border-t border-gray-100 px-5 py-3">
        <button type="button" @click="addDraftRow()" data-testid="colorway-add-size-row"
                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-800 hover:bg-gray-50">
            + Add size row
        </button>
    </div>
</div>

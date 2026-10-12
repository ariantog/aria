@php
    $defaults = $colorwayDefaults ?? ['price' => 0, 'reseller_price' => 0, 'cost' => 0, 'cost_cnh' => 0];
    $fields = [
        'price' => ['label' => 'Selling price', 'step' => 'any', 'decimals' => 0],
        'reseller_price' => ['label' => 'Reseller price', 'step' => 'any', 'decimals' => 0],
    ];
    if ($isAsset ?? false) {
        $fields['cost'] = ['label' => 'Cost (IDR)', 'step' => 'any', 'decimals' => 0];
        $fields['cost_cnh'] = ['label' => 'Cost (CNY)', 'step' => 'any', 'decimals' => 2];
    }
@endphp
<form method="POST"
      action="{{ route('items.colorway-apply-pricing', $group) }}"
      class="mb-4 rounded-lg border border-indigo-200 bg-indigo-50/50 p-4"
      data-testid="colorway-pricing-apply-form"
      onsubmit="return confirm('Apply selected amounts to this colorway? Stored size overrides for those fields are cleared so every size inherits the colorway default. Use 0 to clear the colorway default and inherit from the parent group.');">
    @csrf
    @method('PUT')
    <p class="mb-3 text-sm font-medium text-indigo-950">Colorway default pricing (all sizes)</p>
    <p class="mb-4 text-xs leading-relaxed text-indigo-900/80">
        Check the fields you want to update, enter amounts, then <strong>Apply</strong>.
        <span class="font-mono">0</span> clears the colorway default (sizes inherit from the parent group).
        Per-size exceptions stay in the matrix below and are saved with <strong>Save colorway</strong>.
        @if($isAsset ?? false)
        All-size (AS) assets use the same colorway default when only one SKU exists.
        @endif
    </p>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-{{ count($fields) > 2 ? '4' : '2' }}">
        @foreach($fields as $fieldKey => $meta)
        <div class="rounded-lg border border-white/80 bg-white p-3">
            <label class="mb-2 flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-800">
                <input type="checkbox"
                       name="apply[]"
                       value="{{ $fieldKey }}"
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                       data-testid="colorway-apply-field-{{ $fieldKey }}"
                       @checked(in_array($fieldKey, (array) old('apply', []), true))>
                {{ $meta['label'] }}
            </label>
            <input type="number"
                   step="{{ $meta['step'] }}"
                   min="0"
                   name="pricing[{{ $fieldKey }}]"
                   value="{{ old('pricing.'.$fieldKey, $defaults[$fieldKey] ?? 0) }}"
                   data-testid="colorway-apply-value-{{ $fieldKey }}"
                   class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-right font-mono text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
        </div>
        @endforeach
    </div>
    <div class="mt-4 flex justify-end">
        <button type="submit"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                data-testid="colorway-apply-pricing">
            Apply to all sizes
        </button>
    </div>
</form>

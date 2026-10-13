@php
    $fi = $formItem ?? [];
    $fields = [
        'price' => ['label' => 'Selling price', 'step' => 'any', 'decimals' => 0],
        'reseller_price' => ['label' => 'Reseller price', 'step' => 'any', 'decimals' => 0],
        'cost' => ['label' => 'Cost price (IDR)', 'step' => 'any', 'decimals' => 0],
        'cost_cnh' => ['label' => 'Cost price (CNY)', 'step' => 'any', 'decimals' => 2],
    ];

    if (! ($isAsset ?? false)) {
        $fields = array_intersect_key($fields, array_flip(['price', 'reseller_price']));
    }
@endphp

<fieldset class="space-y-4 rounded-lg border border-indigo-200 bg-indigo-50/40 p-4" data-testid="item-pricing-create">
    <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-indigo-800">Pricing</legend>
    <p class="px-1 text-xs leading-relaxed text-gray-600">
        First colorway for a parent saves amounts on the <strong>parent group</strong>; later colorways store amounts on each <strong>new SKU</strong>. Parent defaults apply when a SKU has no override.
    </p>

    <div class="grid gap-4 sm:grid-cols-2">
        @foreach($fields as $fieldKey => $meta)
            @php
                $oldValue = old($fieldKey, $fi[$fieldKey] ?? '');
            @endphp
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-800" for="item-form-pricing-{{ $fieldKey }}-value">{{ $meta['label'] }}</label>
                <input type="number"
                       step="{{ $meta['step'] }}"
                       min="0"
                       id="item-form-pricing-{{ $fieldKey }}-value"
                       name="{{ $fieldKey }}"
                       value="{{ $oldValue }}"
                       placeholder="0"
                       data-testid="item-form-pricing-{{ $fieldKey }}-value"
                       class="w-full max-w-xs rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                @error($fieldKey)<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
        @endforeach
    </div>
</fieldset>

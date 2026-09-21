@php
    $pricingState = $pricingState ?? [];
    $pricingPrefix = $pricingPrefix ?? 'pricing';
    $pricingIdPrefix = $pricingIdPrefix ?? 'item-pricing';
    $showEffective = $showEffective ?? true;

    $fields = [
        'price' => 'Selling price',
        'reseller_price' => 'Reseller price',
        'cost' => 'Cost price (IDR)',
        'cost_cnh' => 'Cost price (CNY)',
    ];

    $scopes = [
        \App\Support\ItemPricing::SCOPE_SIZE => 'This SKU',
        \App\Support\ItemPricing::SCOPE_COLORWAY => 'This colorway',
        \App\Support\ItemPricing::SCOPE_GROUP => 'Whole group',
    ];

    $pricingIntro = $pricingIntro ?? 'Choose where each amount is saved. If a SKU has no value, the colorway applies; if the colorway has none, the whole group applies.';
@endphp

<fieldset class="space-y-5 rounded-lg border border-indigo-200 bg-indigo-50/40 p-4" data-testid="item-pricing-scopes">
    <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-indigo-800">Pricing</legend>
    @if($pricingIntro !== '')
    <p class="px-1 text-xs leading-relaxed text-gray-600">{{ $pricingIntro }}</p>
    @endif

    @foreach($fields as $fieldKey => $fieldLabel)
        @php
            $state = $pricingState[$fieldKey] ?? ['scope' => \App\Support\ItemPricing::SCOPE_COLORWAY, 'value' => 0, 'effective' => 0];
            $oldScope = old("{$pricingPrefix}.{$fieldKey}.scope", $state['scope'] ?? \App\Support\ItemPricing::SCOPE_COLORWAY);
            $oldValue = old("{$pricingPrefix}.{$fieldKey}.value", $state['value'] ?? 0);
            $effective = (float) ($state['effective'] ?? 0);
        @endphp
        <div class="rounded-lg border border-white/80 bg-white/70 p-4" data-testid="item-pricing-field-{{ $fieldKey }}">
            <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <label class="text-sm font-medium text-gray-800" for="{{ $pricingIdPrefix }}-{{ $fieldKey }}-value">{{ $fieldLabel }}</label>
                @if($showEffective && $effective > 0)
                    <p class="text-xs text-gray-500">Effective: <span class="font-mono text-gray-800">{{ format_amount($effective, $fieldKey === 'cost_cnh' ? 2 : 0) }}</span></p>
                @endif
            </div>

            <div class="mb-3 flex flex-wrap gap-3">
                @foreach($scopes as $scopeKey => $scopeLabel)
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-gray-200 bg-white px-2 py-1 text-xs text-gray-700">
                        <input type="radio"
                               name="{{ $pricingPrefix }}[{{ $fieldKey }}][scope]"
                               value="{{ $scopeKey }}"
                               @checked($oldScope === $scopeKey)
                               data-testid="{{ $pricingIdPrefix }}-{{ $fieldKey }}-scope-{{ $scopeKey }}"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        {{ $scopeLabel }}
                    </label>
                @endforeach
            </div>

            <input type="number"
                   step="any"
                   min="0"
                   id="{{ $pricingIdPrefix }}-{{ $fieldKey }}-value"
                   name="{{ $pricingPrefix }}[{{ $fieldKey }}][value]"
                   value="{{ $oldValue }}"
                   placeholder="0"
                   data-testid="{{ $pricingIdPrefix }}-{{ $fieldKey }}-value"
                   class="w-full max-w-xs rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            @error("{$pricingPrefix}.{$fieldKey}.value")<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            @error("{$pricingPrefix}.{$fieldKey}.scope")<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
    @endforeach
</fieldset>

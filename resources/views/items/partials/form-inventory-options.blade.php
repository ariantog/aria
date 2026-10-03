@php
    $showDecimal = $showDecimalQuantityOption ?? false;
    if (! $showDecimal) {
        return;
    }
    $fi = $formItem ?? [];
    $allowDecimal = old('allow_decimal_quantity', $fi['allow_decimal_quantity'] ?? false);
@endphp
<div class="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-3" data-testid="item-inventory-options">
    <h3 class="text-sm font-semibold text-gray-900">Quantity</h3>
    <label class="flex items-start gap-2 text-sm text-gray-700">
        <input type="checkbox" name="allow_decimal_quantity" value="1" class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
               @checked($allowDecimal) data-testid="item-qty-desimal">
        <span>
            <span class="font-medium">Qty desimal</span>
            <span class="block text-xs text-gray-500">Allow fractional quantities (max 2 decimal places), e.g. 25.9 m². Default is whole numbers only.</span>
        </span>
    </label>
</div>

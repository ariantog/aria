@php
    $applyFormId = $applyFormId ?? '';
    $itemId = (int) ($row['item_id'] ?? 0);
    $eligible = ($canApplyRecommended ?? null) ? ($canApplyRecommended)($row) : false;
@endphp
@if($eligible && $applyFormId !== '')
    <input type="checkbox"
           name="item_ids[]"
           value="{{ $itemId }}"
           form="{{ $applyFormId }}"
           checked
           data-apply-row-checkbox
           class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
           aria-label="Apply recommended qty for SKU {{ $itemId }}"
           @change="bumpApplySelection()"
           data-testid="restock-recommendations-apply-check-{{ $itemId }}">
@endif

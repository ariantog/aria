@php
    $itemId = (int) ($row['item_id'] ?? 0);
    $canEditRow = $canApplyToSheets
        && \App\Enums\ItemType::coerce($row['item_type'] ?? null) === \App\Enums\ItemType::ASSET_LANCAR;
    $suggestedQty = (int) ($row['suggested_restock_qty'] ?? 0);
    $qtyRestock = (int) ($row['qty_restock'] ?? 0);
@endphp
@if($canEditRow)
    <div class="inline-flex flex-col items-end gap-1">
        <div class="inline-flex items-center gap-1">
            <span x-show="!isEditing({{ $itemId }})"
                  class="inline-flex items-center gap-1">
                <span class="tabular-nums"
                      :class="isEdited({{ $itemId }}) ? 'font-semibold text-amber-800' : ''"
                      x-text="formatQty(drafts['{{ $itemId }}'] ?? {{ $qtyRestock }})"
                      data-testid="restock-recommendations-qty-display-{{ $itemId }}"></span>
                <span x-show="isEdited({{ $itemId }})"
                      class="text-xs font-medium text-amber-700"
                      title="Qty changed — save the page to update sheets">*</span>
            </span>
            <input x-show="isEditing({{ $itemId }})"
                   x-cloak
                   type="number"
                   min="0"
                   step="1"
                   class="w-20 rounded-md border border-blue-400 px-2 py-1 text-right text-sm tabular-nums shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                   x-model.number="drafts['{{ $itemId }}']"
                   @input="onDraftInput({{ $itemId }})"
                   @keydown.enter.prevent="finishEdit({{ $itemId }})"
                   @blur="finishEdit({{ $itemId }})"
                   aria-label="Restock qty for SKU {{ $itemId }}"
                   data-testid="restock-recommendations-qty-{{ $itemId }}">
            <button type="button"
                    x-show="!isEditing({{ $itemId }})"
                    @click="startEdit({{ $itemId }})"
                    class="whitespace-nowrap rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
                    data-testid="restock-recommendations-edit-{{ $itemId }}">
                Edit
            </button>
        </div>
        @if($suggestedQty > 0)
            <span class="text-xs text-gray-500">Suggested: {{ $suggestedQty }}</span>
        @endif
        @include('restock.partials.sheet-links', ['links' => $row['sheet_links'] ?? [], 'itemId' => $itemId])
    </div>
@else
    <div class="tabular-nums">{{ number_format($qtyRestock, 0) }}</div>
    @include('restock.partials.sheet-links', ['links' => $row['sheet_links'] ?? [], 'itemId' => $itemId])
@endif

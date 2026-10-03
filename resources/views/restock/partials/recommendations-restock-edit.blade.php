@php
    $itemId = (int) ($row['item_id'] ?? 0);
    $canEditRow = $canApplyToSheets
        && \App\Enums\ItemType::coerce($row['item_type'] ?? null) === \App\Enums\ItemType::ASSET_LANCAR;
    $suggestedQty = (int) ($row['suggested_restock_qty'] ?? 0);
@endphp
@if($canEditRow)
    <form method="POST" action="{{ route('restock.recommendations.save-restock') }}" class="inline-flex flex-col items-end gap-1">
        @csrf
        @include('restock.partials.recommendations-apply-hidden')
        <input type="hidden" name="item_id" value="{{ $itemId }}">
        <div class="inline-flex items-center gap-1">
            <input type="number"
                   name="qty_restock"
                   value="{{ (int) ($row['qty_restock'] ?? 0) }}"
                   min="0"
                   step="1"
                   class="w-20 rounded-md border border-gray-300 px-2 py-1 text-right text-sm tabular-nums shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                   aria-label="Restock qty for SKU {{ $itemId }}"
                   data-testid="restock-recommendations-qty-{{ $itemId }}">
            <button type="submit"
                    class="whitespace-nowrap rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
                    data-testid="restock-recommendations-save-{{ $itemId }}">
                Save
            </button>
        </div>
        @if($suggestedQty > 0)
            <span class="text-xs text-gray-500">Suggested: {{ $suggestedQty }}</span>
        @endif
        @include('restock.partials.sheet-links', ['links' => $row['sheet_links'] ?? [], 'itemId' => $itemId])
    </form>
@else
    <div class="tabular-nums">{{ number_format($row['qty_restock'] ?? 0, 0) }}</div>
    @include('restock.partials.sheet-links', ['links' => $row['sheet_links'] ?? [], 'itemId' => $itemId])
@endif

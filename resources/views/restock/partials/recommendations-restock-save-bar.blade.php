<form method="POST"
      action="{{ route('restock.recommendations.save-restock') }}"
      class="flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2"
      data-testid="restock-recommendations-save-form">
    @csrf
    @include('restock.partials.recommendations-apply-hidden')
    <template x-for="(itemId, index) in editedItemIdsList()" :key="itemId">
        <span class="hidden">
            <input type="hidden" :name="'rows[' + index + '][item_id]'" :value="itemId">
            <input type="hidden" :name="'rows[' + index + '][qty_restock]'" :value="draftQty(itemId)">
        </span>
    </template>
    <button type="submit"
            class="rounded-lg px-3 py-1.5 text-sm font-medium text-white"
            :disabled="!hasPendingEdits()"
            :class="hasPendingEdits() ? 'bg-blue-600 hover:bg-blue-700' : 'cursor-not-allowed bg-gray-400'"
            data-testid="restock-recommendations-save-page">
        Save restock changes (<span x-text="editCount()"></span>)
    </button>
    <span class="text-xs text-gray-500" x-show="hasPendingEdits()">
        Saves edited SKUs on this page to their TYPE restock sheets.
    </span>
</form>
